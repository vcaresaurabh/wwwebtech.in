#!/usr/bin/env bash
# Phase 6 acceptance gate — the tag manager.
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
PHP=${PHP:-/usr/bin/php8.3}
pass=0; fail=0
ok(){ printf "  \033[32mPASS\033[0m  %s\n" "$1"; pass=$((pass+1)); }
no(){ printf "  \033[31mFAIL\033[0m  %s — %s\n" "$1" "$2"; fail=$((fail+1)); }
chk(){ [ "$2" = "$3" ] && ok "$1" || no "$1" "got '$2', want '$3'"; }
has(){ grep -q -- "$2" <<<"$1"; }
q(){ mysql -N -B -uwwt -pdevpass wwt_dev -e "$1" 2>/dev/null; }
ROOT=$($PHP -r 'require "private/bootstrap.php"; echo Blog::webroot();')

echo; echo "── 1. Markers are in every page ───────────────────────"
total=$(find "$ROOT" -name '*.html' -not -path '*/admin/*' -not -path '*/api/*' | wc -l | tr -d ' ')
withm=$(grep -rl "WWT_TAGS_HEAD_START" "$ROOT" --include='*.html' 2>/dev/null | wc -l | tr -d ' ')
chk "all $total pages carry the head marker" "$withm" "$total"
withb=$(grep -rl "WWT_TAGS_BODY_START" "$ROOT" --include='*.html' 2>/dev/null | wc -l | tr -d ' ')
chk "all $total pages carry the body marker" "$withb" "$total"

echo; echo "── 2. IDs are validated, not trusted ──────────────────"
$PHP -r '
require "private/bootstrap.php";
$cases = [
  ["ga4","G-ABC1234567",true], ["ga4","UA-12345-1",false], ["ga4","<script>x</script>",false],
  ["gtm","GTM-ABC1234",true],  ["gtm","GTM",false],
  ["ads","AW-123456789",true], ["ads","AW-abc",false],
  ["meta_pixel","1234567890123456",true], ["meta_pixel","not-a-number",false],
  ["clarity","abcdefghij",true], ["clarity","!!",false],
  ["gsc","abcdefghij0123456789_-",true], ["gsc","short",false],
];
foreach ($cases as [$k,$v,$want]) {
  try { Tags::set($k,$v); $got = true; } catch (Throwable $e) { $got = false; }
  printf("%s:%s=%s\n", $k, $v, $got === $want ? "ok" : "WRONG");
}
foreach (array_keys(Tags::DEFS) as $k) Settings::set("tag_".$k, "");' > /tmp/tagval.txt
bad=$(grep -c "WRONG" /tmp/tagval.txt || true)
chk "every ID shape check behaves" "$bad" "0"
[ "$bad" -gt 0 ] && grep "WRONG" /tmp/tagval.txt | sed 's/^/        /'

echo; echo "── 3. Snippets are the real ones ──────────────────────"
$PHP -r '
require "private/bootstrap.php";
Tags::set("ga4","G-TESTGATE1"); Settings::set("tag_ga4_on","1");
Tags::set("gtm","GTM-TESTG1");  Settings::set("tag_gtm_on","1");
Tags::set("gsc","verification-token-abcdefghij123456"); Settings::set("tag_gsc_on","1");
echo Tags::headHtml(), "\n---BODY---\n", Tags::bodyHtml();' > /tmp/snip.txt
snip=$(cat /tmp/snip.txt)
has "$snip" "googletagmanager.com/gtag/js?id=G-TESTGATE1" && ok "GA4 uses Google's own loader" || no "GA4 snippet" "wrong"
has "$snip" "gtag('config','G-TESTGATE1')"                && ok "GA4 is configured with the pasted ID" || no "GA4 config" "wrong"
has "$snip" "googletagmanager.com/gtm.js"                 && ok "GTM uses the official snippet" || no "GTM snippet" "wrong"
has "$snip" 'name="google-site-verification"'             && ok "Search Console is a meta tag only" || no "GSC" "wrong"
has "$snip" "ns.html?id=GTM-TESTG1"                       && ok "GTM's noscript iframe goes in the body" || no "GTM noscript" "missing"
# Every script tag waits for the page to draw. Measured on the live site,
# tags in <head> were the whole difference between LCP 3.6s and 1.4s.
has "$snip" "wwtDefer(function()"                         && ok "script tags are deferred until after paint" || no "deferred" "a tag loads before paint"
has "$snip" "addEventListener('load'"                     && ok "the loader waits for the load event" || no "loader" "missing"
[ "$(printf '%s' "$snip" | grep -c 'wwtDefer=function')" = "1" ] && ok "the loader is emitted exactly once" || no "loader count" "not exactly once"
# GA4 and Ads on the same page share one gtag.js download.
$PHP -r '
require "private/bootstrap.php";
Tags::set("ads","AW-123456789"); Settings::set("tag_ads_on","1");
echo Tags::headHtml();
Settings::set("tag_ads_on","0");' > /tmp/snip2.txt
[ "$(grep -c 'gtag/js?id=' /tmp/snip2.txt)" = "1" ] && ok "GA4 + Ads fetch gtag.js once, not twice" || no "gtag dedupe" "$(grep -c 'gtag/js?id=' /tmp/snip2.txt) downloads"
has "$(cat /tmp/snip2.txt)" "gtag('config','AW-123456789')" && ok "and Ads is still configured" || no "ads config" "missing"
# An ID is never interpolated raw into a script string.
$PHP -r '
require "private/bootstrap.php";
Settings::set("tag_ga4","G-X\x27);alert(1);//"); Settings::set("tag_ga4_on","1");
echo Tags::snippet("ga4");' > /tmp/xss.txt
# The breakout only works if the quote reaches the output UNESCAPED, so look
# for the closing-quote sequence itself rather than the payload after it.
has "$(cat /tmp/xss.txt)" "'G-X');" && no "an ID cannot break out of the script" "an unescaped quote reached the output" \
  || ok "an ID cannot break out of the script"
