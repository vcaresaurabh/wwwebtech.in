#!/usr/bin/env bash
# Phase 4 acceptance gate — the blog engine.
#   bash automation/tools/gate-phase4.sh
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

echo; echo "── 1. Topic bank ──────────────────────────────────────"
chk "90 topics seeded" "$(q 'SELECT COUNT(*) FROM wwt_topics')" "90"
chk "30 per cluster" "$(q 'SELECT COUNT(DISTINCT n) FROM (SELECT cluster, COUNT(*) n FROM wwt_topics GROUP BY cluster) x')" "1"
chk "three clusters" "$(q 'SELECT COUNT(DISTINCT cluster) FROM wwt_topics')" "3"
chk "every topic has an angle" "$(q "SELECT COUNT(*) FROM wwt_topics WHERE angle=''")" "0"
chk "titles are unique" "$(q 'SELECT COUNT(*)-COUNT(DISTINCT title_seed) FROM wwt_topics')" "0"
$PHP private/cron/run.php blog_seed_topics >/dev/null 2>&1
chk "re-seeding adds no duplicates" "$(q 'SELECT COUNT(*) FROM wwt_topics')" "90"

echo; echo "── 2. Quality gates: a good post passes ───────────────"
out=$($PHP tools/gatecheck.php good 2>&1)
chk "the reference post passes every gate" "$out" "PASS"

echo; echo "── 3. Quality gates: each one actually fires ──────────"
# A gate that never fires is not a gate. Every case below is the good post
# with exactly one thing broken.
while IFS='|' read -r name expect; do
  [ -z "$name" ] && continue
  got=$($PHP tools/gatecheck.php "$name" 2>&1)
  if has "$got" "$expect"; then ok "catches: $name"
  else no "catches: $name" "expected /$expect/, got: $(head -c 120 <<<"$got")"; fi
done <<'CASES'
short|words, minimum 800
no_h1_rule|contains an <h1>
few_h2|<h2> sections, minimum
h2_no_id|have no id
long_title|title is
no_faq|fewer than 3 FAQ
script_tag|contains a <script> tag
inline_handler|inline event handler
external_link|links to an external site
dead_link|links to a page that does not exist
stat_percent|invented statistic about a population
stat_studies|unsourced appeal to research
stat_experts|unsourced appeal to experts
invented_client|invented client
invented_result|invented result claim
case_study|case study
guarantee|a guarantee we cannot make
ai_filler|AI filler
cliche|opening cliché
CASES

echo; echo "── 3b. The bans do not catch honest writing ───────────"
# A ban that also rejects the correct statement is worse than no ban.
banout=$($PHP tools/bancheck.php 2>&1)
if [ $? -eq 0 ]; then ok "$banout"
else no "banned patterns are imprecise" "$(head -3 <<<"$banout")"; fi

