<?php
/* ============================================================
   auth.php — admin sessions, login, CSRF, roles, TOTP.

   Design notes worth keeping:
   · Passwords: password_hash() default algo (bcrypt today, argon2id when
     PHP switches) so hashes upgrade transparently via needs_rehash.
   · Lockout is per-ACCOUNT and per-IP. Account-only lockout lets an
     attacker deny service to the owner by failing 5 logins; IP-only
     lockout lets a botnet walk around it. Both, or neither works.
   · Login responses are deliberately uniform: a wrong email and a wrong
     password produce the same message and comparable timing, so the form
     cannot be used to enumerate valid accounts.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class Auth
{
    public const MAX_FAILS       = 5;
    public const LOCK_MINUTES    = 15;
    public const IDLE_SECONDS    = 86400;   // 24h
    public const ABSOLUTE_SECONDS = 604800; // 7d — re-login regardless of activity

    /* ── Session bootstrap ────────────────────────────────── */

    public static function startSession(string $salt): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
              || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        session_name('wwtadm');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/admin/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_start();

        // Bind the session to the salt + a coarse client fingerprint. A stolen
        // cookie replayed from a different browser will not validate.
        $fp = hash('sha256', $salt . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!isset($_SESSION['fp'])) {
            $_SESSION['fp'] = $fp;
        } elseif (!hash_equals((string)$_SESSION['fp'], $fp)) {
            self::destroy();
            session_start();
            $_SESSION['fp'] = $fp;
        }

        // Idle + absolute expiry.
        $now = time();
        if (isset($_SESSION['uid'])) {
            $last  = (int)($_SESSION['last_seen'] ?? 0);
            $start = (int)($_SESSION['login_at'] ?? 0);
            if (($last && $now - $last > self::IDLE_SECONDS)
             || ($start && $now - $start > self::ABSOLUTE_SECONDS)) {
                self::logout('expired');
            }
        }
        $_SESSION['last_seen'] = $now;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (bool)$p['secure'],
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    /* ── Login ────────────────────────────────────────────── */

    /**
     * @return array{ok:bool, error?:string, need_totp?:bool}
     */
    public static function attempt(string $email, string $password, string $totp = ''): array
    {
        $email = strtolower(trim($email));
        $ipKey = 'login_ip:' . ip_truncate(client_ip());

        // Per-IP brake first: cheap, and blunts distributed guessing.
        if (!RateLimit::allow($ipKey, 20, 900)) {
            audit('login_blocked', 'ip rate limit', $email);
            return ['ok' => false, 'error' => 'Too many attempts. Try again in a few minutes.'];
        }

        $user = DB::one('SELECT * FROM wwt_admin_users WHERE email = ?', [$email]);

        // Uniform failure, whether or not the account exists.
        $generic = ['ok' => false, 'error' => 'Email or password is not correct.'];

        if ($user === null) {
            // Spend comparable time so absence is not detectable by timing.
            password_verify($password, '$2y$12$usesomesillystringfoxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
            audit('login_fail', 'no such account', $email);
            return $generic;
        }

        if ($user['locked_until'] !== null
            && strtotime((string)$user['locked_until'] . ' UTC') > time()) {
            audit('login_blocked', 'account locked', $email);
            return ['ok' => false, 'error' => 'This account is locked. Try again in ' . self::LOCK_MINUTES . ' minutes.'];
        }

        if (!password_verify($password, (string)$user['pass_hash'])) {
            self::registerFailure($user);
            audit('login_fail', 'bad password', $email);
            return $generic;
        }

        // Password is right. Second factor, if the account has one.
        if (!empty($user['totp_secret'])) {
            if ($totp === '') return ['ok' => false, 'need_totp' => true, 'error' => ''];
            if (!Totp::verify((string)$user['totp_secret'], $totp)) {
                self::registerFailure($user);
                audit('login_fail', '2fa wrong', $email);
                return ['ok' => false, 'need_totp' => true, 'error' => 'That code is not right.'];
            }
        }

        // Transparent hash upgrade if PHP's default algorithm has moved on.
        if (password_needs_rehash((string)$user['pass_hash'], PASSWORD_DEFAULT)) {
            DB::run('UPDATE wwt_admin_users SET pass_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        DB::run('UPDATE wwt_admin_users SET failed_attempts = 0, locked_until = NULL,
                 last_login = UTC_TIMESTAMP() WHERE id = ?', [$user['id']]);
        RateLimit::reset($ipKey);

        // New session id on privilege change — defeats session fixation.
        session_regenerate_id(true);
        $_SESSION['uid']      = (int)$user['id'];
        $_SESSION['email']    = (string)$user['email'];
        $_SESSION['role']     = (string)$user['role'];
        $_SESSION['login_at'] = time();
        $_SESSION['last_seen']= time();

        audit('login_ok', 'role=' . $user['role'], $email);
        return ['ok' => true];
    }

    private static function registerFailure(array $user): void
    {
        $fails = (int)$user['failed_attempts'] + 1;
        if ($fails >= self::MAX_FAILS) {
            DB::run('UPDATE wwt_admin_users SET failed_attempts = ?,
                     locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE) WHERE id = ?',
                [$fails, self::LOCK_MINUTES, $user['id']]);
            audit('account_locked', 'after ' . $fails . ' failures', (string)$user['email']);
        } else {
            DB::run('UPDATE wwt_admin_users SET failed_attempts = ? WHERE id = ?', [$fails, $user['id']]);
        }
    }

    public static function logout(string $why = 'user'): void
    {
        if (!empty($_SESSION['email'])) audit('logout', $why, (string)$_SESSION['email']);
        self::destroy();
    }

    /* ── State ────────────────────────────────────────────── */

    public static function check(): bool     { return !empty($_SESSION['uid']); }
    public static function id(): int         { return (int)($_SESSION['uid'] ?? 0); }
    public static function email(): string   { return (string)($_SESSION['email'] ?? ''); }
    public static function role(): string    { return (string)($_SESSION['role'] ?? ''); }
    public static function isAdmin(): bool   { return self::role() === 'admin'; }

    /** Redirect to login unless signed in. */
    public static function requireLogin(): void
    {
        if (self::check()) return;
        $to = '/admin/?p=login';
        if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/admin/') {
            $to .= '&next=' . rawurlencode((string)$_SERVER['REQUEST_URI']);
        }
        header('Location: ' . $to, true, 302);
        exit;
    }

    /** Hard stop for anything a read-only viewer must not do. */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (self::isAdmin()) return;
        http_response_code(403);
        audit('forbidden', (string)($_SERVER['REQUEST_URI'] ?? ''), self::email());
        echo '<h1>403 — not allowed</h1><p>Your account has read-only access.</p>'
           . '<p><a href="/admin/">Back to the panel</a></p>';
        exit;
    }

    /* ── User management ──────────────────────────────────── */

    public static function createUser(string $email, string $password, string $role = 'viewer'): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid email.');
        if (strlen($password) < 12) throw new InvalidArgumentException('Password must be at least 12 characters.');
        if (!in_array($role, ['admin', 'viewer'], true)) throw new InvalidArgumentException('Invalid role.');

        return DB::insert(
            'INSERT INTO wwt_admin_users (email, pass_hash, role, created_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$email, password_hash($password, PASSWORD_DEFAULT), $role]
        );
    }

    public static function setPassword(int $userId, string $password): void
    {
        if (strlen($password) < 12) throw new InvalidArgumentException('Password must be at least 12 characters.');
        DB::run('UPDATE wwt_admin_users SET pass_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $userId]);
    }
}

