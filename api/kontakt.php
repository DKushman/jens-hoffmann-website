<?php
/**
 * Kontaktformular – Handler
 *
 * 1. nimmt den POST von /kontakt.html entgegen und prüft ihn
 * 2. schickt die Nachricht per SMTP (IONOS) an Jens
 * 3. schickt eine Eingangsbestätigung an die absendende Person
 *
 * Antwortet mit JSON (Formular mit JavaScript) oder leitet zurück
 * auf /kontakt.html#gesendet bzw. #fehler (ohne JavaScript).
 * Läuft ab PHP 7.4, ohne Bibliotheken.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

$config = require __DIR__ . '/_config.php';

$wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;


/* ---------------------------------------------------------------
   Hilfsfunktionen
   --------------------------------------------------------------- */

function respond(bool $ok, string $message, int $status, bool $wantsJson): void
{
    if ($wantsJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: /kontakt.html#' . ($ok ? 'gesendet' : 'fehler'), true, 303);
    }
    exit;
}

/** Formularfeld lesen, kürzen, Steuerzeichen entfernen. */
function field(string $key, int $max): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = str_replace("\0", '', trim($value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

/** Für Kopfzeilen: keine Zeilenumbrüche (verhindert Header-Injection). */
function oneLine(string $value): string
{
    return trim((string) preg_replace('/[\r\n\t]+/', ' ', $value));
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/** Einfaches Limit pro IP. Die IP wird nur als Hash gespeichert. */
function isRateLimited(string $ip, string $salt, int $limit, int $window): bool
{
    $dir = sys_get_temp_dir();
    $now = time();

    // Gelegentlich alte Einträge aufräumen
    if (random_int(1, 20) === 1) {
        foreach (glob($dir . '/kontakt_*.json') ?: [] as $old) {
            if (@filemtime($old) < $now - $window) {
                @unlink($old);
            }
        }
    }

    $file = $dir . '/kontakt_' . hash_hmac('sha256', $ip, $salt) . '.json';
    $hits = [];
    if (is_file($file)) {
        $hits = json_decode((string) @file_get_contents($file), true);
        $hits = is_array($hits) ? $hits : [];
    }
    $hits = array_values(array_filter($hits, function ($t) use ($now, $window) {
        return is_int($t) && $t > $now - $window;
    }));

    if (count($hits) >= $limit) {
        return true;
    }

    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return false;
}

/* ---------------------------------------------------------------
   Minimaler SMTP-Client (SSL, AUTH LOGIN)
   --------------------------------------------------------------- */

final class SmtpMailer
{
    /** @var array */
    private $config;
    /** @var resource|null */
    private $socket;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $toEmail, string $toName, string $subject, string $body, string $fromName, string $replyEmail = '', string $replyName = ''): void
    {
        $c = $this->config;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $c['smtp_host'],
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            'ssl://' . $c['smtp_host'] . ':' . (int) $c['smtp_port'],
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($socket === false) {
            throw new RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $errstr . ' (' . $errno . ')');
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, 15);

        try {
            $this->expect([220]);
            $this->command('EHLO ' . ($c['site'] ?? 'localhost'), [250]);
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($c['smtp_user']), [334]);
            $this->command(base64_encode($c['smtp_pass']), [235]);
            $this->command('MAIL FROM:<' . $c['from_email'] . '>', [250]);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', [354]);
            $this->write($this->buildMessage($toEmail, $toName, $subject, $body, $fromName, $replyEmail, $replyName) . "\r\n.");
            $this->expect([250]);
            $this->write('QUIT');
        } finally {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function buildMessage(string $toEmail, string $toName, string $subject, string $body, string $fromName, string $replyEmail, string $replyName): string
    {
        $c = $this->config;
        $domain = substr(strrchr($c['from_email'], '@') ?: '@localhost', 1);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::address($c['from_email'], $fromName),
            'To: ' . self::address($toEmail, $toName),
        ];
        if ($replyEmail !== '') {
            $headers[] = 'Reply-To: ' . self::address($replyEmail, $replyName);
        }
        $headers[] = 'Subject: ' . self::encode($subject);
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $headers[] = 'X-Mailer: kiezfueralle-kontakt';

        // Base64 enthält keine Punkte am Zeilenanfang -> kein Dot-Stuffing nötig
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        $encoded = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));

        return implode("\r\n", $headers) . "\r\n\r\n" . $encoded;
    }

    private static function encode(string $text): string
    {
        $text = oneLine($text);
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
        }
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    private static function address(string $email, string $name): string
    {
        $name = oneLine($name);
        return $name === '' ? '<' . $email . '>' : self::encode($name) . ' <' . $email . '>';
    }

    private function command(string $line, array $codes): void
    {
        $this->write($line);
        $this->expect($codes);
    }

    private function write(string $data): void
    {
        if (fwrite($this->socket, $data . "\r\n") === false) {
            throw new RuntimeException('SMTP: Schreiben fehlgeschlagen');
        }
    }

    private function expect(array $codes): void
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            // Mehrzeilige Antworten: "250-…" geht weiter, "250 …" ist die letzte Zeile
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP: unerwartete Antwort ' . trim($response));
        }
    }
}

