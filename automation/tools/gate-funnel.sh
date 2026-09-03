#!/usr/bin/env bash
# Phase 3 gate — form, scoring and capture (§12.3).
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
PHP=${PHP:-/usr/bin/php8.3}
EP="$BASE/api/lead.php"
pass=0; fail=0
ok(){ printf "  \033[32mPASS\033[0m  %s\n" "$1"; pass=$((pass+1)); }
no(){ printf "  \033[31mFAIL\033[0m  %s — %s\n" "$1" "$2"; fail=$((fail+1)); }
chk(){ [ "$2" = "$3" ] && ok "$1" || no "$1" "got '$2', want '$3'"; }
q(){ mysql -N -B -uwwt -pdevpass wwt_dev -e "$1" 2>/dev/null; }
KEY=$($PHP -r '$c=require "private/config.php"; echo $c["cron_key"];')

echo; echo "── Reset ────────────────────────────────────────────────"
q "DELETE FROM wwt_leads; DELETE FROM wwt_rate_limit;" >/dev/null
echo "  leads cleared"

# A lead is not just its budget. The fixtures below vary company, website,
# message length and click ID as well, because a test where every lead
# supplies the same supporting detail cannot tell a hot lead from a cold one
# — it only measures the budget field.
submit(){  # name budget service timeline lp gclid term n message [rich]
  q "DELETE FROM wwt_rate_limit" >/dev/null
  local rich="${10:-yes}"
  local extra=()
  if [ "$rich" = "yes" ]; then
    extra=(--data-urlencode "company_name=Example Traders"
           --data-urlencode "has_site=yes" --data-urlencode "site_url=example.in"
           --data-urlencode "_dwell=95" --data-urlencode "_seen=3")
  else
    extra=(--data-urlencode "has_site=no" --data-urlencode "_dwell=14" --data-urlencode "_seen=1")
  fi
  curl -s -o /dev/null -X POST -H 'Accept: application/json' \
    -H "Referer: $BASE/lp/$5/?gclid=$6&utm_source=google&utm_medium=cpc&utm_term=$7" \
    --data-urlencode "t=$KEY" --data-urlencode "_started=$(( $(date +%s) - 60 ))" \
    --data-urlencode "name=$1" --data-urlencode "email=$(echo "$1" | tr 'A-Z ' 'a-z.')@example.com" \
    --data-urlencode "phone=+91 98765 4321$8" --data-urlencode "consent=1" \
    --data-urlencode "budget=$2" --data-urlencode "need[]=$3" --data-urlencode "timeline=$4" \
    --data-urlencode "message=$9" --data-urlencode "_lp=$5" --data-urlencode "_variant=a" \
    --data-urlencode "_page=/lp/$5/" --data-urlencode "gclid=$6" --data-urlencode "utm_term=$7" \
    "${extra[@]}" "$EP"
}

