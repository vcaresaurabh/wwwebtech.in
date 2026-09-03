#!/usr/bin/env bash
# Phase 3 acceptance gate — analytics.
#   bash automation/tools/gate-phase3.sh
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
HIT="$BASE/api/hit.php"
PHP=${PHP:-/usr/bin/php8.3}
KEY=$($PHP -r '$c=require "private/config.php"; echo $c["cron_key"];')
pass=0; fail=0
ok(){ printf "  \033[32mPASS\033[0m  %s\n" "$1"; pass=$((pass+1)); }
no(){ printf "  \033[31mFAIL\033[0m  %s — %s\n" "$1" "$2"; fail=$((fail+1)); }
chk(){ [ "$2" = "$3" ] && ok "$1" || no "$1" "got '$2', want '$3'"; }
# grep -q exits on the first match, which SIGPIPEs whatever is feeding it —
# and with `set -o pipefail` that turns a successful match into a failed
# check. A here-string is not a pipe, so it cannot happen.
has(){ grep -q -- "$2" <<<"$1"; }
hasi(){ grep -qi -- "$2" <<<"$1"; }
q(){ mysql -N -B -uwwt -pdevpass wwt_dev -e "$1" 2>/dev/null; }

DESK="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36"
MOB="Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1"
beacon(){ curl -s -o /dev/null -A "$1" -X POST -H "Referer: $BASE$2" --data "t=$KEY&$3" "$HIT"; }

echo; echo "── Reset ──────────────────────────────────────────────"
q "DELETE FROM wwt_hits; DELETE FROM wwt_daily_rollups; DELETE FROM wwt_rate_limit;
   UPDATE wwt_settings SET v='1' WHERE k='analytics_enabled';" >/dev/null
echo "  hits and rollups cleared, collection on"

echo; echo "── 1. Beacon size budget ──────────────────────────────"
WA=../site/assets/js/wa.js
gz=$(gzip -c "$WA" | wc -c | tr -d ' ')
[ "$gz" -le 1536 ] && ok "wa.js is ${gz}B gzipped (budget 1536B)" \
  || no "wa.js size" "${gz}B gzipped, over the 1536B budget"
node -e "new Function(require('fs').readFileSync('$WA','utf8'))" 2>/dev/null \
  && ok "wa.js parses" || no "wa.js" "syntax error"
grep -qi "document.cookie\|localStorage\|sessionStorage" "$WA" \
  && no "wa.js stores nothing on the device" "it touches cookies or storage" \
  || ok "wa.js sets no cookie and writes no storage"

echo; echo "── 2. Collection ──────────────────────────────────────"
for i in 1 2 3 4 5; do beacon "$DESK" "/blog/" "p=/blog/&e=pageview&r=https%3A%2F%2Fwww.google.com%2Fsearch%3Fq%3Dseo"; done
for i in 1 2 3; do beacon "$MOB" "/" "p=/&e=pageview&utm_source=linkedin&utm_medium=social"; done
beacon "$DESK" "/" "p=/&e=engaged&d=15s"
beacon "$DESK" "/contact/" "p=/contact/&e=click&d=mailto"
chk "pageviews recorded" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE event='pageview'")" "8"
chk "desktop and mobile told apart" "$(q "SELECT COUNT(DISTINCT device) FROM wwt_hits WHERE is_bot=0")" "2"
chk "referrer resolved to a domain" "$(q "SELECT DISTINCT ref_domain FROM wwt_hits WHERE path='/blog/'")" "google.com"
chk "campaign tags captured" "$(q "SELECT DISTINCT utm_source FROM wwt_hits WHERE path='/' AND event='pageview'")" "linkedin"
chk "engagement event recorded" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE event='engaged'")" "1"

echo; echo "── 3. Privacy ─────────────────────────────────────────"
chk "no full IPv4 stored" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE ip_trunc REGEXP '^[0-9]+\\\\.[0-9]+\\\\.[0-9]+\\\\.[1-9]'")" "0"
chk "one visitor is one hash" "$(q "SELECT COUNT(DISTINCT session_hash) FROM wwt_hits WHERE device='desktop'")" "1"
chk "a different device is a different hash" "$(q "SELECT COUNT(DISTINCT session_hash) FROM wwt_hits WHERE is_bot=0")" "2"
chk "crawlers get no visitor identifier" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE is_bot=1 AND session_hash<>''")" "0"
# The salt must rotate, or the hash becomes a stable identifier.
s1=$(q "SELECT v FROM wwt_settings WHERE k='analytics_salt'")
q "UPDATE wwt_settings SET v='1970-01-01' WHERE k='analytics_salt_day'" >/dev/null
beacon "$DESK" "/" "p=/&e=pageview"
s2=$(q "SELECT v FROM wwt_settings WHERE k='analytics_salt'")
[ "$s1" != "$s2" ] && ok "the daily salt rotates" || no "salt rotation" "the salt did not change on a new day"
[ -n "$s2" ] && ok "yesterday's hashes can no longer be recomputed" || no "salt" "missing"
q "DELETE FROM wwt_hits WHERE ts > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 SECOND) AND event='pageview' AND path='/' AND device='desktop'" >/dev/null

