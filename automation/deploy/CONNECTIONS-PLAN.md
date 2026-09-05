# Connections hub — Phase 1: what exists, what moves where

Written 5 September 2026 after reading every file the hub touches and
inventorying the production server. No code has been written. This is the
plan the brief's Phase 1 gate asks you to nod at — or correct.

---

## 1 · What I found

### 1.1 · The pieces the brief assumes exist — and do

| Piece | Where | State |
|---|---|---|
| **Encryption** | `private/lib/secrets.php` | AES-256-GCM, key derived (SHA-256) from `secret_key` in `config.php`, falling back to `session_salt`. `Secrets::put()` / `get()` wrap the settings store; a value that fails to decrypt returns `''` and logs, never throws. Good — reuse as is. |
| **Settings store** | `wwt_settings (k, v, updated_at)` + `Settings` class in `db.php` | Key/value with a per-request cache. **No "who set it" column** — that comes from the audit log. |
| **Audit log** | `wwt_audit_log (ts, user, action, detail, ip_trunc)` via `audit()` | Every panel mutation already writes here. "Set by X on date" for a card = the newest audit row for that key. |
| **CSRF / roles** | `Csrf::require()` in `admin/index.php`; `Auth::isAdmin()` / `requireAdmin()` | Route-level gating is a list in `index.php`; page-level `requireAdmin()` guards writes inside pages viewers may read. Nav hides admin-only items for viewers. |
| **Rate limiting** | `RateLimit::allow(bucket, max, window)` | Reusable for the 30-second Test throttle. |
| **Job runner** | `Jobs::registry()` / `groups()` / `run()`; heartbeat in `wwt_task_runs` | `daily` group exists to hang `connections_health` on. |
| **Mailer** | `Mailer::settings()` reads `smtp_*` from Settings, falling back to `config.smtp`; password from `Secrets('smtp_pass')` then `config.smtp.pass` | `send()` accepts `from_email`, `from_name`, `reply_to`, `unsubscribe` — but **always authenticates with the one configured mailbox**. |
| **Telegram** | `Telegram::token()` (secret), `chatId()` (setting), `send()`, `test()` | `sendMessage` only. No `getMe`, no `getUpdates`, one recipient. |
| **WhatsApp** | `WhatsApp::token()`, `phoneId()`, cap, window, `sendTemplate()`, `sendSession()` | API version hard-coded `v21.0`. No WABA id, app id, app secret, verify token, webhook, or template registry. Template category is checked against a local list, not Meta's. |
| **Inbox (IMAP)** | `Inbox::host/port/user/pass()`, `poll()` with UID watermark | Fixed `INBOX`, fixed SSL. |
| **Claude** | `Claude::apiKey()` (secret then config), `model()`, `monthlyCap()`, `spentThisMonth()`, **`testKey()` already exists** | Spend is tracked per call; the cap is `blog_monthly_cap_usd`. |
| **PageSpeed** | `Seo::checkVitals()` reads `Secrets('pagespeed_key')` then config | No standalone test — the call is inside the vitals check. |
| **Conversions key** | `Secrets('conversions_key')`, generated on the Integrations page | Already hub-shaped: masked, rotate button, URLs shown. |

### 1.2 · Where every secret lives today (production, names only)

