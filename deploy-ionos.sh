#!/bin/bash
# Komplette Website nach IONOS (kiezfueralle.berlin)
set -euo pipefail
cd "$(dirname "$0")"

REMOTE="su1205313@access-5021112032.webspace-host.com"

echo "=== IONOS: gesamte Website hochladen ==="
echo "Passwort für su1205313 eingeben."
echo ""

rsync -avz --progress \
  --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
  --exclude '.DS_Store' \
  --exclude '.git' \
  --exclude 'api/_config.php' \
  --exclude '*.zip' \
  ./ "${REMOTE}:./"

echo ""
echo "=== Prüfung (nach ~10 Sekunden) ==="
sleep 10
if curl -sf -o /dev/null -w "%{http_code}" "https://kiezfueralle.berlin/index.html" | grep -q 200; then
  echo "✓ https://kiezfueralle.berlin/ erreichbar"
else
  echo "⚠ Startseite noch nicht OK – im IONOS-Dateimanager prüfen:"
  echo "  Liegt index.html im Stammverzeichnis der Domain?"
  echo "  Alternative: ./make-ionos-zip.sh → ZIP im Panel entpacken"
fi