/* ============================================================
   CSRF — one token per session, required on every mutating request.
   ============================================================ */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function valid(?string $sent): bool
    {
        $have = (string)($_SESSION['csrf'] ?? '');
        return $have !== '' && is_string($sent) && hash_equals($have, $sent);
    }

    /** Call at the top of every POST handler. Exits on failure. */
    public static function require(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        if (self::valid($_POST['_csrf'] ?? null)) return;
        http_response_code(419);
        audit('csrf_reject', (string)($_SERVER['REQUEST_URI'] ?? ''), Auth::email() ?: 'anon');
        echo '<h1>Session expired</h1><p>Please <a href="/admin/">sign in again</a> and retry.</p>';
        exit;
    }
}

/* ============================================================
   TOTP (RFC 6238) — optional 2FA, no external dependency.
   ============================================================ */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $bytes = 20): string
    {
        return self::base32encode(random_bytes($bytes));
    }

    /** otpauth:// URI for the QR code / manual entry. */
    public static function uri(string $secret, string $account, string $issuer = 'Wwwebtech'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
             . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    /** Accepts the current step plus one either side, for clock drift. */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) return false;
        $step = (int)floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::at($secret, $step + $i), $code)) return true;
        }
        return false;
    }

    public static function at(string $secret, int $step): string
    {
        $key  = self::base32decode($secret);
        if ($key === '') return '';
        $hash = hash_hmac('sha1', pack('N*', 0, $step), $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $part = ((ord($hash[$off]) & 0x7F) << 24)
              | ((ord($hash[$off + 1]) & 0xFF) << 16)
              | ((ord($hash[$off + 2]) & 0xFF) << 8)
              |  (ord($hash[$off + 3]) & 0xFF);
        return str_pad((string)($part % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32encode(string $bin): string
    {
        $out = ''; $buf = 0; $bits = 0;
        for ($i = 0, $n = strlen($bin); $i < $n; $i++) {
            $buf = ($buf << 8) | ord($bin[$i]); $bits += 8;
            while ($bits >= 5) { $bits -= 5; $out .= self::ALPHABET[($buf >> $bits) & 31]; }
        }
        if ($bits > 0) $out .= self::ALPHABET[($buf << (5 - $bits)) & 31];
        return $out;
    }

    private static function base32decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32) ?? '');
        $out = ''; $buf = 0; $bits = 0;
        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $v = strpos(self::ALPHABET, $b32[$i]);
            if ($v === false) return '';
            $buf = ($buf << 5) | $v; $bits += 5;
            if ($bits >= 8) { $bits -= 8; $out .= chr(($buf >> $bits) & 0xFF); }
        }
        return $out;
    }
}
