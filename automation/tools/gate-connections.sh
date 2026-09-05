#!/usr/bin/env bash
# Connections hub gate — the page, the rules, the webhook, and the
# guarantee that no secret ever reaches a browser.
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
BASE=${BASE:-http://127.0.0.1:8088}
PHP=${PHP:-/usr/bin/php8.3}
pass=0; fail=0
ok(){ printf "  \033[32mPASS\033[0m  %s\n" "$1"; pass=$((pass+1)); }
no(){ printf "  \033[31mFAIL\033[0m  %s — %s\n" "$1" "$2"; fail=$((fail+1)); }
chk(){ if [ "$2" = "$3" ]; then ok "$1"; else no "$1" "got '$2', want '$3'"; fi; }
# Here-strings, never a pipe: with pipefail, grep -q exiting on the first
# match sends printf a SIGPIPE and a match near the TOP of a long page
# reads as a failure. Bitten by this once before in this repository.
has(){ if grep -qF -- "$3" <<<"$2"; then ok "$1"; else no "$1" "missing '$3'"; fi; }
hasnt(){ if grep -qF -- "$3" <<<"$2"; then no "$1" "found '$3'"; else ok "$1"; fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }

# Accounts the gate signs in with. Created if missing, never emailed.
$PHP -r '
require "private/bootstrap.php";
foreach ([["owner@wwwebtech.in","devpassword123","admin"],["viewer@wwwebtech.in","devpassword123","viewer"]] as [$e,$p,$r]) {
  if (!DB::val("SELECT 1 FROM wwt_admin_users WHERE email=?", [$e])) Auth::createUser($e, $p, $r);
}
/* Point the reader at the local sink so a "mail" test can pass offline. */
foreach (["telegram","whatsapp","claude","pagespeed","mail_send","mail_read"] as $c) Settings::set("conn_status_".$c, "");
DB::run("DELETE FROM wwt_rate_limit WHERE bucket LIKE ?", ["conntest:%"]);'
ADMIN=/tmp/conn-admin.jar; VIEWER=/tmp/conn-viewer.jar
bash tools/login.sh "$ADMIN" owner@wwwebtech.in devpassword123 >/dev/null
bash tools/login.sh "$VIEWER" viewer@wwwebtech.in devpassword123 >/dev/null
csrf(){ curl -s -b "$1" "$BASE/admin/?p=connections" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post(){ # jar action extra-args...
  local jar=$1 act=$2; shift 2
  curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -X POST --data-urlencode "_csrf=$(csrf "$jar")" --data-urlencode "action=$act" "$@" "$BASE/admin/?p=connections"
}
page(){ curl -s -b "$1" "$BASE/admin/?p=connections"; }

echo; echo "── 1. The page ─────────────────────────────────────────"
P=$(page "$ADMIN")
for t in "Email — sending" "Email — reading replies" "Alert recipients" "Telegram" "WhatsApp" "Claude (Anthropic)" "PageSpeed Insights" "Feed &amp; test keys"; do
  has "card present: $t" "$P" "$t"
done
has "the guides are in the page" "$P" "BotFather"
has "the webhook URL is shown copy-ready" "$P" "/api/whatsapp-webhook.php"
has "the verify token is shown (it is ours to generate)" "$P" "Verify token"
V=$(page "$VIEWER")
has "a viewer sees the status table" "$V" "Read-only accounts see the status"
hasnt "a viewer sees no form" "$V" 'name="action"'
hasnt "a viewer sees no secret hint" "$V" "••••"

echo; echo "── 2. CSRF and roles ───────────────────────────────────"
chk "a POST without a token is refused" "$(curl -s -b "$ADMIN" -o /dev/null -w '%{http_code}' -X POST -d 'action=rotate_test_key' "$BASE/admin/?p=connections")" "419"
chk "a viewer cannot rotate a key" "$(post "$VIEWER" rotate_test_key)" "403"

echo; echo "── 3. Telegram: format, error translation, no leak ─────"
TOK="1234567890:AAHgatetokenABCDEFGHIJKLMNOPQRSTUVW"
post "$ADMIN" save_telegram --data-urlencode "token=not-a-token" >/dev/null
P=$(page "$ADMIN"); has "a malformed token is refused at paste time" "$P" "A bot token is 8"
post "$ADMIN" save_telegram --data-urlencode "token=$TOK" >/dev/null
P=$(page "$ADMIN")
has "a well-formed token saves as untested" "$P" "Configured — untested"
hasnt "the token itself is not in the page" "$P" "$TOK"
has "only the last four show" "$P" "••••••••$(printf '%s' "$TOK" | tail -c 4)"
has "who set it is shown" "$P" "by owner@wwwebtech.in"
post "$ADMIN" test_telegram >/dev/null
P=$(page "$ADMIN")
has "the fake token fails the real test" "$P" "Test failed"
has "and the failure is in plain English" "$P" "Telegram rejected this token"
has "with a link to the guide step" "$P" "#g-telegram-1"
hasnt "the error never echoes the token" "$P" "$TOK"
chk "a second test within 30s is throttled" "$(post "$ADMIN" test_telegram)" "303"
P=$(page "$ADMIN"); has "and says so" "$P" "Tested a moment ago"

echo; echo "── 4. Email: the local sink connects; IMAP without a server errors ─"
$PHP -r 'require "private/bootstrap.php"; DB::run("DELETE FROM wwt_rate_limit WHERE bucket LIKE ?", ["conntest:%"]);'
post "$ADMIN" save_identity --data-urlencode "id=default" --data-urlencode "label=Local sink" --data-urlencode "name=Gate" \
  --data-urlencode "email=gate@wwwebtech.in" --data-urlencode "preset=other" --data-urlencode "host=127.0.0.1" \
  --data-urlencode "port=2525" --data-urlencode "secure=none" --data-urlencode "user=gate@wwwebtech.in" \
  --data-urlencode "password=sinkpass" --data-urlencode "use[]=system" --data-urlencode "use[]=funnel" >/dev/null
P=$(page "$ADMIN")
has "the mailbox saved and tested against the sink" "$P" "the mailbox works"
has "sending shows Connected" "$P" 'id="c-mail_send"'
has "the DNS strip renders for the real domain" "$P" "hostingermail-a"
has "DMARC p=none is called out honestly" "$P" "p=none"
n_before=$(ls .dev/mail | wc -l); sleep 1
$PHP -r 'require "private/bootstrap.php"; DB::run("DELETE FROM wwt_rate_limit WHERE bucket LIKE ?", ["conntest:%"]);'
post "$ADMIN" test_identity --data-urlencode "id=default" >/dev/null; sleep 1
[ "$(ls .dev/mail | wc -l)" -gt "$n_before" ] && ok "send-me-a-test delivered a real message to the sink" || no "send-me-a-test" "no new .eml in the sink"
post "$ADMIN" save_mail_read --data-urlencode "preset=other" --data-urlencode "host=127.0.0.1" --data-urlencode "port=1" \
  --data-urlencode "secure=none" --data-urlencode "user=gate@wwwebtech.in" --data-urlencode "password=x" >/dev/null
post "$ADMIN" test_mail_read >/dev/null
P=$(page "$ADMIN")
# This container has no IMAP extension, so the closed-port path cannot run
# here; either explanation is the honest one for the environment it ran in.
if grep -qF -- "Could not reach the server" <<<"$P" || grep -qF -- "IMAP extension is not installed" <<<"$P"; then
  ok "an unreachable or unavailable IMAP reader is explained"; else no "IMAP explanation" "neither phrasing found"; fi

echo; echo "── 5. Recipients ───────────────────────────────────────"
post "$ADMIN" add_recipient --data-urlencode "email=phone@example.com" --data-urlencode "label=Phone" --data-urlencode "roles[]=hot_only" >/dev/null
P=$(page "$ADMIN"); has "a recipient is added with its role" "$P" "Hot leads only"
RID=$($PHP -r 'require "private/bootstrap.php"; foreach (Notify::recipients() as $r) if ($r["email"]==="phone@example.com") echo $r["id"];')
post "$ADMIN" test_recipient --data-urlencode "id=$RID" >/dev/null
has "send-test to a recipient goes through the sink" "$(page "$ADMIN")" "Test passed"
post "$ADMIN" remove_recipient --data-urlencode "id=$RID" >/dev/null
hasnt "and can be removed" "$(page "$ADMIN")" "phone@example.com"

echo; echo "── 6. WhatsApp webhook ─────────────────────────────────"
$PHP -r 'require "private/bootstrap.php"; Secrets::put("wa_app_secret","0123456789abcdef0123456789abcdef"); echo Settings::get("wa_verify_token","");' > /tmp/wa-vt.txt
VT=$(cat /tmp/wa-vt.txt)
chk "handshake with the right token echoes the challenge" "$(curl -s "$BASE/api/whatsapp-webhook.php?hub_mode=subscribe&hub_verify_token=$VT&hub_challenge=12345")" "12345"
chk "handshake with a wrong token is refused" "$(code "$BASE/api/whatsapp-webhook.php?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=1")" "403"
has "the card now shows the webhook as verified" "$(page "$ADMIN")" "verified"
BODY='{"object":"whatsapp_business_account","entry":[]}'
chk "an unsigned POST is refused" "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'content-type: application/json' -d "$BODY" "$BASE/api/whatsapp-webhook.php")" "403"
SIG="sha256=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac 0123456789abcdef0123456789abcdef | sed 's/^.* //')"
chk "a correctly signed POST is accepted" "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'content-type: application/json' -H "X-Hub-Signature-256: $SIG" -d "$BODY" "$BASE/api/whatsapp-webhook.php")" "200"
$PHP -r 'require "private/bootstrap.php"; Secrets::put("wa_app_secret","");'

echo; echo "── 7. Migration is idempotent; health runs ─────────────"
chk "running the migration again changes nothing" "$($PHP -r 'require "private/bootstrap.php"; echo wwt_migrate();')" "0"
H=$($PHP -r 'require "private/bootstrap.php"; echo Connections::health();')
has "the health job reports per card" "$H" "Email"
RP=$($PHP tools/render-pages.php 2>&1)
grep -qE "ok +connections .* bytes" <<<"$RP" && ok "render-pages covers the page" || no "render-pages" "$(grep -E 'FAIL|connections' <<<"$RP" | head -2)"

echo; echo "── 8. No secret anywhere a browser can see ─────────────"
$PHP -r 'require "private/bootstrap.php"; Secrets::put("anthropic_key","sk-ant-gate-secret-VALUE-abcdefghijklmnop");'
for p in connections dashboard blog seo integrations settings; do
  hasnt "secret absent from /admin/?p=$p" "$(curl -s -b "$ADMIN" "$BASE/admin/?p=$p")" "sk-ant-gate-secret-VALUE"
done
$PHP -r 'require "private/bootstrap.php"; Secrets::put("anthropic_key","");'

echo; echo "── Clean up ────────────────────────────────────────────"
$PHP -r '
require "private/bootstrap.php";
Secrets::put("telegram_token",""); Settings::set("telegram_recipients",""); Settings::set("telegram_chat_id","");
Settings::set("mail_identities",""); foreach (["smtp_host","smtp_port","smtp_secure","smtp_user","smtp_from_name"] as $k) Settings::set($k,""); Secrets::put("smtp_pass","");
foreach (["imap_host","imap_port","imap_secure","imap_user","imap_folder"] as $k) Settings::set($k,""); Secrets::put("imap_pass","");
Settings::set("alert_recipients",""); Settings::set("wa_webhook_verified_at","");
foreach (Connections::CARDS as $c) { Settings::set("conn_status_".$c, ""); Settings::set("conn_pending_".$c, ""); }
Connections::refreshAttention();
DB::run("DELETE FROM wwt_rate_limit WHERE bucket LIKE ?", ["conntest:%"]);
DB::run("DELETE FROM wwt_admin_users WHERE email = ?", ["viewer@wwwebtech.in"]);
echo "  reset\n";'

printf "\n  \033[1m%d passed, %d failed\033[0m\n" "$pass" "$fail"
exit $((fail > 0))