/* ---------------------------------------------------------------
   Ablauf
   --------------------------------------------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(false, 'Nur POST erlaubt.', 405, $wantsJson);
}

// Nur Anfragen von der eigenen Website annehmen
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = (string) parse_url($origin, PHP_URL_HOST);
    $host = (string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($originHost === '' || strcasecmp($originHost, $host) !== 0) {
        respond(false, 'Ungültige Anfrage.', 403, $wantsJson);
    }
}

// Spam-Schutz 1: verstecktes Feld, das nur Bots ausfüllen -> so tun, als wäre alles gut
if (field('website', 200) !== '') {
    respond(true, 'Danke!', 200, $wantsJson);
}

// Spam-Schutz 2: Formular in unter 3 Sekunden abgeschickt (Zeit misst der Browser)
$elapsed = (int) field('elapsed', 12);
if ($elapsed > 0 && $elapsed < 3000) {
    respond(true, 'Danke!', 200, $wantsJson);
}

$name = oneLine(field('name', 120));
$email = oneLine(field('email', 254));
$phone = oneLine(field('telefon', 60));
$message = field('nachricht', 5000);
$consent = field('datenschutz', 10) !== '';

if (textLength($name) < 2) {
    respond(false, 'Bitte gib deinen Namen an.', 422, $wantsJson);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Bitte gib eine gültige E-Mail-Adresse an.', 422, $wantsJson);
}
if (textLength($message) < 10) {
    respond(false, 'Bitte schreib eine etwas längere Nachricht.', 422, $wantsJson);
}
if (!$consent) {
    respond(false, 'Bitte stimme der Verarbeitung deiner Angaben zu.', 422, $wantsJson);
}

if (isRateLimited($_SERVER['REMOTE_ADDR'] ?? 'unbekannt', (string) ($config['ip_salt'] ?? __FILE__), (int) $config['rate_limit'], (int) $config['rate_window'])) {
    respond(false, 'Du hast gerade schon mehrere Nachrichten geschickt. Bitte versuch es später noch einmal.', 429, $wantsJson);
}

$mailer = new SmtpMailer($config);
$site = $config['site'];
$sentAt = date('d.m.Y') . ' um ' . date('H:i') . ' Uhr';

// 1. Nachricht an Jens (Antworten gehen direkt an die absendende Person)
$toJens = "Neue Nachricht über das Kontaktformular auf {$site}\n\n"
    . "Name:      {$name}\n"
    . "E-Mail:    {$email}\n"
    . 'Telefon:   ' . ($phone !== '' ? $phone : '–') . "\n\n"
    . "Nachricht:\n{$message}\n\n"
    . "—\nGesendet am {$sentAt}.\nZum Antworten einfach auf diese E-Mail antworten.\n";

try {
    $mailer->send(
        $config['to_email'],
        $config['to_name'],
        "Kontaktformular – {$name}",
        $toJens,
        "{$name} (über {$site})",
        $email,
        $name
    );
} catch (Throwable $e) {
    error_log('[kontakt] Versand an Jens fehlgeschlagen: ' . $e->getMessage());
    respond(
        false,
        'Die Nachricht konnte gerade nicht gesendet werden. Bitte versuch es später noch einmal oder schreib direkt an ' . $config['to_email'] . '.',
        502,
        $wantsJson
    );
}

// 2. Bestätigung an die absendende Person (Fehler hier blockieren nicht)
$confirmation = "Hallo {$name},\n\n"
    . "vielen Dank für deine Nachricht! Sie ist bei mir angekommen, und ich melde mich so bald wie möglich bei dir.\n\n"
    . "Zur Sicherheit hier noch einmal deine Angaben:\n\n"
    . "Deine Nachricht:\n{$message}\n\n"
    . "Viele Grüße\n"
    . "Jens Hoffmann\n"
    . "Mitglied des Abgeordnetenhauses von Berlin\n"
    . "Wahlkreis 3 · Lichterfelde West & Zehlendorf Süd\n"
    . "https://{$site}\n\n"
    . "—\nDiese E-Mail wurde automatisch versendet, weil am {$sentAt} über {$site} eine Nachricht mit dieser Adresse geschickt wurde. "
    . "Falls du das nicht warst, kannst du sie einfach ignorieren.\n";

try {
    $mailer->send(
        $email,
        $name,
        'Danke für deine Nachricht – Jens Hoffmann',
        $confirmation,
        $config['from_name'],
        $config['to_email'],
        $config['to_name']
    );
} catch (Throwable $e) {
    error_log('[kontakt] Bestätigung fehlgeschlagen: ' . $e->getMessage());
}

respond(true, 'Danke! Deine Nachricht ist angekommen.', 200, $wantsJson);
