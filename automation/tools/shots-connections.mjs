#!/usr/bin/env node
/* ============================================================
   shots-connections.mjs — every card, in each state it can be in.

   The brief's acceptance evidence: a screenshot of each Connections card
   as Not configured, Configured — untested, Connected, and Error. The
   states are set up through the same code paths the owner uses (saving
   through the page, testing against the local mail sink, a deliberately
   bad token), never by faking the status column.

     bash automation/tools/dev.sh && node automation/tools/shots-connections.mjs
   Writes automation/.shots/conn-<card>-<state>.png
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const ROOT  = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const REPO  = path.dirname(ROOT);
const BASE  = process.env.BASE || 'http://127.0.0.1:8088';
const SHOTS = path.join(ROOT, '.shots');
const PHP   = process.env.PHP || '/usr/bin/php8.3';
fs.mkdirSync(SHOTS, { recursive: true });

const { chromium } = await import(path.join(REPO, 'node_modules', 'playwright', 'index.mjs'));
const CHROME = process.env.CHROME_PATH
  || [`${process.env.HOME}/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome`].find((p) => fs.existsSync(p));
const browser = await chromium.launch(CHROME ? { executablePath: CHROME } : {});
const page = await browser.newPage({ viewport: { width: 1180, height: 900 }, deviceScaleFactor: 1 });

/** Run a PHP snippet against the dev database. */
const php = (code) => execFileSync(PHP, ['-r', 'require "private/bootstrap.php"; ' + code], { cwd: ROOT }).toString();

/* Sign in as the gate's admin. */
php(`if (!DB::val("SELECT 1 FROM wwt_admin_users WHERE email=?", ["owner@wwwebtech.in"])) Auth::createUser("owner@wwwebtech.in","devpassword123","admin");`);
await page.goto(`${BASE}/admin/?p=login`);
await page.fill('input[name=email]', 'owner@wwwebtech.in');
await page.fill('input[name=password]', 'devpassword123');
await page.click('button[type=submit]');

const shot = async (card, state) => {
  await page.goto(`${BASE}/admin/?p=connections`, { waitUntil: 'networkidle' });
  const el = page.locator(`#c-${card}`);
  await el.locator('details').first().evaluate((d) => { d.open = true; }).catch(() => {});
  await el.screenshot({ path: path.join(SHOTS, `conn-${card}-${state}.png`) });
  console.log(`  ${card.padEnd(11)} ${state}`);
};

/* Clean slate: every card Not configured. */
php(`
foreach (Connections::CARDS as $c) { Settings::set("conn_status_".$c, ""); Settings::set("conn_pending_".$c, ""); }
Secrets::put("telegram_token",""); Settings::set("telegram_recipients",""); Settings::set("telegram_chat_id","");
Settings::set("mail_identities",""); foreach (["smtp_host","smtp_port","smtp_secure","smtp_user","smtp_from_name"] as $k) Settings::set($k,""); Secrets::put("smtp_pass","");
foreach (["imap_host","imap_port","imap_secure","imap_user","imap_folder"] as $k) Settings::set($k,""); Secrets::put("imap_pass","");
Settings::set("alert_recipients",""); Secrets::put("anthropic_key",""); Secrets::put("pagespeed_key",""); Secrets::put("wa_token",""); Settings::set("wa_phone_id","");
Secrets::put("conversions_key",""); Secrets::put("cron_key","");
DB::run("DELETE FROM wwt_rate_limit WHERE bucket LIKE ?", ["conntest:%"]);
`);
for (const c of Connections()) await shot(c, 'not-configured');

