<?php
/* ============================================================
   wwwebtech.in — private configuration.

   COPY THIS FILE TO  config.php  AND FILL IT IN.
   It must live OUTSIDE public_html (see DEPLOY.md), normally at
       /home/USERNAME/wwt_private/config.php
   Nothing in here is ever sent to a browser.
   ============================================================ */

declare(strict_types=1);

return [

  /* ── Database ────────────────────────────────────────────
     Create these in hPanel → Databases → MySQL Databases.
     Hostinger prefixes both with your account id, e.g. uXXXXXXXXX_wwt. */
  'db' => [
    'host'    => 'localhost',
    'name'    => 'REPLACE_DB_NAME',
    'user'    => 'REPLACE_DB_USER',
    'pass'    => 'REPLACE_DB_PASSWORD',
    'charset' => 'utf8mb4',
  ],

  /* ── Site ────────────────────────────────────────────────*/
  'site' => [
    'url'       => 'https://wwwebtech.in',
    'webroot'   => '/home/REPLACE_USER/domains/wwwebtech.in/public_html',
    'timezone'  => 'Asia/Kolkata',
    'lead_email'=> 'contact@wwwebtech.in',
  ],

  /* ── Secrets ─────────────────────────────────────────────
     GENERATE FRESH VALUES. Never reuse the examples.
       php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'   ← cron_key
       php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   ← salts     */
  /* secret_key encrypts anything the panel has to store in the database
     (the mailbox password, API keys). It lives here, outside the database
     and outside the web root, so a stolen SQL dump is not enough on its own. */
  'secret_key'    => 'CHANGE-ME-32-BYTES-HEX',

  'cron_key'      => 'REPLACE_WITH_32_HEX_CHARS',
  'session_salt'  => 'REPLACE_WITH_64_HEX_CHARS',
  'geo_salt'      => 'REPLACE_WITH_64_HEX_CHARS',

  /* ── Outbound email (SMTP) ───────────────────────────────
     [SETUP-2] Create the mailbox in hPanel → Emails, then paste here.
     Never PHP mail() — it lands in spam and cannot be authenticated. */
  'smtp' => [
    'host'      => 'smtp.hostinger.com',
    'port'      => 465,
    'secure'    => 'ssl',              // 'ssl' for 465, 'tls' for 587
    'user'      => 'no-reply@wwwebtech.in',
    'pass'      => 'REPLACE_MAILBOX_PASSWORD',
    'from_name' => 'Wwwebtech website',
  ],

  /* ── Anthropic (blog generation) ─────────────────────────
     [SETUP-1] console.anthropic.com → API keys.
     Set a monthly spend limit there as well as the cap below.
     The model id is editable in Admin → Settings; this is the fallback. */
  'anthropic' => [
    'api_key'         => '',                        // sk-ant-...
    'model'           => 'claude-opus-5',
    'monthly_cap_usd' => 15.00,
  ],

  /* ── Optional integrations ───────────────────────────────*/
  'pagespeed_api_key' => '',   // [SETUP-4] free, Google Cloud console
  'gsc_service_json'  => '',   // [SETUP-5] absolute path to service-account .json
  'indexnow_key'      => '',   // auto-generated on first seo_daily run if blank

  /* ── Behaviour ───────────────────────────────────────────*/
  'debug' => false,            // true prints errors — NEVER true in production
];