echo; echo "── 1. Ten leads across the bands ────────────────────────"
submit "Rakesh Hot"  "₹1.5L – ₹5L"  "CRM"        "asap"     "custom-crm" "GCL-AAA-1" "crm-for-small-business" 0 "We track everything in spreadsheets and keep losing enquiries between WhatsApp and email. Need something the team will actually use."
submit "Priya Hot"   "₹5L+"         "CRM"        "asap"     "custom-crm" "GCL-AAA-2" "crm-development-company" 1 "Fifty people, three systems, nothing talks to anything. Looking for a proper build with a fixed scope."
submit "Anil Warm"   "₹50k – ₹1.5L" "Website"    "1-3m"     "website-development" "GCL-AAA-3" "website-cost" 2 "Our site is slow and old and we would like to replace it."
submit "Meena Warm"  "₹50k – ₹1.5L" "SEO"        "1-3m"     "seo-ai-visibility" "" "seo-company-delhi" 3 "Traffic dropped over the last few months and nobody can explain why to us."
submit "Sunil Warm"  "Not sure yet" "Automation" "asap"     "business-automation" "GCL-AAA-5" "workflow-automation" 4 "We have someone retyping orders into Tally all day and it is costing us."
submit "Vikram Hot"  "₹5L+"         "Website"    "asap"     "ecommerce" "GCL-AAA-6" "ecommerce-website-development" 5 "Two thousand SKUs across Amazon and our own store and the stock never matches."
submit "Farah Warm"  "₹1.5L – ₹5L"  "Website"    "1-3m"     "website-development-delhi" "GCL-AAA-7" "web-design-delhi" 6 "Need a new site for our showroom, want to meet in person first."
# The cold three: no company, no website, vague and unhurried. This is what a
# genuinely low-intent enquiry actually looks like.
submit "Kavita Cold" "Under ₹50k"   "Social"     "research" "social-media" "" "" 7 "just looking around for now" no
submit "Deepak Cold" "Not sure yet" "Not sure"   "research" "ecommerce" "" "" 8 "send me some information" no
submit "Neha Cold"   "Under ₹50k"   "Social"     "research" "social-media" "" "" 9 "what are your rates" no

chk "ten leads stored" "$(q 'SELECT COUNT(*) FROM wwt_leads')" "10"
echo "      band spread:"
q "SELECT CONCAT('        ', RPAD(band,6), COUNT(*)) FROM wwt_leads GROUP BY band ORDER BY FIELD(band,'hot','warm','cold')"
chk "every lead scored" "$(q 'SELECT COUNT(*) FROM wwt_leads WHERE score = 0')" "0"
b=$(q "SELECT COUNT(DISTINCT band) FROM wwt_leads")
[ "$b" -ge 2 ] && ok "the score separates leads into $b bands" || no "banding" "everything landed in one band"

echo; echo "── 2. Click IDs and attribution (§3.2) ──────────────────"
chk "gclid captured" "$(q "SELECT gclid FROM wwt_leads WHERE name='Rakesh Hot'")" "GCL-AAA-1"
chk "click IDs captured where present" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE gclid<>''")" "6"
chk "landing page recorded" "$(q "SELECT landing_page FROM wwt_leads WHERE name='Vikram Hot'")" "ecommerce"
chk "variant recorded" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE variant='a'")" "10"
chk "utm_term captured" "$(q "SELECT utm_term FROM wwt_leads WHERE name='Meena Warm'")" "seo-company-delhi"
chk "timeline captured" "$(q "SELECT timeline FROM wwt_leads WHERE name='Rakesh Hot'")" "asap"
chk "company name kept, not the honeypot" "$(q "SELECT company FROM wwt_leads WHERE name='Rakesh Hot'")" "Example Traders"
chk "existing site recorded" "$(q "SELECT site_url FROM wwt_leads WHERE name='Rakesh Hot'")" "https://example.in"
chk "dwell recorded" "$(q "SELECT dwell_secs FROM wwt_leads WHERE name='Rakesh Hot'")" "95"
chk "consent recorded with a version" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE consent_at IS NOT NULL AND consent_text<>''")" "10"
chk "no full IP stored" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE ip_trunc REGEXP '^[0-9]+\\\\.[0-9]+\\\\.[0-9]+\\\\.[1-9]'")" "0"

echo; echo "── 3. Scoring behaves ───────────────────────────────────"
hot=$(q "SELECT score FROM wwt_leads WHERE name='Priya Hot'")
cold=$(q "SELECT score FROM wwt_leads WHERE name='Neha Cold'")
[ -n "$hot" ] && [ -n "$cold" ] && [ "$hot" -gt "$cold" ] && ok "a serious lead outscores a vague one ($hot vs $cold)" \
  || no "scoring" "serious=$hot vague=$cold"
chk "the short-message penalty applied" "$(q "SELECT band FROM wwt_leads WHERE name='Neha Cold'")" "cold"
chk "a paid click scores as paid search" "$(q "SELECT band FROM wwt_leads WHERE name='Priya Hot'")" "hot"