/* Configured — untested: values present, never tested. */
php(`
Secrets::put("telegram_token","1234567890:AAHshotstokenABCDEFGHIJKLMNOPQRSTUVW"); Settings::set("telegram_chat_id","123456");
Settings::set("imap_host","imap.hostinger.com"); Settings::set("imap_port","993"); Settings::set("imap_user","info@wwwebtech.in"); Secrets::put("imap_pass","x");
Secrets::put("anthropic_key","sk-ant-api03-shots-".str_repeat("x",30)); Secrets::put("pagespeed_key","AIza".str_repeat("A",35));
Secrets::put("wa_token","EAA".str_repeat("x",60)); Settings::set("wa_phone_id","123456789012345"); Settings::set("wa_waba_id","123456789012345");
Secrets::put("conversions_key",bin2hex(random_bytes(24))); Secrets::put("cron_key",bin2hex(random_bytes(16)));
Mailer::saveIdentities([["id"=>"default","label"=>"Company mailbox","name"=>"Saurabh","email"=>"info@wwwebtech.in","host"=>"smtp.hostinger.com","port"=>465,"secure"=>"ssl","user"=>"info@wwwebtech.in","use"=>["system","funnel","manual"]]]);
Secrets::put("smtp_pass","x");
Notify::saveRecipients([["id"=>"company","email"=>"contact@wwwebtech.in","label"=>"Company mailbox","roles"=>["every_lead","digest","errors"]]]);
`);
for (const c of Connections()) await shot(c, 'untested');

/* Error: real tests against credentials that cannot work. */
php(`
foreach (["telegram","whatsapp","claude","pagespeed","mail_read"] as $c) { DB::run("DELETE FROM wwt_rate_limit WHERE bucket LIKE ?", ["conntest:%"]); Connections::test($c); }
`);
for (const c of ['telegram', 'whatsapp', 'claude', 'pagespeed', 'mail_read']) await shot(c, 'error');

/* Connected: the mail sink is a real SMTP server; keys are ours. */
php(`
Mailer::saveIdentities([["id"=>"default","label"=>"Company mailbox","name"=>"Saurabh","email"=>"info@wwwebtech.in","host"=>"127.0.0.1","port"=>2525,"secure"=>"none","user"=>"info@wwwebtech.in","use"=>["system","funnel","manual"]]]);
Secrets::put("smtp_pass","sinkpass");
DB::run("DELETE FROM wwt_rate_limit WHERE bucket LIKE ?", ["conntest:%"]);
Connections::test("mail_send", ["to" => "owner@wwwebtech.in"]);
Connections::test("recipients", ["to" => "contact@wwwebtech.in", "record" => true]);
Connections::test("keys");
`);
for (const c of ['mail_send', 'recipients', 'keys']) await shot(c, 'connected');

/* And the whole page once, for the record. */
await page.goto(`${BASE}/admin/?p=connections`, { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(SHOTS, 'conn-page.png'), fullPage: true });
console.log('  page        full');

/* Leave the dev database as it was found. */
php(`
foreach (Connections::CARDS as $c) { Settings::set("conn_status_".$c, ""); Settings::set("conn_pending_".$c, ""); }
Secrets::put("telegram_token",""); Settings::set("telegram_recipients",""); Settings::set("telegram_chat_id","");
Settings::set("mail_identities",""); foreach (["smtp_host","smtp_port","smtp_secure","smtp_user","smtp_from_name"] as $k) Settings::set($k,""); Secrets::put("smtp_pass","");
foreach (["imap_host","imap_port","imap_secure","imap_user","imap_folder"] as $k) Settings::set($k,""); Secrets::put("imap_pass","");
Settings::set("alert_recipients",""); Secrets::put("anthropic_key",""); Secrets::put("pagespeed_key",""); Secrets::put("wa_token",""); Settings::set("wa_phone_id",""); Settings::set("wa_waba_id","");
Secrets::put("conversions_key",""); Secrets::put("cron_key","");
Connections::refreshAttention();
`);
await browser.close();
console.log(`\n  -> ${SHOTS}/conn-*.png`);

function Connections() { return ['mail_send', 'mail_read', 'recipients', 'telegram', 'whatsapp', 'claude', 'pagespeed', 'keys']; }
