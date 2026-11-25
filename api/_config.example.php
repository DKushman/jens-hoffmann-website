<?php
/**
 * Vorlage für api/_config.php – auf dem Server als _config.php kopieren und ausfüllen.
 * Nicht versionieren (siehe .gitignore).
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

return [
    'smtp_host' => 'smtp.ionos.de',
    'smtp_port' => 465,
    'smtp_user' => 'dein-postfach@deine-domain.de',
    'smtp_pass' => 'DEIN_IONOS_MAIL_PASSWORT',

    'from_email' => 'dein-postfach@deine-domain.de',
    'from_name'  => 'Jens Hoffmann',

    'to_email' => 'dein-postfach@deine-domain.de',
    'to_name'  => 'Jens Hoffmann',

    'site' => 'kiezfueralle.berlin',

    'rate_limit'  => 5,
    'rate_window' => 3600,

  // Zufällige Zeichenkette generieren, z. B. openssl rand -hex 24
    'ip_salt' => 'HIER_ZUFÄLLIGEN_SALT_EINTRAGEN',
];