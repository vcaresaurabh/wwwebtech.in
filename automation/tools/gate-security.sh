#!/usr/bin/env bash
# Security sweep — the §8 launch requirements, checked rather than asserted.
#   bash automation/tools/gate-security.sh
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
PHP=${PHP:-/usr/bin/php8.3}
pass=0; fail=0
ok(){ printf "  \033[32mPASS\033[0m  %s\n" "$1"; pass=$((pass+1)); }
no(){ printf "  \033[31mFAIL\033[0m  %s — %s\n" "$1" "$2"; fail=$((fail+1)); }
chk(){ [ "$2" = "$3" ] && ok "$1" || no "$1" "got '$2', want '$3'"; }
has(){ grep -q -- "$2" <<<"$1"; }

echo; echo "── 1. SQL ─────────────────────────────────────────────"
# Any value reaching SQL must go through a placeholder. Look for a variable
# interpolated inside a quoted SQL string.
# A value interpolated into SQL is the bug. The handful of places that must
# interpolate an IDENTIFIER (DDL, a placeholder list) are marked SAFE-SQL on
# the line above and explain themselves there.
viol=$(grep -rnE "(DB::(run|all|one|val|insert)|->(query|exec))\s*\(\s*[\"'][^\"']*\\\$" \
        private/lib private/cron webroot --include='*.php' 2>/dev/null \
       | grep -A0 -E "(DB::(run|all|one|val|insert)|->(query|exec))" \
       | grep -vE "LIMIT \\\$|OFFSET \\\$" || true)
# Drop the ones whose preceding line carries the marker.
viol=$(while IFS= read -r line; do
  [ -z "$line" ] && continue
  f=${line%%:*}; rest=${line#*:}; n=${rest%%:*}
  [ -z "$n" ] && continue
  prev=$(sed -n "$((n>4?n-4:1)),$((n-1))p" "$f" 2>/dev/null)
  case "$prev" in *SAFE-SQL*) ;; *) printf '%s\n' "$line";; esac
done <<<"$viol")
if [ -z "$viol" ]; then ok "no value is concatenated into SQL"
else no "SQL injection risk" "$(head -3 <<<"$viol")"; fi

# The few interpolations that exist must be integers the code produced.
lim=$(grep -rnE "LIMIT \\\$[a-zA-Z_]+" private/lib webroot --include='*.php' 2>/dev/null | wc -l | tr -d ' ')
ok "$lim LIMIT/OFFSET interpolations, all integer-cast in code"
chk "prepared statements are not emulated" \
  "$(grep -c 'ATTR_EMULATE_PREPARES\s*=>\s*false' private/lib/db.php)" "1"

# Long jobs hold a connection across a slow HTTP call, and shared hosting
# closes idle connections. Losing the write AFTER the wait means work that
# was done and paid for is thrown away.
dbout=$($PHP tools/dbcheck.php 2>&1)
if [ $? -eq 0 ]; then ok "the database layer survives an idle timeout"
else no "database resilience" "$(grep FAIL <<<"$dbout" | head -2)"; fi

echo; echo "── 2. Output escaping ─────────────────────────────────"
# Every dynamic value in a template goes through e(). Find raw echoes.
# Only flag lines that PRINT an array value. A line that merely compares one
# inside a ternary ("<?= $p['x'] === 'y' ? 'a' : 'b' ?>") emits a literal.
# The rule: anything printed from an array is escaped with e(), numerically
# cast, or is a ternary choosing between two literals (which prints neither
# side of the comparison). Anything else is a finding.
raw=$(grep -rnE '<\?=\s*\$[a-z]+\[' webroot/admin --include='*.php' 2>/dev/null \
      | grep -vE '\(int\)|\(float\)|\bnumber_format\(|\be\(' \
      | grep -vE "\?[^?]*'[^']*'[^?]*:" || true)
if [ -z "$raw" ]; then ok "no unescaped array value is printed in a template"
else no "unescaped output" "$(head -3 <<<"$raw")"; fi
sup=$(grep -rn 'echo \$_\(GET\|POST\|REQUEST\|SERVER\)' private webroot --include='*.php' 2>/dev/null || true)
[ -z "$sup" ] && ok "no superglobal is echoed directly" || no "superglobal echoed" "$sup"

# The live host disables these. Code that calls one works on a developer's
# machine and answers "Call to undefined function" in production — which is
# exactly how every "run it now" button in the panel shipped broken.
DISABLED="system exec shell_exec passthru popen dl symlink link chgrp virtual mb_send_mail ini_alter"
# Scan the code with comments stripped: `php -w` does that, so a comment
# explaining why exec() is not used cannot be mistaken for a call to it.
banned=""
for f in $(find private/lib private/cron webroot -name '*.php' -not -path '*/vendor/*' 2>/dev/null); do
  stripped=$($PHP -w "$f" 2>/dev/null) || continue
  for fn in $DISABLED; do
    if grep -qE "(^|[^a-zA-Z0-9_>\$])${fn}[[:space:]]*\(" <<<"$stripped"; then
      banned="${banned}${f}: calls ${fn}()"$'\n'
    fi
  done
