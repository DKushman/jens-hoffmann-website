# Deployment (IONOS) – kiezfueralle.berlin

## Warum die Seite „alt“ wirkt

`git push` aktualisiert nur **GitHub Pages** (Vorschau), nicht **kiezfueralle.berlin**.

Die Live-Domain läuft auf **IONOS**. Dort liegt der Stand oft noch mit **absoluten Pfaden** (`/assets/…`) – das ist eine **ältere Kopie im Webroot**.

Wenn rsync in den Unterordner  
`jens-hoffmann-website-richtig nach meeting/`  
geht, **ändert sich die öffentliche Seite nicht** (dieser Pfad ist nicht die Domain-Wurzel).

## Schnell: ZIP im IONOS-Panel (wenn rsync hängt / 404)

```bash
./make-ionos-zip.sh
```

Erzeugt `kiezfueralle-deploy-YYYYMMDD.zip` im Projektordner.

1. **IONOS** → Webspace → **Dateimanager**
2. Ordner = **Stammverzeichnis** von kiezfueralle.berlin (da wo früher `index.html` lag)
3. ZIP hochladen → **Entpacken**
4. **`api/_config.php` auf dem Server nicht löschen** (Steuerungsdatei nur auf IONOS)

---

## „Forbidden“ / 403 / leere Seite

Häufige Ursachen nach rsync:

1. **`index.html` nicht im Stammverzeichnis** → Deploy bricht ab oder Zielordner falsch  
2. **Dateirechte** → Deploy setzt jetzt `--chmod` (Ordner 755, Dateien 644)  
3. **Stammverzeichnis = Unterordner** → `./deploy-ionos.sh` lädt **Webroot und Unterordner** hoch

Im IONOS-Dateimanager: Liegt `index.html` **im Stammverzeichnis**, das das Panel für kiezfueralle.berlin zeigt?

## Hochladen (richtig)

Im Terminal (IONOS-Passwort **zweimal** eingeben – zwei Ziele):

```bash
cd "/Users/davidjoelchiosea/Downloads/jens-hoffmann-website-richtig nach meeting"
./deploy-ionos.sh
```

**Warten**, bis im Terminal **„✓ Upload fertig“** erscheint (sonst fehlen Dateien auf dem Server).

Bei **404** auf der Startseite: Deploy **sofort nochmal** ausführen. Im IONOS-Dateimanager prüfen, ob im **Stammverzeichnis** `index.html` liegt.

`api/_config.php` wird **nicht** überschrieben (bleibt die Server-Konfiguration).

## Nach dem Upload prüfen

1. **Inkognito:** https://kiezfueralle.berlin/parlamentsreporte.html → **„3 Ausgaben“**, neue PDF vom **26.11.2026**
2. https://kiezfueralle.berlin/kontakt.html → Testnachricht
3. `https://kiezfueralle.berlin/api/_config.php` → **403/404** (ok)

## IONOS-Panel (falls es danach noch falsch ist)

Unter Domain → **Verzeichnis / Stammverzeichnis** muss auf das **gleiche Heimverzeichnis** zeigen wie das rsync-Ziel (`./`), nicht in einen anderen Ordner.

## Konfiguration

`_config.php` nur auf dem Server pflegen (nicht ins Git). Vorlage: `api/_config.example.php`.

## Diagnose: Live noch alt?

Im Terminal:

```bash
curl -sI https://kiezfueralle.berlin/parlamentsreporte.html | grep -i last-modified
```

Steht dort z. B. **23 Nov 2026**, wurde das **Webroot** seit dem nicht überschrieben (Upload lief ins falsche Ziel oder ist fehlgeschlagen).

Lokal ist `parlamentsreporte.html` größer und enthält **„3 Ausgaben“** – nach erfolgreichem Deploy weicht Größe und Inhalt ab.

**Deploy-Check nach Upload:** https://kiezfueralle.berlin/deploy-version.txt (Commit-Zeit, nur wenn Action oder rsync lief).

## Automatisch per GitHub (empfohlen)

1. GitHub → Repo **Settings** → **Secrets and variables** → **Actions** → **New repository secret**
2. Name: `IONOS_SFTP_PASSWORD` (SFTP-Passwort des Users `su1205313`)
3. Jeder **Push auf `main`** startet Workflow **Deploy IONOS** (SFTP ins Webroot)

Ohne Secret schlägt der Workflow fehl – dann weiter `./deploy-ionos.sh` lokal nutzen.