echo; echo "── 4. Duplicate detection ─────────────────────────────"
sim=$($PHP -r 'require "private/bootstrap.php";
$p=json_decode(file_get_contents("tools/fixtures/good.json"),true);
$t=Blog::plainText($p["body"]);
printf("%.3f", Blog::similarity($t,$t));')
chk "a post is 100% similar to itself" "$sim" "1.000"
out=$($PHP tools/gatecheck.php dup 2>&1)
has "$out" "similar to" && ok "a near-rewrite is rejected" || no "duplicate detection" "not caught: $out"
diff=$($PHP -r 'require "private/bootstrap.php";
printf("%.3f", Blog::similarity(
  "the quick brown fox jumps over the lazy dog in the morning sun",
  "search engines index pages by following links between documents"));')
awk -v v="$diff" 'BEGIN{exit !(v < 0.1)}' \
  && ok "unrelated text scores near zero ($diff)" || no "similarity" "unrelated text scored $diff"

echo; echo "── 5. Parser tolerance ────────────────────────────────"
$PHP -r 'require "private/bootstrap.php";
$j = json_encode(["title"=>"T","dek"=>"D","read"=>5,"body"=>"<p>x</p>","faq"=>[]]);
$cases = [
  "bare"        => $j,
  "fenced"      => "```json\n$j\n```",
  "preamble"    => "Here is the article:\n$j",
  "not json"    => "I cannot do that.",
  "missing key" => "{\"title\":\"T\"}",
];
foreach ($cases as $k => $v) {
  $r = Blog::parse($v);
  printf("%s=%s\n", $k, $r === null ? "null" : "ok");
}' > /tmp/parse.txt
for c in "bare=ok" "fenced=ok" "preamble=ok"; do
  grep -q "^$c$" /tmp/parse.txt && ok "parses: ${c%%=*}" || no "parses ${c%%=*}" "$(grep "^${c%%=*}=" /tmp/parse.txt)"
done
grep -q "^not json=null$" /tmp/parse.txt && ok "refuses: a non-JSON reply" || no "non-JSON" "was accepted"
grep -q "^missing key=null$" /tmp/parse.txt && ok "refuses: JSON missing required keys" || no "missing key" "was accepted"

echo; echo "── 6. Cost control ────────────────────────────────────"
$PHP -r 'require "private/bootstrap.php";
printf("opus5_1k=%.6f\n", Claude::priceOf("claude-opus-5", 1000, 1000));
printf("unknown=%.6f\n", Claude::priceOf("not-a-model", 1000, 1000));' > /tmp/cost.txt
chk "Opus 5 priced from the published rate" "$(grep -o 'opus5_1k=.*' /tmp/cost.txt)" "opus5_1k=0.030000"
chk "an unknown model is never guessed at" "$(grep -o 'unknown=.*' /tmp/cost.txt)" "unknown=0.000000"
capout=$($PHP -r 'require "private/bootstrap.php";
  Settings::set("blog_monthly_cap_usd","0");
  Secrets::put("anthropic_key","sk-ant-fake-key-for-the-cap-test");
  $r = Claude::message("s","p",["retries"=>1]);
  echo $r["error"];')
has "$capout" "Monthly cap" && ok "the cap refuses to spend BEFORE calling out" \
  || no "monthly cap" "got: $(head -c 100 <<<"$capout")"
$PHP -r 'require "private/bootstrap.php"; Settings::set("blog_monthly_cap_usd","15"); Secrets::put("anthropic_key","");' 

echo; echo "── 7. Publishing ──────────────────────────────────────"
$PHP tools/publish-fixture.php > /tmp/pub.txt 2>&1
has "$(cat /tmp/pub.txt)" "PUBLISHED" && ok "the fixture publishes" || no "publish" "$(head -c 200 /tmp/pub.txt)"
ROOT=$($PHP -r 'require "private/bootstrap.php"; echo Blog::webroot();')
SLUG=$(grep -o 'slug=[a-z0-9-]*' /tmp/pub.txt | head -1 | cut -d= -f2)
[ -f "$ROOT/blog/$SLUG/index.html" ] && ok "the post page exists on disk" || no "post page" "missing"
page=$(cat "$ROOT/blog/$SLUG/index.html" 2>/dev/null)
chk "exactly one <h1> on the page" "$(grep -o '<h1' <<<"$page" | wc -l | tr -d ' ')" "1"
has "$page" "{{" && no "no placeholders left unfilled" "template markers remain" || ok "no placeholders left unfilled"
has "$page" 'class="prose"' && ok "uses the site's own article markup" || no "markup" "prose wrapper missing"
has "$page" '"@type":"FAQPage"' && ok "FAQ structured data injected" || no "FAQ schema" "missing"
has "$page" 'In this piece' && ok "contents list rendered" || no "TOC" "missing"
toc=$(grep -c 'href="#' <<<"$page")
[ "$toc" -ge 4 ] && ok "contents list has entries ($toc)" || no "TOC entries" "only $toc"
has "$(cat "$ROOT/blog/index.html")" "$SLUG" && ok "listed on /blog/" || no "blog index" "not listed"
has "$(cat "$ROOT/index.html")" "$SLUG" && ok "teased on the homepage" || no "homepage" "not teased"
has "$(cat "$ROOT/sitemap.xml")" "$SLUG" && ok "added to sitemap.xml" || no "sitemap" "not added"
if $PHP -r 'require "private/bootstrap.php";
     exit(@simplexml_load_file(Blog::webroot()."/sitemap.xml") === false ? 1 : 0);'; then
  ok "sitemap.xml is still valid XML"
else no "sitemap XML" "malformed after the edit"; fi

echo; echo "── 8. Unpublishing removes every trace ────────────────"
$PHP tools/publish-fixture.php --unpublish > /tmp/unpub.txt 2>&1
[ ! -f "$ROOT/blog/$SLUG/index.html" ] && ok "the page is gone from disk" || no "unpublish" "page still there"
has "$(cat "$ROOT/blog/index.html")" "$SLUG" && no "removed from /blog/" "still listed" || ok "removed from /blog/"
has "$(cat "$ROOT/index.html")" "$SLUG" && no "removed from the homepage" "still teased" || ok "removed from the homepage"
has "$(cat "$ROOT/sitemap.xml")" "$SLUG" && no "removed from sitemap.xml" "still present" || ok "removed from sitemap.xml"

echo; echo "── 9. Kill switch ─────────────────────────────────────"
$PHP -r 'require "private/bootstrap.php"; Settings::set("blog_enabled","0");'
out=$($PHP private/cron/run.php blog_daily 2>&1)
has "$out" "switched off" && ok "the kill switch stops the daily job" || no "kill switch" "got: $out"
$PHP -r 'require "private/bootstrap.php"; Settings::set("blog_enabled","1");'
out=$($PHP private/cron/run.php blog_daily 2>&1)
has "$out" "no API key" && ok "and a missing key stops it too, without an error" \
  || no "no-key guard" "got: $out"
$PHP -r 'require "private/bootstrap.php"; Settings::set("blog_enabled","0");'

echo; echo "── 9b. Surviving a site deploy ────────────────────────"
# A deploy of site/ overwrites the blog index, the homepage and the sitemap
# with build-time versions that know nothing about server-published posts,
# and rsync --delete removes the post directories outright. The database is
# the source of truth; republish_all has to put all of it back.
$PHP tools/publish-fixture.php >/dev/null 2>&1
SLUG=gate-fixture-website-redesign
ROOT=$($PHP -r 'require "private/bootstrap.php"; echo Blog::webroot();')
rm -rf "$ROOT/blog/$SLUG"
python3 - "$ROOT" <<'PYX'
import sys, re
root = sys.argv[1]
for f in [root + '/blog/index.html', root + '/index.html']:
    s = open(f, encoding='utf-8').read()
    s = re.sub(r'<!--BLOG_TEASERS_START-->.*?<!--BLOG_TEASERS_END-->',
               '<!--BLOG_TEASERS_START--><!--BLOG_TEASERS_END-->', s, flags=re.S)
    open(f, 'w', encoding='utf-8').write(s)
PYX
chk "the simulated deploy really removed the post" \
  "$([ -f "$ROOT/blog/$SLUG/index.html" ] && echo present || echo gone)" "gone"
$PHP private/cron/run.php republish_all >/dev/null 2>&1
chk "republish_all restores the post page" \
  "$([ -f "$ROOT/blog/$SLUG/index.html" ] && echo yes || echo no)" "yes"
chk "the restored page is complete" "$(grep -c '</html>' "$ROOT/blog/$SLUG/index.html")" "1"
chk "with no unfilled placeholders" "$(grep -c '{{' "$ROOT/blog/$SLUG/index.html")" "0"
chk "and it is back in the blog index" "$(grep -c "$SLUG" "$ROOT/blog/index.html")" "1"
chk "and on the homepage" "$(grep -c "$SLUG" "$ROOT/index.html")" "1"
chk "and in the sitemap" "$(grep -c "$SLUG" "$ROOT/sitemap.xml")" "1"
$PHP tools/publish-fixture.php --unpublish >/dev/null 2>&1

# The deploy script must not delete the files the automation layer owns.
DEPLOY=../tools/deploy.sh
for f in "/serve.php" "/_wwt.php" "/admin/" "/api/" "/llms.txt" "/flow/"; do
  has "$(cat $DEPLOY)" "exclude '$f'" && ok "deploy.sh preserves $f" \
    || no "deploy.sh would delete $f" "not in the exclude list"
done
has "$(cat $DEPLOY)" "republish_all" && ok "deploy.sh rebuilds generated pages afterwards" \
  || no "deploy.sh republish" "missing"

echo; echo "── 10. Panel ──────────────────────────────────────────"
JAR=/tmp/wwt-gate4.jar
bash tools/login.sh "$JAR" >/dev/null 2>&1
bp=$(curl -s -b "$JAR" -w '\n%{http_code}' "$BASE/admin/?p=blog")
chk "blog page renders" "$(tail -1 <<<"$bp")" "200"
has "$bp" "Something went wrong" && no "blog page clean" "threw mid-render" || ok "blog page renders without error"
has "$bp" "Automatic publishing is" && ok "the kill switch is the first control" || no "kill switch" "not on the page"
has "$bp" "No Anthropic API key is set" && ok "says plainly when no key is set" || no "key warning" "missing"
# The key moved to the Connections hub; the Blog page points there.
has "$bp" "?p=connections" && ok "the blog page points at Connections for the key" || no "key pointer" "missing"
cp=$(curl -s -b "$JAR" "$BASE/admin/?p=connections")
has "$cp" "Claude (Anthropic)" && ok "the Connections page has the Claude card" || no "claude card" "missing"
# A stored key must never be echoed back into the page.
$PHP -r 'require "private/bootstrap.php"; Secrets::put("anthropic_key","sk-ant-secret-value-abc123");' 
chk "a saved key is never rendered on the blog page" \
  "$(curl -s -b "$JAR" "$BASE/admin/?p=blog" | grep -c 'sk-ant-secret-value-abc123')" "0"
chk "nor on the Connections page" \
  "$(curl -s -b "$JAR" "$BASE/admin/?p=connections" | grep -c 'sk-ant-secret-value-abc123')" "0"
$PHP -r 'require "private/bootstrap.php"; Secrets::put("anthropic_key","");'

# Sign the viewer in here rather than relying on a jar another gate left
# behind: a stale jar would produce a login redirect and look like a pass.
VJAR=/tmp/wwt-gate4-viewer.jar
bash tools/login.sh "$VJAR" staff@wwwebtech.in devpassword123 >/dev/null 2>&1
chk "the viewer really is signed in" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=leads")" "200"
chk "and is refused the blog page by role, not by login" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=blog")" "403"

echo; echo "── 11. Nothing left behind ────────────────────────────"
$PHP tools/publish-fixture.php --unpublish >/dev/null 2>&1
chk "the test fixture is gone from the database" \
  "$(q "SELECT COUNT(*) FROM wwt_posts WHERE slug='gate-fixture-website-redesign'")" "0"
ROOT=$($PHP -r 'require "private/bootstrap.php"; echo Blog::webroot();')
[ ! -d "$ROOT/blog/gate-fixture-website-redesign" ] && ok "and gone from the web root" \
  || no "fixture cleanup" "directory remains"

echo; echo "───────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