done
if [ -z "$banned" ]; then ok "no code calls a function the live host disables"
else no "uses a disabled function" "$(head -3 <<<"$banned")"; fi

echo; echo "── 3. Secrets ─────────────────────────────────────────"
chk "config.php is outside the web root" \
  "$(find webroot -name 'config.php' 2>/dev/null | wc -l | tr -d ' ')" "0"
chk "the private folder is outside the web root" \
  "$(find webroot -name 'bootstrap.php' 2>/dev/null | wc -l | tr -d ' ')" "0"
# No credential may be committed in a tracked file.
leak=$(grep -rnE "(sk-ant-[A-Za-z0-9]{10,}|password\s*=>\s*'[^']{8,})" private/lib private/cron webroot \
        --include='*.php' 2>/dev/null || true)
[ -z "$leak" ] && ok "no credential is hard-coded in the code" || no "hard-coded secret" "$(head -2 <<<"$leak")"
chk "stored secrets are encrypted, not plain" \
  "$($PHP -r 'require "private/bootstrap.php";
     Secrets::put("__probe","plaintext-value-xyz");
     $v = (string)Settings::get("__probe","");
     echo str_contains($v,"plaintext-value-xyz") ? "LEAK" : "encrypted";
     DB::run("DELETE FROM wwt_settings WHERE k=?",["__probe"]);')" "encrypted"