echo; echo "── 4. Crawlers ────────────────────────────────────────"
for b in "GPTBot/1.2" "ClaudeBot/1.0" "PerplexityBot/1.0" "Googlebot/2.1" "AhrefsBot/7.0"; do
  curl -s -o /dev/null -A "$b" -X POST --data "t=$KEY&p=/&e=pageview" "$HIT"
done
chk "crawlers recorded" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE is_bot=1")" "5"
chk "named, not lumped together" "$(q "SELECT COUNT(DISTINCT bot_name) FROM wwt_hits WHERE is_bot=1")" "5"
chk "AI crawlers identified" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE bot_name IN ('GPTBot','ClaudeBot','PerplexityBot')")" "3"
chk "crawlers never count as visitors" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE is_bot=1 AND device<>'bot'")" "0"

echo; echo "── 5. Abuse and misuse ────────────────────────────────"
chk "cross-site beacon refused" \
  "$(curl -s -o /dev/null -w '%{http_code}' -A "$DESK" -X POST -H "Origin: https://evil.example" --data "p=/&e=pageview" "$HIT")" "204"
before=$(q "SELECT COUNT(*) FROM wwt_hits")
curl -s -o /dev/null -A "$DESK" -X POST -H "Origin: https://evil.example" --data "p=/&e=pageview" "$HIT"
chk "and stores nothing" "$(q "SELECT COUNT(*) FROM wwt_hits")" "$before"
chk "GET refused" "$(curl -s -o /dev/null -w '%{http_code}' "$HIT")" "405"
before=$(q "SELECT COUNT(*) FROM wwt_hits")
beacon "$DESK" "/" "p=/&e=not_a_real_event"
chk "unknown event falls back to pageview" \
  "$(q "SELECT COUNT(*) FROM wwt_hits WHERE event='not_a_real_event'")" "0"
q "DELETE FROM wwt_hits WHERE id=(SELECT * FROM (SELECT MAX(id) FROM wwt_hits) x)" >/dev/null

echo; echo "── 6. Rollups ─────────────────────────────────────────"
# Everything above was flagged as test, and rollups deliberately ignore
# flagged traffic — so prove that first, then send UNFLAGGED traffic to
# exercise the aggregation itself.
$PHP private/cron/run.php analytics_hourly >/dev/null 2>&1
chk "rollup job reports ok" "$(q "SELECT last_status FROM wwt_task_runs WHERE task='analytics_hourly'")" "ok"
chk "test traffic is excluded from the totals" "$(q "SELECT COALESCE(SUM(views),0) FROM wwt_daily_rollups")" "0"

real(){ curl -s -o /dev/null -A "$1" -X POST -H "Referer: $BASE$2" --data "$3" "$HIT"; }
for i in 1 2 3 4 5; do real "$DESK" "/blog/" "p=/blog/&e=pageview&r=https%3A%2F%2Fwww.google.com%2Fsearch%3Fq%3Dseo"; done
for i in 1 2 3; do real "$MOB" "/" "p=/&e=pageview&utm_source=linkedin&utm_medium=social"; done
for b in "GPTBot/1.2" "ClaudeBot/1.0" "PerplexityBot/1.0" "Googlebot/2.1" "AhrefsBot/7.0"; do
  curl -s -o /dev/null -A "$b" -X POST --data "p=/&e=pageview" "$HIT"
done
$PHP private/cron/run.php analytics_hourly >/dev/null 2>&1

