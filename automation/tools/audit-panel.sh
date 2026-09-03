#!/usr/bin/env bash
# Walk every page and every POST action in the panel and report what happens.
#   bash automation/tools/audit-panel.sh
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
PHP=${PHP:-/usr/bin/php8.3}
JAR=/tmp/wwt-audit.jar
bash tools/login.sh "$JAR" >/dev/null 2>&1

tok(){ curl -s -b "$JAR" "$BASE/admin/?p=$1" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "── every page renders ──"
for p in dashboard leads analytics blog seo integrations settings; do
  body=$(curl -s -b "$JAR" -w '\n%{http_code}' "$BASE/admin/?p=$p")
  code=$(tail -1 <<<"$body")
  err=$(grep -oE "Something went wrong|Fatal error|Warning:|Notice:|Deprecated:|undefined (function|method|variable)" <<<"$body" | head -1)
  printf "  %-14s HTTP %-4s %s\n" "$p" "$code" "${err:-clean}"
done

echo
echo "── every POST action is reachable and does not fatal ──"
# action|page|extra form fields
while IFS='|' read -r act page extra; do
  [ -z "$act" ] && continue
  t=$(tok "$page")
  # $extra is pre-encoded "a=b&c=d" so a value containing a space cannot
  # split into extra arguments — which curl then read as further URLs, firing
  # several requests and concatenating their status codes.
  out=$(curl -s -b "$JAR" -o /tmp/act.out -w '%{http_code}' -X POST \
        --data-urlencode "_csrf=$t" --data-urlencode "action=$act" \
        ${extra:+--data "$extra"} "$BASE/admin/?p=$page")
  err=$(grep -oE "Fatal error|undefined function|undefined method|Call to undefined" /tmp/act.out | head -1)
  printf "  %-16s %-13s HTTP %-4s %s\n" "$act" "$page" "$out" "${err:-ok}"
done <<'ACTIONS'
rollup_now|analytics|
purge_tests|analytics|
run_daily|seo|
run_weekly|seo|
save_key|seo|--data-urlencode pagespeed_key=
retry_job|dashboard|task=seo_daily
forget_job|dashboard|task=__nonexistent
status|leads|id=1&status=contacted
bulk_status|leads|ids%5B%5D=1&status=new
purge_tests|leads|
save|integrations|tag_gsc=
apply|integrations|
verify|integrations|
clear|integrations|
prefs|settings|hits_retention_days=90&analytics_enabled=1
mail|settings|host=127.0.0.1&port=2525&secure=none&user=no-reply%40wwwebtech.in&from_name=Test&lead_email=contact%40wwwebtech.in&reply_promise=1%20business%20day&lead_ack_enabled=1
mail_test|settings|
toggle|blog|
prefs|blog|blog_model=claude-opus-5&blog_per_day=2&blog_effort=medium&blog_monthly_cap_usd=15
add_topic|blog|title_seed=Audit%20topic&angle=An%20angle%20for%20the%20audit&cluster=web
seed_topics|blog|
ACTIONS

echo
echo "── clean up after the audit ──"
# The audit exercises real write actions. Anything it creates has to go, or
# the next gate run counts it and fails on a number the audit moved.
$PHP <<'CLEANUP'
<?php
require getenv('PWD') . '/private/bootstrap.php';
$n = DB::run('DELETE FROM wwt_topics WHERE title_seed LIKE ?', ['Audit%'])->rowCount();
printf("  removed %d topic(s) the audit added; %d remain\n",
    $n, (int)DB::val('SELECT COUNT(*) FROM wwt_topics', [], 0));
CLEANUP

echo
echo "── an action name that does not exist is ignored, not fatal ──"
t=$(tok settings)
code=$(curl -s -b "$JAR" -o /tmp/act.out -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$t" --data-urlencode "action=totally_made_up" "$BASE/admin/?p=settings")
printf "  %-16s %-13s HTTP %-4s %s\n" "made-up action" "settings" "$code" \
  "$(grep -oE 'Fatal error|undefined' /tmp/act.out | head -1 || echo ok)"

echo
echo "── a viewer is refused every write ──"
VJAR=/tmp/wwt-audit-viewer.jar
bash tools/login.sh "$VJAR" staff@wwwebtech.in devpassword123 >/dev/null 2>&1
vt=$(curl -s -b "$VJAR" "$BASE/admin/?p=leads" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
for pg in leads analytics dashboard; do
  code=$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' -X POST \
    --data-urlencode "_csrf=$vt" --data-urlencode "action=purge_tests" "$BASE/admin/?p=$pg")
  printf "  %-16s %-13s HTTP %s %s\n" "purge_tests" "$pg" "$code" \
    "$([ "$code" = "403" ] && echo "refused, correct" || echo "EXPECTED 403")"
done
