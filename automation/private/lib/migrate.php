<?php
/* ============================================================
   migrate.php — schema top-ups that run themselves.

   The brief is explicit that the owner never runs SQL. schema.sql
   creates everything for a fresh install; this file brings an
   ALREADY-INSTALLED database up to the current shape when a later
   phase adds a column or an index.

   Both operations are checked against information_schema first, so
   this is safe to call on every request and works identically on
   MySQL 5.7/8.0 and MariaDB (neither supports a portable
   "ADD COLUMN IF NOT EXISTS").
   ============================================================ */

declare(strict_types=1);

/** Bump this when you add a step below. */
const WWT_SCHEMA_VERSION = 5;

final class Schema
{
    public static function hasTable(string $table): bool
    {
        return (int)DB::val(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table], 0) > 0;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return (int)DB::val(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column], 0) > 0;
    }

    public static function hasIndex(string $table, string $index): bool
    {
        return (int)DB::val(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index], 0) > 0;
    }

    /**
     * Add a column if it is missing. $definition is trusted DDL from THIS
     * file only — it is never built from user input, which is why it can be
     * interpolated where a placeholder is impossible.
     */
    public static function addColumn(string $table, string $column, string $definition): bool
    {
        if (!self::hasTable($table) || self::hasColumn($table, $column)) return false;
        self::assertIdent($table); self::assertIdent($column);
        // SAFE-SQL: identifiers, not values — a placeholder is impossible in DDL.
        // $table and $column are validated by assertIdent() above; $definition is a
        // literal from this file and never comes from input.
        DB::pdo()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        wwt_log('migrate', "added $table.$column");
        return true;
    }

    public static function addIndex(string $table, string $index, string $columns): bool
    {
        if (!self::hasTable($table) || self::hasIndex($table, $index)) return false;
        self::assertIdent($table); self::assertIdent($index);
        // SAFE-SQL: as above — validated identifiers, literal column list.
        DB::pdo()->exec("ALTER TABLE `$table` ADD INDEX `$index` ($columns)");
        wwt_log('migrate', "added index $table.$index");
        return true;
    }

    /**
     * Create the tables on a fresh install.
     *
     * The brief is explicit that the owner never runs SQL, and importing a
     * schema through phpMyAdmin is exactly the step where a deploy goes
     * wrong silently. So the panel does it: on first run, if the tables are
     * not there, schema.sql is executed here.
     *
     * schema.sql is entirely CREATE TABLE IF NOT EXISTS, so this is safe to
     * call whether or not the database is already set up.
     *
     * @return int number of statements executed
     */
    public static function install(): int
    {
        $file = WWT_PRIVATE . '/schema.sql';
        if (!is_file($file)) {
            throw new RuntimeException('schema.sql is missing from the private folder — see DEPLOY.md.');
        }
        $sql = (string)file_get_contents($file);

        /* Strip comments, then split on semicolons. The schema deliberately
           contains no stored procedures or triggers, so there are no
           semicolons inside a statement body to worry about. */
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $n = 0;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') continue;
            DB::pdo()->exec($stmt);
            $n++;
        }
        wwt_log('migrate', 'schema installed', ['statements' => $n]);
        return $n;
    }

    /** True when the database has not been set up at all yet. */
    public static function needsInstall(): bool
    {
        try { return !self::hasTable('wwt_admin_users'); }
        catch (Throwable) { return false; }   // a connection problem is a different error
    }

    private static function assertIdent(string $s): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $s)) {
            throw new InvalidArgumentException('Unsafe identifier: ' . $s);
        }
    }
}

/**
 * Run any outstanding steps. Cheap no-op once up to date: one settings
 * read that is already cached per request.
 */