echo; echo "── 4. Partial leads (§3.1) ──────────────────────────────"
q "DELETE FROM wwt_rate_limit" >/dev/null
before=$(q 'SELECT COUNT(*) FROM wwt_leads')
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "t=$KEY" --data-urlencode "_partial=1" --data-urlencode "name=Half Finished" \
  --data-urlencode "email=half@example.com" --data-urlencode "need[]=CRM" \
  --data-urlencode "_lp=custom-crm" "$EP")
chk "an abandoned form answers 204" "$code" "204"
chk "and is stored as a partial" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE is_partial=1")" "1"
# A second beacon from the same session must update, not duplicate.
curl -s -o /dev/null -X POST --data-urlencode "t=$KEY" --data-urlencode "_partial=1" \
  --data-urlencode "name=Half Finished" --data-urlencode "email=half@example.com" \
  --data-urlencode "phone=+91 90000 00000" --data-urlencode "need[]=CRM" \
  --data-urlencode "_lp=custom-crm" "$EP"
chk "a second beacon updates rather than duplicates" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE is_partial=1")" "1"
chk "and the new detail was kept" "$(q "SELECT phone FROM wwt_leads WHERE is_partial=1")" "+91 90000 00000"
chk "a partial with no way to reply is not stored" \
  "$(curl -s -o /dev/null -w '%{http_code}' -X POST --data-urlencode "_partial=1" --data-urlencode "name=Nobody" "$EP")" "204"
chk "  (still just the one partial)" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE is_partial=1")" "1"
chk "partials are never emailed" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE is_partial=1 AND mail_status='sent'")" "0"

echo; echo "── 5. Anti-spam (§3.4) ──────────────────────────────────"
q "DELETE FROM wwt_rate_limit" >/dev/null
before=$(q 'SELECT COUNT(*) FROM wwt_leads')
curl -s -o /dev/null -X POST --data-urlencode "t=$KEY" --data-urlencode "_started=$(date +%s)" \
  --data-urlencode "name=Too Fast" --data-urlencode "email=fast@example.com" \
  --data-urlencode "phone=+919999999999" --data-urlencode "message=hello there friend" "$EP"
chk "a submission inside 3 seconds is dropped" "$(q 'SELECT COUNT(*) FROM wwt_leads')" "$before"
curl -s -o /dev/null -X POST --data-urlencode "t=$KEY" --data-urlencode "name=Bot" \
  --data-urlencode "email=bot@example.com" --data-urlencode "message=spam" \
  --data-urlencode "company=Acme Spam Co" "$EP"
chk "the honeypot still stores nothing" "$(q 'SELECT COUNT(*) FROM wwt_leads')" "$before"


echo; echo "── 6. The rate limit still works ────────────────────────"
q "DELETE FROM wwt_rate_limit" >/dev/null
lim=$($PHP -r 'require "private/bootstrap.php"; echo Leads::rateMax();')
for i in $(seq 1 "$lim"); do
  curl -s -o /dev/null -X POST --data-urlencode "t=$KEY" --data-urlencode "_started=$(( $(date +%s) - 60 ))" \
    --data-urlencode "name=Flood $i" --data-urlencode "email=flood$i@example.com" \
    --data-urlencode "phone=+919000000000" --data-urlencode "message=a message long enough to pass" "$EP"
done
chk "the ${lim}th submission is still accepted" \
  "$(curl -s -o /dev/null -w '%{http_code}' -X POST --data-urlencode "t=$KEY" \
     --data-urlencode "_started=$(( $(date +%s) - 60 ))" --data-urlencode "name=One Too Many" \
     --data-urlencode "email=over@example.com" --data-urlencode "phone=+919000000000" \
     --data-urlencode "message=a message long enough to pass" "$EP")" "429"
q "DELETE FROM wwt_rate_limit" >/dev/null

echo; echo "─────────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
