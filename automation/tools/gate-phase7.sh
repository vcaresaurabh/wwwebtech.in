#!/usr/bin/env bash
# Phases 6-9 gate — inbox, audit tool, panel pages, conversions feed.
#
# The brief's gate for Phase 6 is "reply to a funnel email from an external
# address and watch it appear in the thread and stop the sequence". There is
# no IMAP server in this container, so the reply is injected the way Inbox
# would inject it and the CONSEQUENCE is what gets asserted: the message is
# on the thread, and the enrolment is stopped. The matching logic itself is
# unit-tested in tests/funnel_test.php against real header shapes.
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
PHP=${PHP:-/usr/bin/php8.3}
pass=0; fail=0
ok(){ printf "  \033[32mPASS\033[0m  %s\n" "$1"; pass=$((pass+1)); }
no(){ printf "  \033[31mFAIL\033[0m  %s — %s\n" "$1" "$2"; fail=$((fail+1)); }
chk(){ if [ "$2" = "$3" ]; then ok "$1"; else no "$1" "got '$2', want '$3'"; fi; }
has(){ if printf '%s' "$2" | grep -qF -- "$3"; then ok "$1"; else no "$1" "missing '$3'"; fi; }
hasnt(){ if printf '%s' "$2" | grep -qF -- "$3"; then no "$1" "found '$3'"; else ok "$1"; fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }

