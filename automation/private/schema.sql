-- ============================================================
--  wwwebtech.in — automation layer schema
--  MySQL 5.7+ / MariaDB 10.3+   ·   InnoDB   ·   utf8mb4
--
--  Import once:  mysql -u USER -p DBNAME < schema.sql
--  Safe to re-run: every statement is IF NOT EXISTS.
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── Leads ──────────────────────────────────────────────────
-- Every contact-form submission. `ip_trunc` is a /24 (v4) or /48 (v6)
-- prefix only — we never store a full address (privacy, DPDP).
CREATE TABLE IF NOT EXISTS wwt_leads (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ts            DATETIME        NOT NULL,
  name          VARCHAR(100)    NOT NULL,
  email         VARCHAR(150)    NOT NULL,
  phone         VARCHAR(30)     NOT NULL DEFAULT '',
  service       VARCHAR(120)    NOT NULL DEFAULT '',
  budget        VARCHAR(40)     NOT NULL DEFAULT '',
  message       TEXT            NOT NULL,
  company       VARCHAR(120)    NOT NULL DEFAULT '',
  page          VARCHAR(190)    NOT NULL DEFAULT '',
  referrer      VARCHAR(255)    NOT NULL DEFAULT '',
  utm_source    VARCHAR(80)     NOT NULL DEFAULT '',
  utm_medium    VARCHAR(80)     NOT NULL DEFAULT '',
  utm_campaign  VARCHAR(120)    NOT NULL DEFAULT '',
  country       CHAR(2)         NOT NULL DEFAULT '',
  status        ENUM('new','contacted','qualified','won','lost') NOT NULL DEFAULT 'new',
  notes         TEXT            NULL,
  ip_trunc      VARCHAR(45)     NOT NULL DEFAULT '',
  -- Delivery is tracked per lead so a mail failure shows in the panel
  -- rather than losing an enquiry quietly.
  mail_status   ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  mail_error    VARCHAR(255)    NOT NULL DEFAULT '',
  is_test       TINYINT(1)      NOT NULL DEFAULT 0,
  updated_at    DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_leads_ts      (ts),
  KEY idx_leads_test    (is_test, ts),
  KEY idx_leads_status  (status, ts),
  KEY idx_leads_source  (utm_source, ts),
  KEY idx_leads_email   (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Raw analytics hits ─────────────────────────────────────
-- High-volume. Pruned to `hits_retention_days` (settings) by cron;
-- the rollups below are what the panel actually reads.
CREATE TABLE IF NOT EXISTS wwt_hits (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ts            DATETIME        NOT NULL,
  path          VARCHAR(190)    NOT NULL,
  ref_domain    VARCHAR(120)    NOT NULL DEFAULT '',
  utm_source    VARCHAR(80)     NOT NULL DEFAULT '',
  utm_medium    VARCHAR(80)     NOT NULL DEFAULT '',
  utm_campaign  VARCHAR(120)    NOT NULL DEFAULT '',
  country       CHAR(2)         NOT NULL DEFAULT '',
  device        ENUM('desktop','mobile','tablet','bot','unknown') NOT NULL DEFAULT 'unknown',
  is_bot        TINYINT(1)      NOT NULL DEFAULT 0,
  bot_name      VARCHAR(40)     NOT NULL DEFAULT '',
  session_hash  CHAR(64)        NOT NULL DEFAULT '',
  event         VARCHAR(40)     NOT NULL DEFAULT 'pageview',
  event_detail  VARCHAR(255)    NOT NULL DEFAULT '',
  ip_trunc      VARCHAR(45)     NOT NULL DEFAULT '',
  is_test       TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_hits_ts       (ts),
  KEY idx_hits_path     (path, ts),
  KEY idx_hits_bot      (is_bot, bot_name, ts),
  KEY idx_hits_session  (session_hash, ts),
  KEY idx_hits_event    (event, ts),
  KEY idx_hits_country  (country, ts),
  KEY idx_hits_geo      (ip_trunc, country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Daily rollups ──────────────────────────────────────────
-- Kept forever. One row per (date, path, source, country, device).
CREATE TABLE IF NOT EXISTS wwt_daily_rollups (
  d           DATE          NOT NULL,
  path        VARCHAR(190)  NOT NULL,
  source      VARCHAR(120)  NOT NULL DEFAULT 'direct',
  country     CHAR(2)       NOT NULL DEFAULT '',
  device      VARCHAR(10)   NOT NULL DEFAULT 'unknown',
  views       INT UNSIGNED  NOT NULL DEFAULT 0,
  visitors    INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (d, path, source, country, device),
  KEY idx_roll_date (d),
  KEY idx_roll_src  (d, source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Country lookup cache ───────────────────────────────────
-- Truncated-prefix → country. Populated best-effort by cron.
CREATE TABLE IF NOT EXISTS wwt_geo_cache (
  ip_trunc    VARCHAR(45) NOT NULL,
  country     CHAR(2)     NOT NULL DEFAULT '',
  region      VARCHAR(80) NOT NULL DEFAULT '',
  resolved_at DATETIME    NOT NULL,
  PRIMARY KEY (ip_trunc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Blog posts ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_posts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(160)    NOT NULL,
  title         VARCHAR(200)    NOT NULL,
  dek           VARCHAR(400)    NOT NULL DEFAULT '',
  html_path     VARCHAR(255)    NOT NULL DEFAULT '',
  cluster       VARCHAR(40)     NOT NULL DEFAULT '',
  status        ENUM('queued','published','unpublished','failed') NOT NULL DEFAULT 'queued',
  published_at  DATETIME        NULL,
  created_at    DATETIME        NOT NULL,
  model         VARCHAR(60)     NOT NULL DEFAULT '',
  tokens_in     INT UNSIGNED    NOT NULL DEFAULT 0,
  tokens_out    INT UNSIGNED    NOT NULL DEFAULT 0,
  cost_usd      DECIMAL(10,5)   NOT NULL DEFAULT 0,
  word_count    INT UNSIGNED    NOT NULL DEFAULT 0,
  topic_id      BIGINT UNSIGNED NULL,
  first_para    TEXT            NULL,
  reject_reason VARCHAR(255)    NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_status (status, published_at),
  KEY idx_posts_cluster (cluster, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Topic bank ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_topics (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cluster     VARCHAR(40)     NOT NULL,
  title_seed  VARCHAR(200)    NOT NULL,
  angle       VARCHAR(400)    NOT NULL DEFAULT '',
  sort_order  INT             NOT NULL DEFAULT 0,
  used_at     DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_topics_pick (used_at, cluster, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Settings (tags, toggles, thresholds) ───────────────────
CREATE TABLE IF NOT EXISTS wwt_settings (
  k           VARCHAR(80)  NOT NULL,
  v           TEXT         NOT NULL,
  updated_at  DATETIME     NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SEO check results ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_seo_checks (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  d           DATE            NOT NULL,
  check_name  VARCHAR(60)     NOT NULL,
  status      ENUM('ok','warn','fail','info') NOT NULL DEFAULT 'ok',
  detail      TEXT            NULL,
  score       TINYINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_seo_date (d, check_name),
  KEY idx_seo_status (status, d)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Core Web Vitals samples ────────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_cwv (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  d         DATE            NOT NULL,
  url       VARCHAR(190)    NOT NULL,
  strategy  ENUM('mobile','desktop') NOT NULL DEFAULT 'mobile',
  lcp_ms    INT UNSIGNED    NULL,
  cls       DECIMAL(6,4)    NULL,
  inp_ms    INT UNSIGNED    NULL,
  perf      TINYINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_cwv (d, url, strategy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Admin users ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_admin_users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(150) NOT NULL,
  pass_hash       VARCHAR(255) NOT NULL,
  role            ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
  totp_secret     VARCHAR(64)  NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME     NULL,
  last_login      DATETIME     NULL,
  created_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Audit log ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_audit_log (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ts      DATETIME     NOT NULL,
  user    VARCHAR(150) NOT NULL DEFAULT 'system',
  action  VARCHAR(60)  NOT NULL,
  detail  TEXT         NULL,
  ip_trunc VARCHAR(45) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_audit_ts (ts),
  KEY idx_audit_action (action, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cron heartbeat ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_task_runs (
  task        VARCHAR(40)  NOT NULL,
  last_start  DATETIME     NULL,
  last_end    DATETIME     NULL,
  last_status ENUM('ok','fail','running') NOT NULL DEFAULT 'ok',
  last_error  TEXT         NULL,
  run_count   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (task)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rate limiting (leads + login), DB-backed so it survives ──
CREATE TABLE IF NOT EXISTS wwt_rate_limit (
  bucket    VARCHAR(120) NOT NULL,
  hits      INT UNSIGNED NOT NULL DEFAULT 0,
  window_at DATETIME     NOT NULL,
  PRIMARY KEY (bucket),
  KEY idx_rl_window (window_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Funnel tables (§6.1). Added in schema v4.
--  Every statement is IF NOT EXISTS, so this file stays safe to re-run
--  and the migration can simply execute it again on an existing install.
-- ============================================================

-- ── The lead timeline ──────────────────────────────────────
-- Every open, click, reply, status change and message, in one place.
-- This is what the Conversations inbox renders and what the score reads.
CREATE TABLE IF NOT EXISTS wwt_lead_events (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id    BIGINT UNSIGNED NOT NULL,
  ts         DATETIME        NOT NULL,
  kind       VARCHAR(40)     NOT NULL,
  channel    VARCHAR(20)     NOT NULL DEFAULT '',
  detail     VARCHAR(500)    NOT NULL DEFAULT '',
  actor      VARCHAR(150)    NOT NULL DEFAULT 'system',
  meta       TEXT            NULL,
  PRIMARY KEY (id),
  KEY idx_ev_lead (lead_id, ts),
  KEY idx_ev_kind (kind, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Messages, both directions ──────────────────────────────
-- cost_paise is stored per message so the WhatsApp meter is counted from
-- what was actually sent, not estimated after the fact.
CREATE TABLE IF NOT EXISTS wwt_messages (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id       BIGINT UNSIGNED NOT NULL,
  ts            DATETIME        NOT NULL,
  direction     ENUM('out','in') NOT NULL DEFAULT 'out',
  channel       ENUM('email','whatsapp','telegram','note') NOT NULL DEFAULT 'email',
  subject       VARCHAR(255)    NOT NULL DEFAULT '',
  body          MEDIUMTEXT      NULL,
  template_key  VARCHAR(60)     NOT NULL DEFAULT '',
  provider_id   VARCHAR(190)    NOT NULL DEFAULT '',
  in_reply_to   VARCHAR(190)    NOT NULL DEFAULT '',
  status        ENUM('queued','pending_review','sent','failed','received','skipped')
                                NOT NULL DEFAULT 'queued',
  error         VARCHAR(255)    NOT NULL DEFAULT '',
  cost_paise    INT UNSIGNED    NOT NULL DEFAULT 0,
  is_ai         TINYINT(1)      NOT NULL DEFAULT 0,
  approved_by   VARCHAR(150)    NOT NULL DEFAULT '',
  send_after    DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_msg_lead   (lead_id, ts),
  KEY idx_msg_status (status, send_after),
  KEY idx_msg_prov   (provider_id),
  KEY idx_msg_cost   (channel, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sequences and their steps ──────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_sequences (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  key_name    VARCHAR(40)  NOT NULL,
  title       VARCHAR(120) NOT NULL DEFAULT '',
  description VARCHAR(400) NOT NULL DEFAULT '',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  -- Review-before-send is per sequence and OFF-auto by default (§0.4).
  auto_send   TINYINT(1)   NOT NULL DEFAULT 0,
  auto_after_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seq_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wwt_sequence_steps (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sequence_id    INT UNSIGNED NOT NULL,
  step_no        SMALLINT UNSIGNED NOT NULL,
  offset_minutes INT NOT NULL DEFAULT 0,
  business_hours TINYINT(1)   NOT NULL DEFAULT 1,
  channel        ENUM('email','whatsapp','internal') NOT NULL DEFAULT 'email',
  template_key   VARCHAR(60)  NOT NULL DEFAULT '',
  purpose        VARCHAR(400) NOT NULL DEFAULT '',
  condition_expr VARCHAR(120) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_step (sequence_id, step_no),
  KEY idx_step_seq (sequence_id, step_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Who is in what, and why they stopped ───────────────────
CREATE TABLE IF NOT EXISTS wwt_sequence_enrollments (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id     BIGINT UNSIGNED NOT NULL,
  sequence_id INT UNSIGNED    NOT NULL,
  step        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_run_at DATETIME        NULL,
  status      ENUM('active','paused','done','stopped') NOT NULL DEFAULT 'active',
  stop_reason VARCHAR(120)    NOT NULL DEFAULT '',
  started_at  DATETIME        NOT NULL,
  ended_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrol (lead_id, sequence_id),
  KEY idx_enrol_due (status, next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Templates: the static safety net, and the AI drafts ─────
CREATE TABLE IF NOT EXISTS wwt_templates (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  key_name      VARCHAR(60)  NOT NULL,
  channel       ENUM('email','whatsapp','telegram') NOT NULL DEFAULT 'email',
  -- Category matters: a marketing-category WhatsApp template costs several
  -- times a utility one in India, so the sending path refuses anything but
  -- utility (§5.2).
  category      ENUM('utility','marketing','authentication') NOT NULL DEFAULT 'utility',
  subject       VARCHAR(255) NOT NULL DEFAULT '',
  body          TEXT         NOT NULL,
  is_ai         TINYINT(1)   NOT NULL DEFAULT 0,
  meta_name     VARCHAR(120) NOT NULL DEFAULT '',
  approval      ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  updated_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tpl (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Consent, recorded as given ─────────────────────────────
CREATE TABLE IF NOT EXISTS wwt_consents (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id     BIGINT UNSIGNED NOT NULL,
  ts          DATETIME     NOT NULL,
  channel     VARCHAR(20)  NOT NULL DEFAULT 'all',
  granted     TINYINT(1)   NOT NULL DEFAULT 1,
  text_version VARCHAR(40) NOT NULL DEFAULT '',
  wording     TEXT         NULL,
  ip_trunc    VARCHAR(45)  NOT NULL DEFAULT '',
  source      VARCHAR(60)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_consent_lead (lead_id, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wwt_bookings (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id    BIGINT UNSIGNED NULL,
  ts         DATETIME     NOT NULL,
  starts_at  DATETIME     NULL,
  source     VARCHAR(40)  NOT NULL DEFAULT '',
  ref        VARCHAR(190) NOT NULL DEFAULT '',
  status     VARCHAR(20)  NOT NULL DEFAULT 'booked',
  PRIMARY KEY (id),
  KEY idx_book_lead (lead_id, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Offline conversions waiting to be fetched by the ad platforms ──
CREATE TABLE IF NOT EXISTS wwt_ad_conversion_queue (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id      BIGINT UNSIGNED NOT NULL,
  ts           DATETIME     NOT NULL,
  platform     ENUM('google','microsoft','meta','ga4') NOT NULL DEFAULT 'google',
  action       VARCHAR(60)  NOT NULL,
  click_id     VARCHAR(200) NOT NULL DEFAULT '',
  value_inr    DECIMAL(12,2) NOT NULL DEFAULT 0,
  occurred_at  DATETIME     NOT NULL,
  fetched_at   DATETIME     NULL,
  status       ENUM('queued','fetched','skipped') NOT NULL DEFAULT 'queued',
  skip_reason  VARCHAR(120) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_conv (lead_id, platform, action),
  KEY idx_conv_status (platform, status, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── The free audit tool's job queue and reports ────────────
CREATE TABLE IF NOT EXISTS wwt_audits (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ts          DATETIME     NOT NULL,
  url         VARCHAR(255) NOT NULL,
  host        VARCHAR(190) NOT NULL DEFAULT '',
  name        VARCHAR(100) NOT NULL DEFAULT '',
  email       VARCHAR(150) NOT NULL DEFAULT '',
  phone       VARCHAR(30)  NOT NULL DEFAULT '',
  lead_id     BIGINT UNSIGNED NULL,
  token       CHAR(32)     NOT NULL,
  status      ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  score       TINYINT UNSIGNED NULL,
  results     MEDIUMTEXT   NULL,
  error       VARCHAR(255) NOT NULL DEFAULT '',
  started_at  DATETIME     NULL,
  finished_at DATETIME     NULL,
  emailed_at  DATETIME     NULL,
  ip_trunc    VARCHAR(45)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_audit_token (token),
  KEY idx_audit_status (status, ts),
  KEY idx_audit_host (host, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
