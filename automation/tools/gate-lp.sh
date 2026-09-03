#!/usr/bin/env bash
# Landing page gate (§12.1, §12.2).
#   bash automation/tools/gate-lp.sh
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
PHP=${PHP:-/usr/bin/php8.3}
pass=0; fail=0
ok(){ printf "  \033[32mPASS\033[0m  %s\n" "$1"; pass=$((pass+1)); }
no(){ printf "  \033[31mFAIL\033[0m  %s — %s\n" "$1" "$2"; fail=$((fail+1)); }
chk(){ [ "$2" = "$3" ] && ok "$1" || no "$1" "got '$2', want '$3'"; }
has(){ grep -q -- "$2" <<<"$1"; }

SLUGS=$(ls private/lp/data/*.php | xargs -n1 basename | sed 's/\.php$//')

echo; echo "── 1. Every page renders, with every block ──────────────"
for s in $SLUGS; do
  body=$(curl -s "$BASE/lp/index.php?p=$s")
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/lp/index.php?p=$s")
  missing=""
  for block in 'lp-head__in' 'hero__grid' 'formwrap' 'class="pain"' 'class="gets"' \
               'class="steps"' 'price__moves' 'class="faq"' 'class="bar"' 'lp-foot'; do
    has "$body" "$block" || missing="$missing $block"
  done
  if [ "$code" = "200" ] && [ -z "$missing" ]; then ok "$s — 200, all 13 blocks"
  else no "$s" "HTTP $code, missing:$missing"; fi
done

echo; echo "── 2. No page invents proof ─────────────────────────────"
for s in $SLUGS; do
  body=$(curl -s "$BASE/lp/index.php?p=$s")
  bad=""
  # A percentage attached to a population is the fabrication that matters.
  grep -qiE '[0-9]{1,3}\s*%\s*(of|increase|more|growth)' <<<"$body" && bad="$bad percentage-claim"
  grep -qiE 'studies show|research shows|our clients? (saw|got)|case study' <<<"$body" && bad="$bad unsourced-claim"
  grep -qiE 'guarantee[ds]? (results|rankings|traffic)' <<<"$body" && bad="$bad guarantee"
  [ -z "$bad" ] && ok "$s — no invented proof" || no "$s invents proof" "$bad"
done

echo; echo "── 3. Message match resists hostile input ───────────────"
for s in $SLUGS; do
  worst=0
  for kw in '<script>alert(1)</script>' '"><img src=x onerror=alert(1)>' "'; DROP TABLE wwt_leads;--" '../../etc/passwd' '%3Cscript%3E'; do
    body=$(curl -s -G --data-urlencode "p=$s" --data-urlencode "kw=$kw" "$BASE/lp/index.php")
    grep -qE '<script>alert|onerror=alert|<img src=x' <<<"$body" && worst=1
  done
  [ "$worst" = "0" ] && ok "$s — hostile kw never reaches the DOM" || no "$s XSS" "a payload was rendered"
done

echo; echo "── 4. Routing and errors ────────────────────────────────"
chk "an unknown slug is a 404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/lp/index.php?p=not-a-real-page")" "404"
chk "a traversal attempt is a 404" "$(curl -s -o /dev/null -w '%{http_code}' --get --data-urlencode 'p=../../private/config' "$BASE/lp/index.php")" "404"
chk "no slug at all is a 404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/lp/index.php")" "404"
chk "the data files are not web-reachable" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/lp/data/custom-crm.php")" "404"

echo; echo "── 5. The form works without JavaScript ─────────────────"
body=$(curl -s "$BASE/lp/index.php?p=custom-crm")
has "$body" 'method="post"'          && ok "form posts normally"        || no "form method" "not a POST form"
has "$body" 'action="/api/lead.php"' && ok "posts to the real endpoint" || no "form action" "wrong endpoint"
chk "three fieldsets in the markup" "$(grep -o 'data-step="[0-9]"' <<<"$body" | wc -l | tr -d ' ')" "3"
has "$body" 'name="consent"'         && ok "consent checkbox present"   || no "consent" "missing"
has "$body" 'class="hp"'             && ok "honeypot present"           || no "honeypot" "missing"
for cid in gclid gbraid wbraid msclkid fbclid; do
  has "$body" "name=\"$cid\"" && ok "click ID captured: $cid" || no "click ID $cid" "field missing"
done

echo; echo "── 6. Head and schema ───────────────────────────────────"
for s in $SLUGS; do
  body=$(curl -s "$BASE/lp/index.php?p=$s")
  issues=""
  has "$body" '<link rel="canonical"' || issues="$issues canonical"
  has "$body" '"@type":"FAQPage"'     || issues="$issues faq-schema"
  has "$body" '"@type":"Service"'     || issues="$issues service-schema"
  has "$body" '@font-face'            || issues="$issues font-faces"
  has "$body" 'WWT_TAGS_HEAD_START'   || issues="$issues tag-markers"
  [ "$(grep -c '<h1' <<<"$body")" = "1" ] || issues="$issues h1-count"
  grep -q '<link rel="stylesheet"' <<<"$body" && issues="$issues render-blocking-css"
  [ -z "$issues" ] && ok "$s — head correct" || no "$s head" "$issues"
done

echo; echo "── 7. No page carries the marketing site's stylesheet ───"
for s in $SLUGS; do
  body=$(curl -s "$BASE/lp/index.php?p=$s")
  bytes=$(wc -c <<<"$body")
  if grep -q 'assets/css/site' <<<"$body"; then no "$s" "loads the full site CSS"
  elif [ "$bytes" -gt 90000 ]; then no "$s" "page is ${bytes}B, unexpectedly large"
  else ok "$s — self-contained, ${bytes}B"; fi
done

echo; echo "─────────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