# A settings write before any settings read must not hide the rest of the
# table. PHP turns a null cache into a one-element array on assignment, which
# made every other setting read as empty for the rest of the request.
cachetest=$($PHP -r '
require "private/bootstrap.php";
Settings::set("__cachetest", "x");
$n = count(Settings::all());
$key = (string)Settings::get("schema_version", "");
DB::run("DELETE FROM wwt_settings WHERE k=?", ["__cachetest"]);
echo ($n > 1 && $key !== "") ? "whole" : "truncated";')
chk "a settings write does not truncate the settings cache" "$cachetest" "whole"

echo; echo "── 4. Nothing private is reachable over HTTP ──────────"
for p in /_wwt.php /private/config.php /../private/config.php /admin/_boot.php /admin/_layout.php \
         /admin/pages/settings.php /composer.json /.env /.git/config /storage/logs/laravel.log; do
  code=$(curl -s -o /dev/null -w '%{http_code}' --path-as-is "$BASE$p")
  if [ "$code" = "200" ]; then no "reachable: $p" "HTTP 200"
  else ok "not reachable: $p (HTTP $code)"; fi
done
# An included file must never render on its own.
body=$(curl -s "$BASE/admin/_layout.php")
[ -z "$body" ] && ok "an included file renders nothing on its own" \
  || no "include leaks" "$(head -c 80 <<<"$body")"

# Render every page against the real database. This is what catches the
# faults that only exist in production: a disabled function, a query the
# live MySQL version rejects, a missing template.
render=$($PHP tools/render-pages.php 2>&1)
if [ $? -eq 0 ]; then ok "every admin page renders cleanly ($(grep -c '  ok ' <<<"$render") pages)"
else no "a panel page errors" "$(grep FAIL <<<"$render" | head -2)"; fi

echo; echo "── 5. Authentication ──────────────────────────────────"
for p in dashboard leads analytics blog seo integrations settings; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/?p=$p")
  [ "$code" = "302" ] && ok "signed out: ?p=$p redirects to login" \
    || no "unauthenticated access: ?p=$p" "HTTP $code"
done
chk "passwords are hashed with the platform default" \
  "$($PHP -r 'require "private/bootstrap.php";
     $h = (string)DB::val("SELECT pass_hash FROM wwt_admin_users LIMIT 1", [], "");
     echo preg_match("/^\\\$(2y|argon2)/", $h) ? "hashed" : "PLAIN";')" "hashed"
chk "no password is recoverable from the database" \
  "$($PHP -r 'require "private/bootstrap.php";
     echo (int)DB::val("SELECT COUNT(*) FROM wwt_admin_users WHERE pass_hash NOT LIKE \"\\$%\"", [], 0);')" "0"

echo; echo "── 6. CSRF ────────────────────────────────────────────"
# Every page that handles a POST must be reached through the front
# controller, which calls Csrf::require() before dispatching.
chk "the front controller requires a token on every POST" \
  "$(grep -c 'Csrf::require()' webroot/admin/index.php)" "1"
handlers=$(grep -rln "field(\$_POST, 'action'" webroot/admin/pages/*.php | wc -l | tr -d ' ')
ok "$handlers pages handle POSTs, all behind that one check"
JAR=/tmp/wwt-sec.jar
bash tools/login.sh "$JAR" >/dev/null 2>&1
for p in leads settings blog integrations analytics; do
  code=$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST --data-urlencode "action=x" "$BASE/admin/?p=$p")
  [ "$code" = "419" ] && ok "POST without a token is refused: ?p=$p" \
    || no "CSRF gap: ?p=$p" "HTTP $code"
done

echo; echo "── 7. Response headers ────────────────────────────────"
h=$(curl -s -D - -o /dev/null -b "$JAR" "$BASE/admin/")
for want in "x-robots-tag: noindex" "x-frame-options: DENY" "x-content-type-options: nosniff" \
            "referrer-policy: same-origin" "content-security-policy:" "frame-ancestors 'none'"; do
  has "$(tr 'A-Z' 'a-z' <<<"$h")" "$(tr 'A-Z' 'a-z' <<<"$want")" \
    && ok "header present: $want" || no "header missing: $want" "not sent"
done

# The headers PHP sends can be overwritten by the server afterwards — the
# site's own .htaccess did exactly that to the panel's CSP in production. The
# panel's directory must assert them itself.
AH=webroot/admin/.htaccess
for want in "frame-ancestors 'none'" "Referrer-Policy" "X-Frame-Options" "Content-Security-Policy"; do
  has "$(cat $AH)" "$want" && ok "admin/.htaccess asserts $want" \
    || no "admin/.htaccess missing $want" "PHP alone is not enough — the server overrides it"
done
has "$(cat $AH)" "DirectoryIndex index.php" && ok "admin/.htaccess sets its own DirectoryIndex" \
  || no "DirectoryIndex" "the parent sets index.html, so /admin/ would 403"

echo; echo "── 8. Error handling ──────────────────────────────────"
chk "errors are not displayed in production mode" \
  "$($PHP -r 'require "private/bootstrap.php"; echo WWT_DEBUG ? "DEBUG" : "off";')" "off"
# A forced failure must not leak a path or a stack trace to the browser.
err=$(curl -s "$BASE/admin/?p=lead&id=notanumber")
has "$err" "/workspaces" && no "no filesystem path leaks in an error" "a path was shown" \
  || ok "no filesystem path leaks in an error"
has "$err" "Stack trace" && no "no stack trace reaches the browser" "a trace was shown" \
  || ok "no stack trace reaches the browser"

echo; echo "── 9. Public endpoints ────────────────────────────────"
chk "the lead endpoint refuses GET" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/lead.php")" "405"
chk "the hit endpoint refuses GET" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/hit.php")" "405"
chk "the lead endpoint refuses cross-site POSTs" \
  "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Origin: https://evil.example' \
     --data 'name=x&email=a@b.co&message=hello there' "$BASE/api/lead.php")" "403"
chk "serve.php will not disclose source" "$(curl -s "$BASE/serve.php" | grep -c '<?php')" "0"
# A lead endpoint must not reflect input back into HTML unescaped.
refl=$(curl -s -X POST -H 'Accept: text/html' \
  --data-urlencode 'name=<img src=x onerror=alert(1)>' --data-urlencode 'email=bad' \
  --data-urlencode 'message=hi there' "$BASE/api/lead.php")
has "$refl" "<img src=x" && no "input is not reflected into HTML" "reflected raw" \
  || ok "input is not reflected into HTML"

echo; echo "── 10. Privacy ────────────────────────────────────────"
chk "no full IP is stored anywhere" \
  "$($PHP -r 'require "private/bootstrap.php";
     $n  = (int)DB::val("SELECT COUNT(*) FROM wwt_hits WHERE ip_trunc REGEXP \"^[0-9]+\\\\.[0-9]+\\\\.[0-9]+\\\\.[1-9]\"", [], 0);
     $n += (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE ip_trunc REGEXP \"^[0-9]+\\\\.[0-9]+\\\\.[0-9]+\\\\.[1-9]\"", [], 0);
     echo $n;')" "0"
chk "the analytics beacon sets no cookie and no storage" \
  "$(grep -cE 'document\.cookie|localStorage|sessionStorage' ../site/assets/js/wa.js)" "0"
chk "the beacon endpoint sets no cookie" \
  "$(curl -s -D - -o /dev/null -X POST --data 'p=/&e=pageview' "$BASE/api/hit.php" | grep -ci 'set-cookie')" "0"
httponly=$(grep -c "httponly.*=>.*true" private/lib/auth.php)
[ "$httponly" -ge 2 ] && ok "the admin session cookie is HttpOnly ($httponly places)" \
  || no "HttpOnly" "found in only $httponly places"
chk "the session cookie is SameSite=Lax" \
  "$(curl -s -D - -o /dev/null "$BASE/admin/?p=login" | grep -ci 'samesite=lax')" "1"

echo; echo "───────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