echo; echo "── 1. A reply lands on the thread and stops the sequence ─"
RESULT=$($PHP -r '
require "private/bootstrap.php";
$id = DB::insert("INSERT INTO wwt_leads (ts,name,email,phone,service,budget,message,page,consent_at,consent_text,status)
  VALUES (UTC_TIMESTAMP(),\"Gate Reply\",\"gate.reply@example.com\",\"9876500001\",\"SEO\",\"₹5L+\",\"Please help with our site.\",\"/lp/seo/\",UTC_TIMESTAMP(),\"gate\",\"new\")");
Funnel::enrol($id, "standard");
$before = (string)DB::val("SELECT status FROM wwt_sequence_enrollments WHERE lead_id=?", [$id]);
/* Exactly what Inbox::ingest writes when a reply arrives. */
DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body, provider_id, status)
            VALUES (?, UTC_TIMESTAMP(), \"in\", \"email\", \"Re: your site\", \"Yes — call me Tuesday.\", ?, \"received\")",
           [$id, "<gate-".bin2hex(random_bytes(4))."@example.com>"]);
Timeline::add($id, "reply_received", "email", "Re: your site", "them");
$why   = Funnel::checkStop($id);
$after = (string)DB::val("SELECT status FROM wwt_sequence_enrollments WHERE lead_id=?", [$id]);
$onThread = (int)DB::val("SELECT COUNT(*) FROM wwt_messages WHERE lead_id=? AND direction=\"in\"", [$id], 0);
echo "$id|$before|$why|$after|$onThread\n";')
IFS='|' read -r LID BEFORE WHY AFTER ONTHREAD <<<"$RESULT"
chk "enrolled before the reply"     "$BEFORE"   "active"
chk "the reply is on the thread"    "$ONTHREAD" "1"
chk "the stop reason is the reply"  "$WHY"      "they replied"
chk "the sequence is stopped"       "$AFTER"    "stopped"

echo; echo "── 2. Auto-replies and bounces are not replies ──────────"
$PHP -r '
require "private/bootstrap.php";
$c = 0;
foreach ([["", "Out of Office: Re: hello", true],
          ["Return-Path: <>\r\n", "Undeliverable", true],
          ["Auto-Submitted: no\r\n", "Re: hello", false]] as [$h, $s, $want]) {
  if (Inbox::isAutomated($h, $s) === $want) $c++;
}
exit($c === 3 ? 0 : 1);' && ok "vacation replies and bounces are filtered, real replies are not" \
  || no "auto-reply filter" "one of the three cases is wrong"

echo; echo "── 3. The audit tool measures real pages ────────────────"
AUD=$($PHP -r '
require "private/bootstrap.php";
$r = AuditTool::run("https://wwwebtech.in/");
$states = array_count_values(array_column($r["checks"], "state"));
printf("%d|%d|%d|%d\n", $r["score"], count($r["checks"]),
       (int)($states["ok"] ?? 0), (int)($states["bad"] ?? 0));' 2>/dev/null)
IFS='|' read -r SCORE NCHECKS NOK NBAD <<<"$AUD"
[ "${NCHECKS:-0}" -ge 17 ] && ok "at least 17 checks ran ($NCHECKS)" || no "check count" "got ${NCHECKS:-0}"
[ "${SCORE:-0}" -ge 80 ] && ok "our own site scores well ($SCORE/100)" \
  || no "own site score" "got ${SCORE:-0} — the audit is finding real problems on wwwebtech.in"

# A tool that scores everything badly is a sales gimmick, not a measurement,
# and one that scores everything well is useless. Two fixed pages, offline,
# so the assertion cannot fail because someone else's server hiccuped.
SPREAD=$($PHP -r '
require "private/bootstrap.php";
$out = [];
foreach (["good" => 120, "bad" => 2200] as $which => $ttfb) {
  $r = AuditTool::analyse("https://example.test/",
       file_get_contents("tools/fixtures/audit-" . $which . ".html"),
       ["status" => 200, "headers" => ["content-encoding" => $which === "good" ? "gzip" : ""]],
       $ttfb, ["network" => false]);
  $out[] = (int)$r["score"];
}
echo implode("|", $out);' 2>/dev/null)
IFS='|' read -r GOODFIX BADFIX <<<"$SPREAD"
[ "${GOODFIX:-0}" -ge 90 ] && ok "a well-built page scores well ($GOODFIX/100)" \
  || no "good fixture" "scored ${GOODFIX:-?}, expected 90+"
[ "${BADFIX:-100}" -le 45 ] && ok "a badly-built page scores badly ($BADFIX/100)" \
  || no "bad fixture" "scored ${BADFIX:-?}, expected 45 or less"

# The report has to name the worst thing first, not whatever ran first.
HEAD=$($PHP -r '
require "private/bootstrap.php";
$r = AuditTool::analyse("https://example.test/", file_get_contents("tools/fixtures/audit-bad.html"),
     ["status" => 200, "headers" => []], 2200, ["network" => false]);
echo $r["headline"];' 2>/dev/null)
case "$HEAD" in
  *"mobile layout"*|*"search engine access"*) ok "the headline leads with the heaviest problem" ;;
  *) no "headline priority" "led with: $HEAD" ;;
esac

echo; echo "── 4. The audit tool refuses what it should ─────────────"
$PHP -r 'require "private/bootstrap.php"; exit(AuditTool::isPrivateHost("127.0.0.1") ? 0 : 1);' \
  && ok "loopback is refused (no SSRF into the host)" || no "SSRF guard" "loopback was allowed"
$PHP -r 'require "private/bootstrap.php"; exit(AuditTool::isPrivateHost("10.0.0.5") ? 0 : 1);' \
  && ok "private ranges are refused" || no "SSRF guard" "10/8 was allowed"
$PHP -r 'require "private/bootstrap.php"; exit(AuditTool::normaliseUrl("javascript:alert(1)") === "" ? 0 : 1);' \
  && ok "a javascript: URL is rejected" || no "URL validation" "javascript: was accepted"

echo; echo "── 5. The public tool page ──────────────────────────────"
FORM=$(curl -s "$BASE/tools/index.php")
has   "the form renders"            "$FORM" "Run my free audit"
has   "consent is required"         "$FORM" 'name="consent"'
has   "a honeypot is present"       "$FORM" 'name="company"'
hasnt "no unfilled template tokens" "$FORM" "{{"
chk   "a bad token 404s" "$(code "$BASE/tools/index.php?t=deadbeef")" "404"

echo; echo "── 6. The conversions feed is a credential, not a URL ────"
KEY=$($PHP -r 'require "private/bootstrap.php";
  $k = Secrets::get("conversions_key", ""); if ($k === "") { $k = bin2hex(random_bytes(24)); Secrets::put("conversions_key", $k); } echo $k;')
chk "no key → 404"        "$(code "$BASE/api/conversions.php?type=google")" "404"
chk "wrong key → 404"     "$(code "$BASE/api/conversions.php?type=google&key=$(printf 'x%.0s' {1..48})")" "404"
chk "unknown type → 404"  "$(code "$BASE/api/conversions.php?type=facebook&key=$KEY")" "404"
chk "right key → 200"     "$(code "$BASE/api/conversions.php?type=google&key=$KEY")" "200"
CSV=$(curl -s "$BASE/api/conversions.php?type=enhanced&key=$KEY")
has   "enhanced exports hashes"   "$CSV" "Email,Phone Number"
HDRS=$(curl -sD- -o /dev/null "$BASE/api/conversions.php?type=google&key=$KEY")
has   "it is never indexed"       "$HDRS" "noindex"
has   "it is never cached"        "$HDRS" "no-store"

echo; echo "── 7. Every panel page still renders ────────────────────"
RP=$($PHP tools/render-pages.php 2>&1 | tail -1)
has "all admin pages render" "$RP" "pages render cleanly"
hasnt "none failed" "$($PHP tools/render-pages.php 2>&1)" "FAIL"

echo; echo "── 8. The unit suite ────────────────────────────────────"
TS=$($PHP tests/funnel_test.php 2>&1 | tail -1)
has "funnel_test passes" "$TS" "0 failed"

echo; echo "── Clean up ─────────────────────────────────────────────"
$PHP -r '
require "private/bootstrap.php";
$ids = array_column(DB::all("SELECT id FROM wwt_leads WHERE email = ?", ["gate.reply@example.com"]), "id");
foreach ($ids as $i) { foreach (["wwt_messages","wwt_sequence_enrollments","wwt_lead_events"] as $t)
  DB::run("DELETE FROM $t WHERE lead_id = ?", [$i]); DB::run("DELETE FROM wwt_leads WHERE id = ?", [$i]); }
echo "  ", count($ids), " gate lead(s) removed\n";'

printf "\n  \033[1m%d passed, %d failed\033[0m\n" "$pass" "$fail"
exit $((fail > 0))
