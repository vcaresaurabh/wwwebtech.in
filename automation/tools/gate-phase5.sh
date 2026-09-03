#!/usr/bin/env bash
# Phase 5 acceptance gate — the SEO/GEO engine.
#   bash automation/tools/gate-phase5.sh
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

echo; echo "── 1. Against the live site ───────────────────────────"
out=$($PHP private/cron/run.php seo_daily 2>&1)
chk "the daily run completes" "$(q "SELECT last_status FROM wwt_task_runs WHERE task='seo_daily'")" "ok"
n=$(q "SELECT COUNT(*) FROM wwt_seo_checks WHERE d=UTC_DATE()")
[ "$n" -ge 8 ] && ok "$n checks recorded" || no "check count" "only $n"
chk "every check has a verdict" "$(q "SELECT COUNT(*) FROM wwt_seo_checks WHERE d=UTC_DATE() AND status=''")" "0"
chk "every check explains itself" "$(q "SELECT COUNT(*) FROM wwt_seo_checks WHERE d=UTC_DATE() AND (detail IS NULL OR detail='')")" "0"

# The point of the gate: an engine that says everything is fine is not
# working. It has to find the things that are genuinely wrong today.
flagged=$(q "SELECT COUNT(*) FROM wwt_seo_checks WHERE d=UTC_DATE() AND status IN ('warn','fail','info')")
[ "$flagged" -ge 1 ] && ok "it flags $flagged real defects on the live site" \
  || no "live defects" "it reported nothing wrong, which means it is not checking"
echo "      findings:"
q "SELECT CONCAT('        ', UPPER(status), ' · ', check_name, ' — ', LEFT(REPLACE(detail,'\n',' '),95))
   FROM wwt_seo_checks WHERE d=UTC_DATE() AND status<>'ok' ORDER BY FIELD(status,'fail','warn','info')"

echo; echo "── 2. The metadata lint actually fires ────────────────"
# A lint that passes a real site might be broken. Feed it pages that are
# definitely wrong and confirm each rule catches its own case.
D=.dev/webroot/_lint
mkdir -p "$D"
cat > "$D/notitle.html" <<'H'
<!doctype html><html><head><meta name="description" content="A description that is comfortably long enough to pass the minimum length rule without trouble."><link rel="canonical" href="/x"><meta property="og:image" content="/og.png"></head><body><h1>One</h1></body></html>
H
cat > "$D/longtitle.html" <<'H'
<!doctype html><html><head><title>An extremely long page title that goes well beyond the sixty character limit Google uses</title><meta name="description" content="A description that is comfortably long enough to pass the minimum length rule without trouble."><link rel="canonical" href="/x"><meta property="og:image" content="/og.png"></head><body><h1>One</h1></body></html>
H
cat > "$D/twoh1.html" <<'H'
<!doctype html><html><head><title>A perfectly reasonable title</title><meta name="description" content="A description that is comfortably long enough to pass the minimum length rule without trouble."><link rel="canonical" href="/x"><meta property="og:image" content="/og.png"></head><body><h1>One</h1><h1>Two</h1></body></html>
H
cat > "$D/nocanon.html" <<'H'
<!doctype html><html><head><title>A perfectly reasonable title</title><meta name="description" content="A description that is comfortably long enough to pass the minimum length rule without trouble."><meta property="og:image" content="/og.png"></head><body><h1>One</h1></body></html>
H
cat > "$D/nodesc.html" <<'H'
<!doctype html><html><head><title>A perfectly reasonable title</title><link rel="canonical" href="/x"><meta property="og:image" content="/og.png"></head><body><h1>One</h1></body></html>
H
cat > "$D/clean.html" <<'H'
<!doctype html><html><head><title>A perfectly reasonable title</title><meta name="description" content="A description that is comfortably long enough to pass the minimum length rule without trouble."><link rel="canonical" href="/x"><meta property="og:image" content="/og.png"></head><body><h1>One</h1></body></html>
H

