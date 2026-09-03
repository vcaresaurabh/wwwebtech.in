#!/usr/bin/env bash
# Start (or restart) the local dev services for the automation layer.
#   bash automation/tools/dev.sh
# PHP dev server on :8088 serving webroot/, and the SMTP sink on :2525.
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
PHP=${PHP:-/usr/bin/php8.3}   # the PATH php in this container is a broken build
mkdir -p .dev/mail .dev/sessions

# ── A web root that looks like the real one ───────────────────────────
# On the server, public_html holds BOTH the static site and the automation
# files. Serving them from separate folders locally would test a layout that
# never ships — and the publisher, which edits index.html and sitemap.xml in
# place, would have nothing to edit.
#
# site/ is COPIED, not linked: publishing rewrites those files, and the
# deploy artifact must not be modified by a test run.
DEVROOT="$PWD/.dev/webroot"
# A devroot older than the last site build serves yesterday's HTML, and the
# gates that diff it against site/ then report a failure that looks like a
# real regression. Rebuild rather than make someone work that out again.
if [ -f "$DEVROOT/index.html" ] && [ ../site/index.html -nt "$DEVROOT/index.html" ]; then
  echo "  site/ is newer than the dev web root — rebuilding"
  set -- --rebuild
fi
if [ "${1:-}" = "--rebuild" ] || [ ! -f "$DEVROOT/index.html" ]; then
  echo "  building $DEVROOT from site/ + webroot/"
  rm -rf "$DEVROOT"; mkdir -p "$DEVROOT"
  cp -a ../site/. "$DEVROOT/"
  for f in admin api lp tools serve.php _wwt.php; do
    ln -sfn "$PWD/webroot/$f" "$DEVROOT/$f"
  done
fi

pkill -f "127.0.0.1:8088" 2>/dev/null
pkill -f "tools/smtp-sink.py" 2>/dev/null
sleep 0.4

# PHP's built-in server is single-threaded, and Lighthouse opens several
# connections at once — the server would stall and then die mid-audit, which
# looked like the page scoring zero. Workers make it survive a real page load.
export PHP_CLI_SERVER_WORKERS=8
setsid "$PHP" -d session.save_path="$PWD/.dev/sessions" -S 127.0.0.1:8088 -t "$DEVROOT" \
  > .dev/php.log 2>&1 < /dev/null &
setsid python3 tools/smtp-sink.py > .dev/smtp.log 2>&1 < /dev/null &
sleep 1.2

printf "  admin  http://127.0.0.1:8088/admin/   -> HTTP %s\n" \
  "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8088/admin/)"
printf "  smtp   127.0.0.1:2525                 -> %s\n" \
  "$(pgrep -f 'tools/smtp-sink.py' >/dev/null && echo up || echo DOWN)"