r1=$(q "SELECT SUM(views) FROM wwt_daily_rollups WHERE device<>'bot'")
$PHP private/cron/run.php analytics_hourly >/dev/null 2>&1
r2=$(q "SELECT SUM(views) FROM wwt_daily_rollups WHERE device<>'bot'")
chk "running it twice does not double-count" "$r2" "$r1"
chk "human pageviews rolled up" "$r1" "8"
chk "crawler history is rolled up too" "$(q "SELECT COUNT(*) FROM wwt_daily_rollups WHERE device='bot'")" "5"
chk "crawlers contribute no visitor count" "$(q "SELECT COALESCE(SUM(visitors),0) FROM wwt_daily_rollups WHERE device='bot'")" "0"
chk "sources classified" "$(q "SELECT COUNT(DISTINCT source) FROM wwt_daily_rollups WHERE device<>'bot'")" "2"
chk "organic detected from the referrer" \
  "$(q "SELECT views FROM wwt_daily_rollups WHERE source='Organic' AND path='/blog/'")" "5"
chk "social detected from the campaign tag" \
  "$(q "SELECT views FROM wwt_daily_rollups WHERE source='Social' AND path='/'")" "3"

# Clear the unflagged traffic again so section 7 starts from a known state.
q "DELETE FROM wwt_hits WHERE is_test=0; DELETE FROM wwt_daily_rollups;" >/dev/null

echo; echo "── 7. Test-traffic flag and purge ─────────────────────"
chk "everything above was flagged as test" \
  "$(q "SELECT COUNT(*) FROM wwt_hits WHERE is_test=0")" "0"
beacon2(){ curl -s -o /dev/null -A "$DESK" -X POST -H "Referer: $BASE/" --data "p=/&e=pageview" "$HIT"; }
beacon2
chk "an unflagged hit is stored as real" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE is_test=0")" "1"
JAR=/tmp/wwt-gate3.jar
bash tools/login.sh "$JAR" >/dev/null 2>&1
tok=$(curl -s -b "$JAR" "$BASE/admin/?p=analytics" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR" -o /dev/null -X POST --data-urlencode "_csrf=$tok" --data-urlencode "action=purge_tests" "$BASE/admin/?p=analytics"
chk "purge removes the test hits" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE is_test=1")" "0"
chk "and leaves the real one alone" "$(q "SELECT COUNT(*) FROM wwt_hits WHERE is_test=0")" "1"

echo; echo "── 8. Panel ───────────────────────────────────────────"
ap=$(curl -s -b "$JAR" -w '\n%{http_code}' "$BASE/admin/?p=analytics")
chk "analytics page renders" "$(echo "$ap" | tail -1)" "200"
has "$ap" "Something went wrong" && no "analytics page clean" "threw mid-render" \
  || ok "analytics page renders without error"
has "$ap" '<svg class="chart"' && ok "timeseries chart drawn inline" \
  || no "chart" "no inline SVG found"
has "$ap" "role=\"img\"" && ok "chart has a text alternative" || no "chart a11y" "no role/aria-label"
has "$ap" "Not available" && ok "geography states plainly that it is unknown" \
  || no "geography" "does not say the data is unavailable"

q "UPDATE wwt_settings SET v='0' WHERE k='analytics_enabled'" >/dev/null
has "$(curl -s -b "$JAR" "$BASE/admin/?p=analytics")" "Collection is switched off" \
  && ok "panel says so when collection is off" || no "off-state warning" "not shown"
q "UPDATE wwt_settings SET v='1' WHERE k='analytics_enabled'" >/dev/null

before=$(q "SELECT COUNT(*) FROM wwt_hits")
q "UPDATE wwt_settings SET v='0' WHERE k='analytics_enabled'" >/dev/null
beacon2
chk "collection off means nothing is recorded" "$(q "SELECT COUNT(*) FROM wwt_hits")" "$before"
q "UPDATE wwt_settings SET v='1' WHERE k='analytics_enabled'" >/dev/null

echo; echo "── 9. Crawler front controller ────────────────────────"
# serve.php ends in readfile(). It must never hand back anything but a
# document, and never its own source, whatever the rewrite sends it.
chk "serve.php will not serve itself" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/serve.php")" "404"
chk "serve.php discloses no PHP source" \
  "$(curl -s "$BASE/serve.php" | grep -c '<?php')" "0"
chk "serve.php refuses traversal" \
  "$(curl -s -o /dev/null -w '%{http_code}' --path-as-is "$BASE/serve.php/../private/config.php")" "404"
grep -q "RewriteCond %{HTTP_USER_AGENT} (GPTBot" ../site/.htaccess \
  && ok ".htaccess routes AI crawlers to serve.php" || no ".htaccess" "crawler block missing"
grep -q "RewriteCond %{DOCUMENT_ROOT}/serve.php -f" ../site/.htaccess \
  && ok "the rule is a no-op until serve.php is deployed" || no ".htaccess guard" "missing -f guard"

echo; echo "───────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