lint=$($PHP -r '
require "private/bootstrap.php";
$urls = array_map(fn($f) => "'"$BASE"'/_lint/$f",
  ["notitle.html","longtitle.html","twoh1.html","nocanon.html","nodesc.html","clean.html"]);
$r = Seo::checkMetadata($urls);
echo $r["status"], "\n", $r["detail"], "\n";')
has "$lint" "no <title>"          && ok "catches: a missing title"        || no "missing title" "not caught"
has "$lint" "characters (over 60"  && ok "catches: an over-long title"     || no "long title" "not caught"
has "$lint" "2 <h1> headings"     && ok "catches: two h1 headings"        || no "two h1" "not caught"
has "$lint" "no canonical"        && ok "catches: a missing canonical"    || no "canonical" "not caught"
has "$lint" "no meta description" && ok "catches: a missing description"  || no "description" "not caught"
has "$lint" "clean.html"          && no "the clean page is left alone" "it was flagged" \
                                  || ok "the clean page is left alone"
q "DELETE FROM wwt_seo_checks WHERE check_name='page metadata' AND d=UTC_DATE() AND score=6" >/dev/null

echo; echo "── 3. The link crawler actually fires ─────────────────"
cat > "$D/broken.html" <<'H'
<!doctype html><html><head><title>Links</title></head><body>
<a href="/_lint/clean.html">fine</a>
<a href="/_lint/does-not-exist.html">broken</a>
<a href="https://example.com/">external, ignored</a>
</body></html>
H
crawl=$($PHP -r '
require "private/bootstrap.php";
$r = Seo::checkLinks(["'"$BASE"'/_lint/broken.html"]);
echo $r["status"], "\n", $r["detail"], "\n";')
has "$crawl" "does-not-exist"  && ok "catches: a broken internal link"    || no "broken link" "not caught: $crawl"
has "$crawl" "example.com"     && no "external links are ignored" "an external link was reported" \
                               || ok "external links are ignored"
rm -rf "$D"
q "DELETE FROM wwt_seo_checks WHERE check_name='internal links' AND d=UTC_DATE()" >/dev/null

echo; echo "── 4. robots.txt parsing ──────────────────────────────"
$PHP -r '
require "private/bootstrap.php";
$cases = [
  "blocked_named"  => ["User-agent: GPTBot\nDisallow: /\n", "GPTBot", true],
  "allowed_named"  => ["User-agent: GPTBot\nAllow: /\n", "GPTBot", false],
  "blocked_star"   => ["User-agent: *\nDisallow: /\n", "GPTBot", true],
  "partial_only"   => ["User-agent: *\nDisallow: /admin/\n", "GPTBot", false],
  "empty"          => ["", "GPTBot", false],
  "named_wins"     => ["User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nAllow: /\n", "GPTBot", false],
  "comments"       => ["# a comment\nUser-agent: GPTBot # trailing\nDisallow: /\n", "GPTBot", true],
];
foreach ($cases as $k => [$txt, $ua, $want]) {
  $got = Seo::robotsBlocks($txt, $ua);
  printf("%s=%s\n", $k, $got === $want ? "ok" : "WRONG");
}' > /tmp/robots.txt
for c in blocked_named allowed_named blocked_star partial_only empty named_wins comments; do
  grep -q "^$c=ok$" /tmp/robots.txt && ok "robots.txt: $c" || no "robots.txt: $c" "wrong verdict"
done

echo; echo "── 5. Machine-readable files ──────────────────────────"
ROOT=$($PHP -r 'require "private/bootstrap.php"; echo Blog::webroot();')
rm -f "$ROOT/llms.txt" "$ROOT/llms-full.txt"
$PHP -r 'require "private/bootstrap.php"; Seo::writeLlmsTxt(); Seo::writeLlmsFullTxt();' >/dev/null 2>&1
[ -f "$ROOT/llms.txt" ] && ok "llms.txt written" || no "llms.txt" "not written"
llms=$(cat "$ROOT/llms.txt" 2>/dev/null)
has "$llms" "# Wwwebtech"           && ok "llms.txt has a heading"        || no "llms.txt heading" "missing"
has "$llms" "/services/seo/"        && ok "llms.txt lists the services"   || no "llms.txt services" "missing"
has "$llms" "do not attribute"      && ok "llms.txt warns against inventing proof" || no "llms.txt honesty note" "missing"
[ -f "$ROOT/llms-full.txt" ] && ok "llms-full.txt written" || no "llms-full.txt" "not written"
chk "llms.txt is served" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/llms.txt")" "200"
q "DELETE FROM wwt_seo_checks WHERE check_name LIKE 'llms%' AND d=UTC_DATE()" >/dev/null

echo; echo "── 6. Vitals degrade honestly without a key ───────────"
v=$($PHP -r '
require "private/bootstrap.php";
Secrets::put("pagespeed_key","");
$r = Seo::checkVitals("https://wwwebtech.in/","mobile");
echo $r["status"], "|", $r["detail"];')
has "$v" "info|No PageSpeed API key" && ok "says the key is missing rather than inventing a score" \
  || no "vitals without a key" "got: $v"
chk "and records no fabricated measurement" "$(q "SELECT COUNT(*) FROM wwt_cwv WHERE d=UTC_DATE()")" "0"
q "DELETE FROM wwt_seo_checks WHERE check_name='core web vitals' AND d=UTC_DATE()" >/dev/null

echo; echo "── 7. Re-running does not duplicate ───────────────────"
$PHP private/cron/run.php seo_daily >/dev/null 2>&1
a=$(q "SELECT COUNT(*) FROM wwt_seo_checks WHERE d=UTC_DATE()")
$PHP private/cron/run.php seo_daily >/dev/null 2>&1
b=$(q "SELECT COUNT(*) FROM wwt_seo_checks WHERE d=UTC_DATE()")
chk "a second run replaces, not appends" "$b" "$a"

echo; echo "───────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
