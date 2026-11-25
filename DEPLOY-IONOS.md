# Deployment (IONOS)

## Voraussetzungen

- Webhosting mit **PHP 7.4+**
- HTTPS für die Domain aktiv
- Ordner `api/` inkl. `.htaccess` und `_config.php` auf dem Server

## Hochladen

```bash
rsync -avz --delete --exclude '.DS_Store' ./ \
  su1205313@access-5021112032.webspace-host.com:~/jens-hoffmann-website-richtig\ nach\ meeting/
```

## Nach dem Upload prüfen

1. `https://kiezfueralle.berlin/api/_config.php` → **403/404** (kein Klartext)
2. `https://kiezfueralle.berlin/kontakt.html` → Testnachricht senden
3. E-Mail beim Empfänger + Bestätigung beim Absender

## Konfiguration

`_config.php` nur auf dem Server pflegen (nicht ins Git). Vorlage: `api/_config.example.php`.

`from_email` und `smtp_user` müssen dasselbe IONOS-Postfach sein.