| Secret | Today | Read by |
|---|---|---|
| Mailbox password | **`config.php` → `smtp.pass`** (the encrypted store's `smtp_pass` is empty; so are `smtp_host/port/user`) | `Mailer` |
| IMAP password | encrypted store `imap_pass` (copied from config yesterday, server-side) | `Inbox` |
| Anthropic key | encrypted store `anthropic_key` | `Claude` |
| PageSpeed key | encrypted store `pagespeed_key` | `Seo` |
| Telegram token | encrypted store `telegram_token` | `Telegram` |
| Conversions feed key | encrypted store `conversions_key` | `api/conversions.php` |
| `cron_key` | **`config.php`** | `api/lead.php`, `api/hit.php` — as the **test-submission flag** only |
| `secret_key`, `session_salt`, `geo_salt`, DB credentials | `config.php` | stay there (the brief's exception) |
| `indexnow_key`, `gsc_service_json` | `config.php`, both empty | `Seo` |

So the one-time migration is small: **SMTP settings and the mailbox password, and the cron key.** Everything else is already in the store.

### 1.3 · Where the brief's assumptions differ from reality

1. **The cron lines do not contain the cron key.** They are `php run.php frequent|hourly|daily|weekly` — CLI, authenticated by being on the server. `cron_key` is only the secret that marks a form submission as a *test* (used by the QA gates and the "send a flagged test" path). Rotating it changes nothing in hPanel. I propose the card calls it what it is — **"Test-submission key"** — with Rotate, and drops the "paste these new cron lines" step. The cron lines themselves can still be *displayed* on the card, read-only, as the reference the owner keeps losing.
2. **One SMTP account, not a table.** `Mailer::send()` can set any From name/address on a message, but signs in with one mailbox. Hostinger (and Gmail, Zoho, M365) will reject or spam-flag a From address the authenticated mailbox is not allowed to send as. So "sender identities as a table" means each identity carries **its own SMTP login**, and `Mailer::send()` gains an `identity` option. That is a real change to the mailer, kept backward-compatible: identity `default` = today's behaviour.
3. **Alert recipients are two fixed slots** (`lead_email`, `personal_email`) hard-wired into `Notify::newLead()`. The list replaces them; the two current values seed it, and `Notify` iterates the list by role instead of naming channels.
4. **`Secrets::mask()` shows dots only.** The brief wants `••••••••7Fk2` — last four visible. That is a deliberate, small disclosure; it needs a one-line change and I will make it, since the brief asks for it explicitly.
5. **DNS on wwwebtech.in today:** SPF present (`include:_spf.mail.hostinger.com ~all`); DMARC present but **`p=none`** (monitor only); DKIM present at Hostinger's selector **`hostingermail-a`**, not `default`. So the DKIM probe must know Hostinger's selectors (`hostingermail-a`, `hostingermail-b`) alongside Google's and Zoho's, or it will report ✗ on a domain that is fine. `dns_get_record` is available on the host. The strip's first honest finding will be: *DMARC is set to `p=none` — consider `p=quarantine` once SPF and DKIM have been green for a month.*
6. **Business verification has an API** (`GET /{business_id}?fields=verification_status`, needs `business_management`). I will try it and fall back to the self-reported field the brief describes, so the gate can be automatic where Meta allows it.
7. **A WhatsApp webhook is more than a status light.** Once inbound messages arrive, they belong in `wwt_messages` as `direction = 'in'` — which means a customer's WhatsApp reply **stops their sequence**, the same way an email reply does. That is the single most valuable side-effect of this build and I will wire it, not just verify the handshake.

---

## 2 · What moves where

One page, `/admin/?p=connections`, nine cards. Storage stays in the existing
store: plain fields as `conn_<card>_<field>`, secrets via `Secrets::put()`,
lists (identities, recipients, Telegram chats, templates) as JSON settings
with an id per row and each row's secret under its own key. Per-card status
(`state`, `checked_at`, `reason`) as `conn_<card>_status` JSON, written by
tests and by the health job. No new tables; one new API endpoint (the
WhatsApp webhook). The old keys the transports read today are **kept and
written through**, so nothing else in the codebase changes its reads.

| Card | Today | After | Migration |
|---|---|---|---|
| Email — sending | Settings → Mailbox (5 fields) + `config.smtp` | Identity table; identity `default` writes today's `smtp_*` keys + `smtp_pass`; presets; DNS strip; send-me-a-test with SPF/DKIM read-back via IMAP | Copy `config.smtp.*` → store; blank `config.smtp.pass` (host/port/user stay as the documented fallback shape but empty) |
| Email — reading replies | Settings → Reading replies | Same fields + encryption + folder; test lists 3 subjects + watermark | none (already in store) |
| Alert recipients | `lead_email`, `personal_email` | JSON list with roles; `Notify` iterates it | Seed from the two values |
| Telegram | Settings → Telegram alerts | Token + **Detect my chat** (`getUpdates`) + recipient list with roles; test = `getMe` then `sendMessage` | Seed list from `telegram_chat_id` |
| WhatsApp | Settings → WhatsApp (token, phone id, cap, on/off) | Six credentials + display number + API version + verification status + webhook block + template registry with Sync | Existing token/phone id carried over |
| Claude | Blog page (key field) + Settings (model, cap) | Key, model, cap with live meter (blog + funnel); test = `Claude::testKey()` | none |
| PageSpeed | SEO health page (key field) | Key; test = one PSI call on the homepage | none |
| Feed & test keys | Integrations (conversions key) + `config.cron_key` | Both masked with Rotate; cron lines shown read-only | `cron_key` → store; `api/lead.php` and `api/hit.php` read `Secrets` |
| Turnstile (optional) | — | Site key + secret; test; toggle wires the widget into the public forms | none |

**Leaves the old pages:** the Mailbox, Telegram, WhatsApp and Reading-replies
sections of Settings; the key fields on Blog, SEO health and Integrations.
Each is replaced by one line: *"Managed on the Connections page →"*.
Settings keeps account, 2FA, users, follow-up and scoring, data retention.

**New code, by file:**

- `private/lib/connections.php` — the card registry (fields, regexes, guide
  steps, presets), status read/write, the Test dispatcher, the 30-second
  throttle, health run, fallback alerting.
- `private/lib/telegram.php` — `getMe()`, `getUpdates()` → distinct chats,
  multi-recipient send by role.
- `private/lib/whatsapp.php` — configurable API version, `checkPhone()`,
  `listTemplates()` + sync, `verifyWebhook()`, `verifySignature()`,
  inbound → `wwt_messages`.
- `private/lib/mailer.php` — identities; `send(['identity' => …])`;
  `dnsHealth()`; `authResultsOf()` (reads `Authentication-Results` of the
  test message over IMAP).
- `private/lib/notify.php` — recipients by role.
- `webroot/api/whatsapp-webhook.php` — GET handshake, POST with signature.
- `webroot/admin/pages/connections.php` — the page; `connections_test.php`
  is not a separate endpoint, tests are POST actions on the page.
- `private/lib/jobs.php` — `connections_health` in `daily`; `migrate.php`
  v6 for the one-time migration.
- Docs: `OWNER-GUIDE.md` chapter "Connecting things"; `FUNNEL-SETUP.md`,
  `WHATSAPP-SETUP.md` point at the page; `DEPLOY.md` loses "edit config.php"
  for anything but the database and the encryption key, and gains the
  backup/restore note.

---

## 3 · Phases, as the brief lists them, with the launch call

| # | Delivers | Gate |
|---|---|---|
| 1 | This document | **you nod** |
| 2 | Hub skeleton, Email sending (identities, presets, DNS strip, test with SPF/DKIM read-back), Email reading, Alert recipients; old sections replaced with links | Test mail arrives from each identity; IMAP test shows real subjects; DNS strip shows wwwebtech.in's true state (SPF ✓, DKIM ✓ at `hostingermail-a`, DMARC `p=none`) |
| 3 | Telegram card with Detect-my-chat and roles | From blank, a person following only the card gets a message on their phone in under five minutes — timed. **I cannot create a Telegram bot; this needs you and BotFather (2 minutes). I will time it with you.** |
| 4 | WhatsApp card, webhook endpoint with signature check, template registry + sync, inbound → thread | Handshake against the generated token (simulated); unsigned payload rejected; *Not configured* state correct with the guide visible |
| 5 | Claude, PageSpeed, keys, Turnstile; migration v6 | Every feature re-tested after migration; `grep` finds no secret outside the store; `config.php` holds only DB + encryption key + salts |
| 6 | `connections_health`, Dashboard summary, nav dot, fallback alert, docs | Break one connection on purpose, run the job, see red + the fallback alert; fix, see green |
| 7 | Security pass, `gate-security` extended, `--dry-run` deploy | No secret in HTML, log, error or test output; viewer sees status pills only |

**Launch (12 September):** phases 2 and 3 carry launch value and are small.
Plan: 2–3 done and deployed by Sunday 7 September; 4–7 the following week.
If anything slips, Telegram can still be configured through today's Settings
fields — they keep working until the moment their replacement is live.

---

## 4 · Decisions I need from you before writing code

1. **Cron key** — rename the card "Feed & test keys" and drop the "new cron
   lines" step, since the lines don't contain it? (§1.3.1)
2. **Sender identities** — each identity carries its own SMTP login (§1.3.2).
   Start with one identity (`info@wwwebtech.in`, used for everything) and
   let you add a `no-reply@` later, or seed both now? A `no-reply@` mailbox
   does not exist on Hostinger yet.
3. **Last-four masking** — confirm you want `••••••••7Fk2` rather than dots
   only (§1.3.4).
4. **WhatsApp inbound replies stop sequences** — confirm you want that wired
   in phase 4, not just the handshake (§1.3.7). I recommend yes.
5. **Turnstile** — build it in phase 5, or leave it out until spam is
   actually a problem? The forms have a honeypot and rate limits today and
   nothing has got through. I recommend leaving it out of this build.

Everything else in the brief I will build as written.