has "$(cat /tmp/xss.txt)" "\\\\'" && ok "the quote is escaped rather than dropped" \
  || no "quote escaping" "the quote was not escaped"
$PHP -r 'require "private/bootstrap.php"; Tags::set("ga4","G-TESTGATE1");'

echo; echo "── 4. Applying and removing ───────────────────────────"
$PHP -r 'require "private/bootstrap.php"; $r = Tags::apply(); echo json_encode($r);' > /tmp/apply.txt
n=$($PHP -r 'echo json_decode(file_get_contents("/tmp/apply.txt"),true)["written"];')
[ "$n" -ge 10 ] && ok "$n pages written" || no "apply" "only $n pages written"
chk "no page was missing its markers" \
  "$($PHP -r 'echo count(json_decode(file_get_contents("/tmp/apply.txt"),true)["missing"]);')" "0"
once=$(curl -s "$BASE/" | grep -c 'G-TESTGATE1')
[ "$once" -ge 1 ] && ok "the tag is live on the homepage" || no "homepage tag" "not found"
[ "$(curl -s "$BASE/contact/" | grep -c 'G-TESTGATE1')" -ge 1 ] && ok "and on an inner page" || no "inner page" "not found"
[ "$(curl -s "$BASE/blog/" | grep -c 'G-TESTGATE1')" -ge 1 ] && ok "and on a blog page" || no "blog page" "not found"
[ "$(curl -s "$BASE/lp/index.php?p=custom-crm" | grep -c 'G-TESTGATE1')" -ge 1 ] && ok "and on a landing page (rendered live)" || no "landing page" "the PHP pages never got the tags"

# Applying twice must not stack copies.
$PHP -r 'require "private/bootstrap.php"; Tags::apply();' >/dev/null
chk "applying twice does not duplicate" "$(curl -s "$BASE/" | grep -c 'G-TESTGATE1')" "$once"

echo; echo "── 5. Removal is exact ────────────────────────────────"
before=$(md5sum "$ROOT/contact/index.html" | cut -d' ' -f1)
$PHP -r '
require "private/bootstrap.php";
foreach (array_merge(array_keys(Tags::DEFS), array_keys(Tags::SLOTS)) as $k) Settings::set("tag_".$k."_on","0");
Tags::apply();' >/dev/null
chk "the tag is gone from the homepage" "$(curl -s "$BASE/" | grep -c 'G-TESTGATE1')" "0"
chk "markers survive removal" "$(curl -s "$BASE/" | grep -c 'WWT_TAGS_HEAD_START')" "1"
# The page must return to exactly what the build produced.
# With everything off, the page must be byte-for-byte what the build wrote.
if cmp -s "$ROOT/contact/index.html" ../site/contact/index.html; then
  ok "the page is byte-identical to the build after removal"
else
  no "removal exactness" "$(cmp "$ROOT/contact/index.html" ../site/contact/index.html 2>&1 | head -1)"
fi

echo; echo "── 6. Honesty about cost and consent ──────────────────"
JAR=/tmp/wwt-gate6.jar
bash tools/login.sh "$JAR" >/dev/null 2>&1
$PHP -r 'require "private/bootstrap.php"; Settings::set("tag_ga4_on","1");'
page=$(curl -s -b "$JAR" "$BASE/admin/?p=integrations")
has "$page" "These set cookies"    && ok "warns that a cookie obligation now exists" || no "cookie warning" "missing"
has "$page" "privacy policy"       && ok "points at the privacy policy"              || no "privacy link" "missing"
has "$page" "measured"             && ok "states a measured page-weight cost"        || no "weight" "not stated as measured"
has "$page" "no cookies"           && ok "distinguishes the harmless verification tags" || no "no-cookie pill" "missing"
$PHP -r 'require "private/bootstrap.php"; Settings::set("tag_ga4_on","0"); Tags::apply();' >/dev/null

echo; echo "── 7. Access control ──────────────────────────────────"
VJAR=/tmp/wwt-gate6-viewer.jar
bash tools/login.sh "$VJAR" staff@wwwebtech.in devpassword123 >/dev/null 2>&1
chk "the viewer really is signed in" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=leads")" "200"
chk "and is refused this page by role, not by login" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=integrations")" "403"
chk "applying without CSRF is refused" \
  "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST --data-urlencode "action=apply" "$BASE/admin/?p=integrations")" "419"

echo; echo "── 8. Nothing left behind ─────────────────────────────"
$PHP -r '
require "private/bootstrap.php";
foreach (array_merge(array_keys(Tags::DEFS), array_keys(Tags::SLOTS)) as $k) {
  Settings::set("tag_".$k, ""); Settings::set("tag_".$k."_on","0");
}
Tags::apply();' >/dev/null
chk "no test tag remains on the site" "$(grep -rl 'TESTGATE' "$ROOT" --include='*.html' 2>/dev/null | wc -l | tr -d ' ')" "0"

echo; echo "───────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