function wwt_migrate(bool $force = false): int
{
    /* A completely empty database means a fresh install, not a broken one. */
    if (Schema::needsInstall()) {
        Schema::install();
        audit('schema_install', 'tables created on first run');
    }

    $have = Settings::int('schema_version', 0);
    if (!$force && $have >= WWT_SCHEMA_VERSION) return 0;

    $done = 0;

    /* ── v2 · Phase 2, leads ──────────────────────────────────
       Delivery is tracked per lead so a mail failure is visible in the
       panel instead of silent. `is_test` lets QA submissions be flagged
       and purged without touching real enquiries. */
    $done += (int)Schema::addColumn('wwt_leads', 'mail_status',
        "ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending' AFTER ip_trunc");
    $done += (int)Schema::addColumn('wwt_leads', 'mail_error',
        "VARCHAR(255) NOT NULL DEFAULT '' AFTER mail_status");
    $done += (int)Schema::addColumn('wwt_leads', 'is_test',
        "TINYINT(1) NOT NULL DEFAULT 0 AFTER mail_error");
    $done += (int)Schema::addColumn('wwt_leads', 'updated_at',
        "DATETIME NULL AFTER is_test");
    $done += (int)Schema::addIndex('wwt_leads', 'idx_leads_test', 'is_test, ts');

    /* ── v3 · Phase 3, the conversion funnel ──────────────────
       Everything the landing pages already POST but the leads table had
       nowhere to put. The click IDs matter most: without them an offline
       conversion cannot attribute back to the ad that paid for the lead,
       which is the entire point of the ads feedback loop. */
    foreach ([
        'gclid'        => "VARCHAR(200) NOT NULL DEFAULT '' AFTER utm_campaign",
        'gbraid'       => "VARCHAR(200) NOT NULL DEFAULT '' AFTER gclid",
        'wbraid'       => "VARCHAR(200) NOT NULL DEFAULT '' AFTER gbraid",
        'msclkid'      => "VARCHAR(200) NOT NULL DEFAULT '' AFTER wbraid",
        'fbclid'       => "VARCHAR(200) NOT NULL DEFAULT '' AFTER msclkid",
        'utm_term'     => "VARCHAR(160) NOT NULL DEFAULT '' AFTER utm_campaign",
        'utm_content'  => "VARCHAR(160) NOT NULL DEFAULT '' AFTER utm_term",
        'landing_page' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER page",
        'variant'      => "CHAR(1) NOT NULL DEFAULT '' AFTER landing_page",
        'timeline'     => "VARCHAR(20) NOT NULL DEFAULT '' AFTER budget",
        'has_site'     => "VARCHAR(3) NOT NULL DEFAULT '' AFTER timeline",
        'site_url'     => "VARCHAR(255) NOT NULL DEFAULT '' AFTER has_site",
        /* Band is stored, not derived at read time, so changing the
           thresholds later cannot silently rewrite history. */
        'score'        => "SMALLINT NOT NULL DEFAULT 0 AFTER status",
        'band'         => "ENUM('hot','warm','cold') NOT NULL DEFAULT 'cold' AFTER score",
        'dwell_secs'   => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER band",
        'pages_seen'   => "SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER dwell_secs",
        'is_partial'   => "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_test",
        'consent_at'   => "DATETIME NULL AFTER is_partial",
        'consent_text' => "VARCHAR(40) NOT NULL DEFAULT '' AFTER consent_at",
        'do_not_contact' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER consent_text",
        'session_hash' => "CHAR(64) NOT NULL DEFAULT '' AFTER do_not_contact",
    ] as $col => $def) {
        $done += (int)Schema::addColumn('wwt_leads', $col, $def);
    }
    $done += (int)Schema::addIndex('wwt_leads', 'idx_leads_band',    'band, ts');
    $done += (int)Schema::addIndex('wwt_leads', 'idx_leads_lp',      'landing_page, ts');
    $done += (int)Schema::addIndex('wwt_leads', 'idx_leads_partial', 'is_partial, ts');
    $done += (int)Schema::addIndex('wwt_leads', 'idx_leads_gclid',   'gclid(32)');

    /* "Meeting Scheduled" sits between Contacted and Qualified. It is the
       point at which automation must stop, so it has to be a status the
       code can test, not a note someone typed. */
    if (Schema::hasTable('wwt_leads')) {
        $col = DB::one("SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wwt_leads'
                          AND COLUMN_NAME = 'status'");
        if ($col && !str_contains((string)$col['t'], 'meeting')) {
            // SAFE-SQL: no values here at all — a fixed ENUM definition.
            DB::pdo()->exec("ALTER TABLE wwt_leads MODIFY status
                ENUM('new','contacted','meeting','qualified','won','lost')
                NOT NULL DEFAULT 'new'");
            $done++;
        }
    }

    /* ── v4 · The funnel tables ───────────────────────────────
       schema.sql is entirely CREATE TABLE IF NOT EXISTS, so running it
       again is how an existing install picks up new tables. No separate
       migration script to drift out of step with the fresh-install path. */
    if ($have < 4) {
        $before = (int)DB::val('SELECT COUNT(*) FROM information_schema.TABLES
                                WHERE TABLE_SCHEMA = DATABASE()', [], 0);
        Schema::install();
        $after = (int)DB::val('SELECT COUNT(*) FROM information_schema.TABLES
                               WHERE TABLE_SCHEMA = DATABASE()', [], 0);
        $done += max(0, $after - $before);
    }

    /* ── v5 · The audit tool ──────────────────────────────────
       The report is addressed to a person, so the name is asked for at
       the form and carried on the audit row until a lead exists. */
    $done += (int)Schema::addColumn('wwt_audits', 'name',
        "VARCHAR(100) NOT NULL DEFAULT '' AFTER host");

    Settings::set('schema_version', (string)WWT_SCHEMA_VERSION);
    if ($done) audit('schema_migrate', 'to v' . WWT_SCHEMA_VERSION . ', ' . $done . ' change(s)');
    return $done;
}
