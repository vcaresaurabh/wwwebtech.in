<?php
/* ============================================================
   secrets.php — encrypt values that have to live in the database.

   The owner must be able to change the mailbox password from the panel
   without editing a file or opening SSH. That means the password has to
   be stored somewhere the panel can write, which means the database.

   Storing it in plain text would mean any SQL injection, any stolen
   backup, any shared phpMyAdmin session hands over a working mailbox.
   So it is encrypted with a key that lives in config.php, OUTSIDE the
   web root and outside the database. An attacker now needs both.

   AES-256-GCM: authenticated, so a tampered ciphertext fails loudly
   instead of decrypting to garbage.
   ============================================================ */

declare(strict_types=1);

final class Secrets
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:v1:';

    /** 32-byte key derived from the config secret, so its length is fixed. */
    private static function key(): string
    {
        $material = (string)cfg('secret_key', '') ?: (string)cfg('session_salt', '');
        if ($material === '') {
            throw new RuntimeException('No secret_key or session_salt in config.php — cannot store secrets.');
        }
        return hash('sha256', 'wwt-secret-v1|' . $material, true);
    }

    public static function available(): bool
    {
        return function_exists('openssl_encrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true)
            && ((string)cfg('secret_key', '') !== '' || (string)cfg('session_salt', '') !== '');
    }

    public static function isEncrypted(string $v): bool
    {
        return str_starts_with($v, self::PREFIX);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) throw new RuntimeException('Could not encrypt.');
        return self::PREFIX . base64_encode($iv . $tag . $ct);
    }

    /**
     * Returns '' when the value cannot be decrypted — a rotated key, a
     * corrupted row. Never throws into a request: the caller falls back to
     * config.php and the panel reports the mailbox as unconfigured, which is
     * true and actionable, rather than 500ing.
     */
    public static function decrypt(string $stored): string
    {
        if ($stored === '') return '';
        if (!self::isEncrypted($stored)) return $stored;   // pre-encryption value
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) return '';
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $out = @openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($out === false) {
            wwt_log('secrets', 'decrypt failed — key changed or value corrupt');
            return '';
        }
        return $out;
    }

    /** Store an encrypted setting. An empty value clears it. */
    public static function put(string $key, string $plain): void
    {
        Settings::set($key, $plain === '' ? '' : self::encrypt($plain));
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = (string)Settings::get($key, '');
        return $v === '' ? $default : self::decrypt($v);
    }

    /** Never show a secret back to the browser — only whether there is one. */
    public static function mask(string $plain): string
    {
        if ($plain === '') return '';
        $n = strlen($plain);
        return str_repeat('•', min(12, max(6, $n)));
    }
}
