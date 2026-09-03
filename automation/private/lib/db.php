<?php
/* ============================================================
   db.php — the only place that talks to MySQL.

   One lazily-opened PDO connection, exceptions on, emulation off,
   and helpers that take bound parameters ONLY. If you find yourself
   concatenating a value into SQL anywhere in this codebase, that is
   the bug — every value goes through a placeholder.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/util.php';

final class DB
{
    private static ?PDO $pdo = null;
    private static array $cfg = [];

    /* Tracked here rather than asked of PDO: inTransaction() on a dead
       handle is not reliable, and this is consulted precisely when the
       handle may be dead. */
    private static bool $inTransaction = false;

    public static function configure(array $dbConfig): void
    {
        self::$cfg = $dbConfig;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $c = self::$cfg;
        if (!$c) throw new RuntimeException('DB::configure() was never called.');

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $c['host'] ?? 'localhost',
            $c['name'] ?? '',
            $c['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements, not client-side interpolation.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $ex) {
            // The message can contain the password — never let it reach a browser.
            wwt_log('db', 'connection failed', ['code' => $ex->getCode()]);
            throw new RuntimeException('Database connection failed.', 0, $ex);
        }
        return self::$pdo;
    }

    /**
     * Run a statement, reconnecting once if the connection has died.
     *
     * Shared hosting closes idle MySQL connections after a short timeout, and
     * several jobs here hold one open across a slow HTTP call — writing a
     * blog post takes over a minute against the API, and PageSpeed can take
     * ninety seconds. Without this, the query AFTER the wait fails with
     * "MySQL server has gone away" and the result of the work is lost. That
     * is an article that was generated, paid for, and never stored.
     *
     * Deliberately NOT retried inside a transaction: reconnecting would
     * silently discard the statements already issued, turning a visible
     * failure into a partial write.
     */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        try {
            $st = self::pdo()->prepare($sql);
            $st->execute($params);
            return $st;
        } catch (PDOException $ex) {
            if (self::$inTransaction || !self::isLostConnection($ex)) throw $ex;

            wwt_log('db', 'connection had gone away; reconnecting');
            self::$pdo = null;
            $st = self::pdo()->prepare($sql);
            $st->execute($params);
            return $st;
        }
    }

    /** Is this the connection dying rather than the query being wrong? */
    public static function isLostConnection(Throwable $ex): bool
    {
        $msg = $ex->getMessage();
        foreach (['server has gone away', 'Lost connection', 'Error while sending',
                  'is dead or not enabled', 'no connection to the server',
                  'Broken pipe', 'Connection refused', 'Communication link failure'] as $needle) {
            if (stripos($msg, $needle) !== false) return true;
        }
        return false;
    }

    /**
     * Drop the connection on purpose before a long wait, so the next query
     * opens a fresh one instead of discovering a dead one.
     */
    public static function disconnect(): void
    {
        if (self::$inTransaction) return;
        self::$pdo = null;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** First column of the first row, or $default when there is no row. */
    public static function val(string $sql, array $params = [], mixed $default = null): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        self::$inTransaction = true;
        try {
            $out = $fn($pdo);
            $pdo->commit();
            return $out;
        } catch (Throwable $t) {
            try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable) {}
            throw $t;
        } finally {
            self::$inTransaction = false;
        }
    }

    /**
     * Build "IN (?,?,?)" safely from a list of values.
     * Returns [placeholders, values]; empty input yields a never-true clause
     * so callers cannot accidentally produce "IN ()", which is a syntax error.
     */
    public static function inClause(array $values): array
    {
        if (!$values) return ['(NULL)', []];
        return ['(' . implode(',', array_fill(0, count($values), '?')) . ')', array_values($values)];
    }
}

/* ============================================================
   Settings — key/value, cached per-request.
   ============================================================ */
final class Settings
{
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache !== null) return self::$cache;
        self::$cache = [];
        foreach (DB::all('SELECT k, v FROM wwt_settings') as $r) {
            self::$cache[$r['k']] = $r['v'];
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::load()[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : (int)$v;
    }

    public static function json(string $key, array $default = []): array
    {
        $v = self::get($key);
        if ($v === null || $v === '') return $default;
        $d = json_decode($v, true);
        return is_array($d) ? $d : $default;
    }

    public static function set(string $key, string $value): void
    {
        /* Fill the cache BEFORE adding to it.
        
           Without this, writing a setting before anything has read one leaves
           self::$cache as a one-element array (PHP turns the null into an
           array on assignment). load() then sees a non-null cache, believes it
           is complete, and every other setting reads as empty for the rest of
           the request — silently, and only on that code path. */
        self::load();

        DB::run(
            'INSERT INTO wwt_settings (k, v, updated_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = UTC_TIMESTAMP()',
            [$key, $value]
        );
        self::$cache[$key] = $value;
    }

    public static function all(): array { return self::load(); }

    /** Drop the per-request cache (after a bulk save). */
    public static function flush(): void { self::$cache = null; }
}

/* ============================================================
   Rate limiting — DB backed, so it survives across PHP workers
   and cannot be reset by rotating a temp directory.
   ============================================================ */
final class RateLimit
{
    /**
     * Returns true when the caller is ALLOWED. Counts one hit if so.
     * A fixed window is enough here and is far cheaper than a sliding log.
     */
    public static function allow(string $bucket, int $max, int $windowSeconds): bool
    {
        $bucket = cut($bucket, 120);
        try {
            $row = DB::one(
                'SELECT hits, window_at FROM wwt_rate_limit WHERE bucket = ?',
                [$bucket]
            );
            $now = time();
            if ($row === null || (strtotime((string)$row['window_at'] . ' UTC') + $windowSeconds) <= $now) {
                DB::run(
                    'INSERT INTO wwt_rate_limit (bucket, hits, window_at)
                     VALUES (?, 1, UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE hits = 1, window_at = UTC_TIMESTAMP()',
                    [$bucket]
                );
                return true;
            }
            if ((int)$row['hits'] >= $max) return false;
            DB::run('UPDATE wwt_rate_limit SET hits = hits + 1 WHERE bucket = ?', [$bucket]);
            return true;
        } catch (Throwable $t) {
            // A limiter that is down must not take the whole endpoint with it.
            wwt_log('ratelimit', 'check failed, allowing', ['bucket' => $bucket, 'err' => $t->getMessage()]);
            return true;
        }
    }

    public static function reset(string $bucket): void
    {
        try { DB::run('DELETE FROM wwt_rate_limit WHERE bucket = ?', [cut($bucket, 120)]); }
        catch (Throwable) {}
    }

    public static function sweep(): int
    {
        try {
            return DB::run('DELETE FROM wwt_rate_limit WHERE window_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)')
                ->rowCount();
        } catch (Throwable) { return 0; }
    }
}

/* ============================================================
   Audit log — who changed what.
   ============================================================ */
function audit(string $action, string $detail = '', string $user = 'system'): void
{
    try {
        DB::run(
            'INSERT INTO wwt_audit_log (ts, user, action, detail, ip_trunc)
             VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?)',
            [cut($user, 150), cut($action, 60), cut($detail, 2000), ip_truncate(client_ip())]
        );
    } catch (Throwable $t) {
        wwt_log('audit', 'write failed', ['action' => $action, 'err' => $t->getMessage()]);
    }
}
