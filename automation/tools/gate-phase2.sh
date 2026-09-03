#!/usr/bin/env bash
# Phase 2 acceptance gate — leads.
#   bash automation/tools/gate-phase2.sh
# Expects the dev server on :8088 and (for the mail checks) the SMTP sink.
set -uo pipefail
BASE=${BASE:-http://127.0.0.1:8088}
EP="$BASE/api/lead.php"
MAILDIR=${MAILDIR:-"$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/.dev/mail"}
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

echo; echo "── Reset ──────────────────────────────────────────────"
q "DELETE FROM wwt_leads; DELETE FROM wwt_rate_limit;
   INSERT INTO wwt_settings (k,v,updated_at) VALUES ('lead_ack_enabled','1',UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE v='1';" >/dev/null
rm -f "$MAILDIR"/*.eml 2>/dev/null
echo "  leads and rate-limit counters cleared"

echo; echo "── 1. Five submissions ────────────────────────────────"
for i in 1 2 3 4 5; do
  code=$(curl -s -o /tmp/g2_$i.json -w '%{http_code}' -X POST -H 'Accept: application/json' \
    -H "Referer: $BASE/contact/?utm_source=gate&utm_medium=qa&utm_campaign=phase2" \
    --data-urlencode "name=Gate Tester $i" \
    --data-urlencode "email=gate$i@example.com" \
    --data-urlencode "phone=+91 98765 4321$i" \
    --data-urlencode "need[]=SEO" --data-urlencode "need[]=Website" \
    --data-urlencode "budget=₹50k – ₹1.5L" \
    --data-urlencode "message=Submission number $i from the Phase 2 gate. Rupee ₹ and a curly quote ’ to prove encoding." \
    "$EP")
  chk "submission $i accepted" "$code" "200"
done
chk "five leads stored" "$(q 'SELECT COUNT(*) FROM wwt_leads')" "5"
chk "service chips whitelisted" "$(q "SELECT service FROM wwt_leads ORDER BY id LIMIT 1")" "Website, SEO"
chk "UTM recovered from Referer" "$(q "SELECT CONCAT(utm_source,'/',utm_medium,'/',utm_campaign) FROM wwt_leads ORDER BY id LIMIT 1")" "gate/qa/phase2"
chk "page recovered from Referer" "$(q "SELECT page FROM wwt_leads ORDER BY id LIMIT 1")" "/contact/"
chk "full IP never stored" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE ip_trunc LIKE '127.0.0.1'")" "0"

echo; echo "── 2. Honeypot ────────────────────────────────────────"
before=$(q 'SELECT COUNT(*) FROM wwt_leads')
body=$(curl -s -X POST -H 'Accept: application/json' \
  --data-urlencode "name=Spam Bot" --data-urlencode "email=bot@example.com" \
  --data-urlencode "message=buy cheap things" --data-urlencode "company=Acme Spam Co" "$EP")
after=$(q 'SELECT COUNT(*) FROM wwt_leads')
chk "honeypot stores nothing" "$after" "$before"
has "$body" '"ok":true' && ok "honeypot looks successful to the bot" \
  || no "honeypot response" "expected ok:true, got $body"

echo; echo "── 3. Rate limit (per IP, per hour) ───────────────────"
# Read the configured limit rather than hard-coding it: it is a setting now,
# because five an hour is tight for a shared office IP on paid traffic.
LIM=$(/usr/bin/php8.3 -r 'require "private/bootstrap.php"; echo Leads::rateMax();')
sent=5
while [ "$sent" -lt "$LIM" ]; do
  sent=$((sent+1))
  curl -s -o /dev/null -X POST -H 'Accept: application/json' \
    --data-urlencode "name=Filler $sent" --data-urlencode "email=fill$sent@example.com" \
    --data-urlencode "message=Filling the rate limit window up to the configured maximum." "$EP"
done
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Accept: application/json' \
  --data-urlencode "name=One Too Many" --data-urlencode "email=over@example.com" \
  --data-urlencode "message=This one is over the limit and must be refused." "$EP")
chk "submission ${LIM} + 1 is refused" "$code" "429"
chk "refused submission not stored" "$(q 'SELECT COUNT(*) FROM wwt_leads')" "$LIM"

echo; echo "── 4. Validation ──────────────────────────────────────"
q "DELETE FROM wwt_rate_limit;" >/dev/null
chk "empty body rejected" \
  "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Accept: application/json' "$EP")" "422"
chk "bad email rejected" \
  "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Accept: application/json' \
     --data-urlencode 'name=X' --data-urlencode 'email=not-an-email' \
     --data-urlencode 'message=hello there' "$EP")" "422"
chk "GET refused" "$(curl -s -o /dev/null -w '%{http_code}' "$EP")" "405"
chk "cross-site POST refused" \
  "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Origin: https://evil.example' \
     --data-urlencode 'name=X' --data-urlencode 'email=a@b.co' \
     --data-urlencode 'message=hello there' "$EP")" "403"
q "DELETE FROM wwt_rate_limit;" >/dev/null

echo; echo "── 5. No-JavaScript path ──────────────────────────────"
html=$(curl -s -X POST -H 'Accept: text/html,application/xhtml+xml' \
  --data-urlencode "name=No JavaScript" --data-urlencode "email=nojs@example.com" \
  --data-urlencode "message=Posted as a plain HTML form with no fetch involved." "$EP")
has "$html" "<!DOCTYPE html" && ok "returns an HTML page, not JSON" \
  || no "no-JS response" "not HTML"
hasi "$html" "Message sent" && ok "no-JS page confirms the send" \
  || no "no-JS confirmation" "missing"
chk "no-JS submission stored" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE email='nojs@example.com'")" "1"

echo; echo "── 6. Email delivery ──────────────────────────────────"
sleep 1
n=$(ls -1 "$MAILDIR"/*.eml 2>/dev/null | wc -l | tr -d ' ')
[ "$n" -ge 2 ] && ok "SMTP received $n messages" || no "SMTP delivery" "only $n messages captured"
chk "no lead left un-emailed" "$(q "SELECT COUNT(*) FROM wwt_leads WHERE mail_status<>'sent'")" "0"
# Subjects contain '\u00b7' and a curly apostrophe, so they arrive RFC 2047
# encoded. Decode before matching, or the test measures its own grep.
subjects=$(python3 - "$MAILDIR" <<'PYX'
import sys, glob, email, email.header
for f in glob.glob(sys.argv[1] + "/*.eml"):
    with open(f, encoding="utf-8", errors="replace") as fh:
        m = email.message_from_file(fh)
    parts = email.header.decode_header(m.get("Subject", ""))
    print("".join(p.decode(c or "utf-8", "replace") if isinstance(p, bytes) else p for p, c in parts))
PYX
)
# The owner notification is now the decision-ready brief from Notify (§4),
# whose subject leads with the band — "HOT lead — Name" — so that the
# subject alone says whether to stop what you are doing. Matching the old
# "New enquiry" wording here would only be testing this file's memory.
if printf '%s' "$subjects" | grep -qE '(HOT|WARM|COLD) lead'; then
  ok "owner notification present, with the band in the subject"
else
  no "owner notification" "no message with a band in the subject"
fi
has "$subjects" "received your enquiry" && ok "acknowledgement present" \
  || no "acknowledgement" "not found"
has "$subjects" "Website, SEO" && ok "both service chips reach the email" \
  || no "service chips in subject" "only one chip present"
grep -lq "^Reply-To:" "$MAILDIR"/*.eml 2>/dev/null && ok "Reply-To set to the enquirer" \
  || no "Reply-To" "missing"

echo; echo "── 7. Admin panel ─────────────────────────────────────"
JAR=/tmp/wwt-gate.jar
bash "$(dirname "${BASH_SOURCE[0]}")/login.sh" "$JAR" >/dev/null 2>&1 \
  && ok "signed in as admin" || no "admin login" "could not sign in"

lp=$(curl -s -b "$JAR" -w '\n%{http_code}' "$BASE/admin/?p=leads&show=all")
chk "leads page renders" "$(echo "$lp" | tail -1)" "200"
rows=$(echo "$lp" | grep -c "Gate Tester")
[ "$rows" -ge 5 ] && ok "leads table lists the submissions ($rows)" \
  || no "leads table" "only $rows rows"
has "$lp" "Something went wrong" && no "leads page clean" "page threw mid-render" \
  || ok "leads page renders without error"

id=$(q "SELECT id FROM wwt_leads ORDER BY id LIMIT 1")
chk "lead detail page renders" \
  "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=lead&id=$id")" "200"
# The filter controls must show the filter that is actually applied.
# PHP casts numeric-string array keys to int, so a `$days === $k` comparison
# silently never matches and the select shows option one whatever is in force.
sel=$(curl -s -b "$JAR" "$BASE/admin/?p=leads&days=30" \
      | grep -o '<option value="30"[^>]*>' | head -1)
has "$sel" "selected" && ok "period filter shows the period in force" \
  || no "period filter" "days=30 applied but the control does not show it"

chk "unknown lead is a real 404" \
  "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=lead&id=999999")" "404"

# Status change, with CSRF.
tok=$(curl -s -b "$JAR" "$BASE/admin/?p=lead&id=$id" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR" -o /dev/null -X POST --data-urlencode "_csrf=$tok" \
  --data-urlencode "action=save" --data-urlencode "id=$id" \
  --data-urlencode "status=contacted" --data-urlencode "notes=Called back." "$BASE/admin/?p=lead&id=$id"
chk "status moves through the pipeline" "$(q "SELECT status FROM wwt_leads WHERE id=$id")" "contacted"
chk "notes saved" "$(q "SELECT notes FROM wwt_leads WHERE id=$id")" "Called back."
chk "status change without CSRF is refused" \
  "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' -X POST \
     --data-urlencode "action=save" --data-urlencode "id=$id" --data-urlencode "status=won" "$BASE/admin/?p=lead&id=$id")" "419"
chk "CSRF refusal changed nothing" "$(q "SELECT status FROM wwt_leads WHERE id=$id")" "contacted"

# A read-only account must be able to look and unable to touch.
VJAR=/tmp/wwt-gate-viewer.jar
bash "$(dirname "${BASH_SOURCE[0]}")/login.sh" "$VJAR" staff@wwwebtech.in devpassword123 >/dev/null 2>&1
chk "viewer can read leads" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=leads")" "200"
vtok=$(curl -s -b "$VJAR" "$BASE/admin/?p=leads" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
chk "viewer cannot change a status" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' -X POST --data-urlencode "_csrf=$vtok" \
     --data-urlencode "action=status" --data-urlencode "id=$id" --data-urlencode "status=won" "$BASE/admin/?p=leads")" "403"
chk "viewer refusal changed nothing" "$(q "SELECT status FROM wwt_leads WHERE id=$id")" "contacted"
chk "sign-out by GET is refused" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=logout")" "303"
chk "viewer still signed in after the forged sign-out" \
  "$(curl -s -b "$VJAR" -o /dev/null -w '%{http_code}' "$BASE/admin/?p=leads")" "200"

echo; echo "── 8. CSV export ──────────────────────────────────────"
CSV=/tmp/wwt-gate-leads.csv
curl -s -b "$JAR" -D /tmp/wwt-gate-csv.h -o "$CSV" "$BASE/admin/?p=leads&show=all&export=csv"
grep -qi 'content-type: text/csv' /tmp/wwt-gate-csv.h && ok "served as text/csv" || no "CSV content-type" "wrong"
grep -qi 'content-disposition: attachment' /tmp/wwt-gate-csv.h && ok "downloads as a file" || no "CSV disposition" "missing"
[ "$(head -c 3 "$CSV" | xxd -p)" = "efbbbf" ] && ok "UTF-8 BOM present (Excel reads ₹ correctly)" \
  || no "CSV BOM" "missing — Excel would mangle the rupee sign"
python3 - "$CSV" <<'PYX'
import csv, sys
rows = list(csv.reader(open(sys.argv[1], encoding="utf-8-sig")))
hdr, body = rows[0], rows[1:]
def out(good, label, detail=""):
    print(("  [32mPASS[0m  " if good else "  [31mFAIL[0m  ") + label + ("" if good else " — " + detail))
out(len(set(hdr)) == len(hdr), "column names are unique",
    "duplicates: " + ",".join(sorted({h for h in hdr if hdr.count(h) > 1})))
out(all(len(r) == len(hdr) for r in body), "every row has the right number of cells")
out(len(body) >= 5, "rows exported (%d)" % len(body))
msg = [r for r in body if "₹" in ",".join(r)]
out(bool(msg), "rupee sign survives the round trip")
out(any("’" in ",".join(r) for r in body), "curly quote survives the round trip")
bad = [c for r in body for c in r if c and c[0] in "=+-@"]
out(not bad, "no cell can execute as a spreadsheet formula", "unescaped: %r" % bad[:3])
PYX
csvpass=$(python3 - "$CSV" <<'PYX'
import csv, sys
rows = list(csv.reader(open(sys.argv[1], encoding="utf-8-sig")))
hdr, body = rows[0], rows[1:]
checks = [len(set(hdr)) == len(hdr), all(len(r) == len(hdr) for r in body), len(body) >= 5,
          any("₹" in ",".join(r) for r in body), any("’" in ",".join(r) for r in body),
          not [c for r in body for c in r if c and c[0] in "=+-@"]]
print("%d %d" % (sum(checks), len(checks) - sum(checks)))
PYX
)
pass=$((pass + $(echo "$csvpass" | cut -d' ' -f1)))
fail=$((fail + $(echo "$csvpass" | cut -d' ' -f2)))

echo; echo "── 9. Mailbox settings (owner-editable) ───────────────"
stok=$(curl -s -b "$JAR" "$BASE/admin/?p=settings" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR" -o /dev/null -X POST --data-urlencode "_csrf=$stok" \
  --data-urlencode "action=mail" --data-urlencode "host=127.0.0.1" --data-urlencode "port=2525" \
  --data-urlencode "secure=none" --data-urlencode "user=no-reply@wwwebtech.in" \
  --data-urlencode "pass=gate-secret-password" --data-urlencode "from_name=Wwwebtech website" \
  --data-urlencode "lead_email=contact@wwwebtech.in" --data-urlencode "reply_promise=1 business day" \
  --data-urlencode "lead_ack_enabled=1" "$BASE/admin/?p=settings"

stored=$(q "SELECT v FROM wwt_settings WHERE k='smtp_pass'")
case "$stored" in
  enc:v1:*) ok "mailbox password is encrypted at rest" ;;
  *)        no "mailbox password at rest" "stored as '$stored'" ;;
esac
has "$stored" "gate-secret-password" \
  && no "password not in plain text" "the plaintext is in the database" \
  || ok "plaintext password is nowhere in the database"

# Saving the form again with a blank password must not wipe the stored one.
stok=$(curl -s -b "$JAR" "$BASE/admin/?p=settings" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR" -o /dev/null -X POST --data-urlencode "_csrf=$stok" \
  --data-urlencode "action=mail" --data-urlencode "host=127.0.0.1" --data-urlencode "port=2525" \
  --data-urlencode "secure=none" --data-urlencode "user=no-reply@wwwebtech.in" \
  --data-urlencode "pass=" --data-urlencode "from_name=Wwwebtech website" \
  --data-urlencode "lead_email=contact@wwwebtech.in" --data-urlencode "reply_promise=1 business day" \
  --data-urlencode "lead_ack_enabled=1" "$BASE/admin/?p=settings"
chk "blank password field keeps the saved one" \
  "$(q "SELECT v FROM wwt_settings WHERE k='smtp_pass'")" "$stored"

# An unticked checkbox sends nothing at all, so the handler has to read its
# absence as "off" rather than "unchanged" — and then put it back.
stok=$(curl -s -b "$JAR" "$BASE/admin/?p=settings" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR" -o /dev/null -X POST --data-urlencode "_csrf=$stok" \
  --data-urlencode "action=mail" --data-urlencode "host=127.0.0.1" --data-urlencode "port=2525" \
  --data-urlencode "secure=none" --data-urlencode "user=no-reply@wwwebtech.in" \
  --data-urlencode "from_name=Wwwebtech website" --data-urlencode "lead_email=contact@wwwebtech.in" \
  --data-urlencode "reply_promise=1 business day" "$BASE/admin/?p=settings"
chk "unticking the acknowledgement box turns it off" \
  "$(q "SELECT v FROM wwt_settings WHERE k='lead_ack_enabled'")" "0"
stok=$(curl -s -b "$JAR" "$BASE/admin/?p=settings" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR" -o /dev/null -X POST --data-urlencode "_csrf=$stok" \
  --data-urlencode "action=mail" --data-urlencode "host=127.0.0.1" --data-urlencode "port=2525" \
  --data-urlencode "secure=none" --data-urlencode "user=no-reply@wwwebtech.in" \
  --data-urlencode "from_name=Wwwebtech website" --data-urlencode "lead_email=contact@wwwebtech.in" \
  --data-urlencode "reply_promise=1 business day" --data-urlencode "lead_ack_enabled=1" "$BASE/admin/?p=settings"
chk "and ticking it turns it back on" \
  "$(q "SELECT v FROM wwt_settings WHERE k='lead_ack_enabled'")" "1"

chk "the panel never echoes the password back" \
  "$(curl -s -b "$JAR" "$BASE/admin/?p=settings" | grep -c 'gate-secret-password')" "0"

before=$(ls -1 "$MAILDIR"/*.eml 2>/dev/null | wc -l | tr -d ' ')
stok=$(curl -s -b "$JAR" "$BASE/admin/?p=settings" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR" -o /dev/null -X POST --data-urlencode "_csrf=$stok" \
  --data-urlencode "action=mail_test" "$BASE/admin/?p=settings"
sleep 1
after=$(ls -1 "$MAILDIR"/*.eml 2>/dev/null | wc -l | tr -d ' ')
[ "$after" -gt "$before" ] && ok "\"Send me a test email\" delivers using the saved password" \
  || no "test email" "nothing was delivered"

echo; echo "───────────────────────────────────────────────────────"
printf "  %d passed, %d failed\n\n" "$pass" "$fail"
[ "$fail" -eq 0 ]
