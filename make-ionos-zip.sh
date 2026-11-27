#!/bin/bash
# ZIP für IONOS-Dateimanager (ohne rsync)
set -euo pipefail
cd "$(dirname "$0")"
OUT="kiezfueralle-deploy-$(date +%Y%m%d).zip"
rm -f "$OUT"
zip -rq "$OUT" . \
  -x "*.git/*" -x ".git/*" -x "*/.DS_Store" -x ".DS_Store" \
  -x "api/_config.php" -x "kiezfueralle-deploy-*.zip"
echo "Erstellt: $OUT"
echo ""
echo "IONOS: Dateimanager → Stammverzeichnis → ZIP hochladen → Entpacken"
echo "(Bestehendes api/_config.php auf dem Server nicht überschreiben.)"