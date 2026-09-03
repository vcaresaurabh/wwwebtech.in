#!/usr/bin/env bash
# Build an upload-ready bundle of the automation layer.
#   bash automation/tools/package.sh
# Writes automation/dist/wwwebtech-automation-<date>.zip plus an unzipped
# copy, laid out exactly as it has to sit on the server.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
STAMP=$(date -u +%Y%m%d)
OUT="dist/wwwebtech-automation-$STAMP"

rm -rf "$OUT"; mkdir -p "$OUT/wwt_private" "$OUT/public_html"

echo "  private/  -> wwt_private/"
# Everything the panel needs at runtime. Deliberately NOT config.php: the
# server's own copy holds live passwords and must never be overwritten by a
# bundle, nor shipped from a developer's machine.
for item in bootstrap.php schema.sql config.sample.php lib cron data lp templates vendor; do
  [ -e "private/$item" ] && cp -a "private/$item" "$OUT/wwt_private/"
done
mkdir -p "$OUT/wwt_private/logs" "$OUT/wwt_private/posts"
cat > "$OUT/wwt_private/logs/.gitkeep" <<'X'
X
cp -a "$OUT/wwt_private/logs/.gitkeep" "$OUT/wwt_private/posts/.gitkeep"

echo "  webroot/  -> public_html/"
cp -a webroot/. "$OUT/public_html/"

echo "  docs"
cp -a deploy/*.md "$OUT/"

# A bundle that carries a real config or a real key is a security incident
# waiting to happen. Refuse to build one.
if [ -f "$OUT/wwt_private/config.php" ]; then
  echo "REFUSING: config.php ended up in the bundle" >&2; exit 1
fi
if grep -rlq "sk-ant-[A-Za-z0-9]\{10,\}" "$OUT" 2>/dev/null; then
  echo "REFUSING: an API key is present in the bundle" >&2; exit 1
fi
if grep -rl "devpass" "$OUT" 2>/dev/null | grep -q .; then
  echo "REFUSING: a development password is present in the bundle" >&2; exit 1
fi

( cd dist && zip -qr "wwwebtech-automation-$STAMP.zip" "wwwebtech-automation-$STAMP" )

echo
echo "  bundle: automation/$OUT"
echo "  zip:    automation/dist/wwwebtech-automation-$STAMP.zip ($(du -h "dist/wwwebtech-automation-$STAMP.zip" | cut -f1))"
echo
echo "  Upload wwt_private/  -> /home/USER/wwt_private"
echo "  Upload public_html/  -> /home/USER/domains/wwwebtech.in/public_html"
echo "  Then follow DEPLOY.md."
