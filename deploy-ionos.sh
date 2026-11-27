#!/bin/bash
# Live-Deploy für kiezfueralle.berlin (IONOS)
#
# WICHTIG: Die Domain zeigt auf das SFTP-Heimverzeichnis (Webroot), NICHT auf den
# Unterordner „jens-hoffmann-website-richtig nach meeting“. Rsync dorthin aktualisiert
# die Live-Seite nicht (nur ein privates Verzeichnis auf dem Server).
#
# Nach erfolgreichem Lauf sollte parlamentsreporte.html „3 Ausgaben“ zeigen.
set -euo pipefail
cd "$(dirname "$0")"

REMOTE="su1205313@access-5021112032.webspace-host.com"
# Webroot = Home-Verzeichnis (./)
REMOTE_DIR="./"

echo "→ rsync ins IONOS-Webroot (Passwort für su1205313)…"
echo "  Ziel: ${REMOTE}:${REMOTE_DIR}"
rsync -avz --delete \
  --exclude '.DS_Store' \
  --exclude '.git' \
  --exclude 'api/_config.php' \
  ./ "${REMOTE}:${REMOTE_DIR}"

echo ""
echo "✓ Upload fertig. Bitte im Browser prüfen (Inkognito):"
echo "  https://kiezfueralle.berlin/parlamentsreporte.html"
echo "  Erwartung: „3 Ausgaben“, Datum 26.11.2026"