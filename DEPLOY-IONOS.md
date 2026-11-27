# Deployment (IONOS) – kiezfueralle.berlin

## Warum die Seite „alt“ wirkt

`git push` aktualisiert nur **GitHub Pages** (Vorschau), nicht **kiezfueralle.berlin**.

Die Live-Domain läuft auf **IONOS**. Dort liegt der Stand oft noch mit **absoluten Pfaden** (`/assets/…`) – das ist eine **ältere Kopie im Webroot**.

Wenn rsync in den Unterordner  
`jens-hoffmann-website-richtig nach meeting/`  
geht, **ändert sich die öffentliche Seite nicht** (dieser Pfad ist nicht die Domain-Wurzel).

## Hochladen (richtig)

Im Terminal (IONOS-Passwort eingeben):

```bash
cd "/Users/davidjoelchiosea/Downloads/jens-hoffmann-website-richtig nach meeting"
./deploy-ionos.sh
```

Das synchronisiert ins **SFTP-Heimverzeichnis** (= Webroot für die Domain).

`api/_config.php` wird **nicht** überschrieben (bleibt die Server-Konfiguration).

## Nach dem Upload prüfen

1. **Inkognito:** https://kiezfueralle.berlin/parlamentsreporte.html → **„3 Ausgaben“**, neue PDF vom **26.11.2026**
2. https://kiezfueralle.berlin/kontakt.html → Testnachricht
3. `https://kiezfueralle.berlin/api/_config.php` → **403/404** (ok)

## IONOS-Panel (falls es danach noch falsch ist)

Unter Domain → **Verzeichnis / Stammverzeichnis** muss auf das **gleiche Heimverzeichnis** zeigen wie das rsync-Ziel (`./`), nicht in einen anderen Ordner.

## Konfiguration

`_config.php` nur auf dem Server pflegen (nicht ins Git). Vorlage: `api/_config.example.php`.