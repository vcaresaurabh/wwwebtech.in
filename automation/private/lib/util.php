<?php
/* ============================================================
   util.php — small shared helpers. No state, no side effects
   beyond logging. Loaded by everything.
   ============================================================ */

declare(strict_types=1);

/**
 * HTML-escape for output. Use on EVERY dynamic value in a template.
 *
 * Takes int and float as well as string on purpose. PHP silently casts a
 * numeric-string array key to an integer, so `foreach (['7' => '...'] as $k)`
 * hands you int 7 — and under strict_types a `?string` parameter turns that
 * ordinary option list into a fatal error halfway through the page.
 */
function e(string|int|float|null $s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside a JS string literal in a <script> block. */
function ejs(?string $s): string {
    return json_encode((string)$s, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '""';
}

/** Truncate to $max characters (not bytes), with or without mbstring. */
function cut(string $v, int $max): string {
    if (function_exists('mb_substr')) return mb_substr($v, 0, $max);
    return preg_match('/^.{0,' . $max . '}/us', $v, $m) ? $m[0] : substr($v, 0, $max);
}

/** Read a POST/GET field as a trimmed, length-capped, NUL-free string. */
function field(array $src, string $key, int $max = 255): string {
    $v = $src[$key] ?? '';
    if (is_array($v)) $v = implode(', ', array_map('strval', array_slice($v, 0, 10)));
    return cut(trim(str_replace("\0", '', (string)$v)), $max);
}

/**
 * Reduce an IP to a network prefix so it can be used for geo lookup and
 * rate limiting without storing anyone's address: /24 for v4, /48 for v6.
 * The full address is never written to disk or database.
 */
function ip_truncate(?string $ip): string {
    $ip = (string)$ip;
    if ($ip === '') return '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $p = explode('.', $ip);
        return "$p[0].$p[1].$p[2].0";
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $bin = @inet_pton($ip);
        if ($bin === false) return '';
        $bin = substr($bin, 0, 6) . str_repeat("\0", 10);   // keep /48
        return (string)@inet_ntop($bin);
    }
    return '';
}

/**
 * This site's own hostname, without the port.
 *
 * $_SERVER['HTTP_HOST'] includes the port when it is not 80/443, while
 * parse_url(..., PHP_URL_HOST) never does. Comparing the two directly makes
 * every same-origin check fail the moment the site is served on any other
 * port — which is exactly what a dev server does.
 */
function self_host(): string {
    $h = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($h === '') return '';
    // Strip the port, but not the colons inside a bracketed IPv6 literal.
    if ($h[0] === '[') return (string)(parse_url('http://' . $h, PHP_URL_HOST) ?: $h);
    $colon = strrpos($h, ':');
    return $colon === false ? $h : substr($h, 0, $colon);
}

/** True when $url points at this same site (or its www./bare variant). */
function same_origin(?string $url): bool {
    $host = strtolower((string)parse_url((string)$url, PHP_URL_HOST));
    if ($host === '') return true;                 // relative or unparseable: not cross-site
    $self = self_host();
    if ($self === '') return true;
    $bare = preg_replace('/^www\./', '', $self) ?? $self;
    return $host === $self || $host === $bare || $host === 'www.' . $bare;
}

/** The client IP, honouring the proxy headers LiteSpeed/Cloudflare set. */
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $h) {
        if (!empty($_SERVER[$h]) && filter_var($_SERVER[$h], FILTER_VALIDATE_IP)) {
            return (string)$_SERVER[$h];
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Registrable-ish domain of a referrer URL, or '' for direct/self. */
function ref_domain(?string $referrer, string $selfHost = 'wwwebtech.in'): string {
    $host = strtolower((string)parse_url((string)$referrer, PHP_URL_HOST));
    if ($host === '') return '';
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    if ($host === $selfHost || str_ends_with($host, '.' . $selfHost)) return '';
    return cut($host, 120);
}

/** A URL path we are willing to store: no query, no fragment, capped. */
function clean_path(?string $p): string {
    $p = (string)$p;
    $p = (string)(parse_url($p, PHP_URL_PATH) ?? '/');
    if ($p === '' || $p[0] !== '/') $p = '/' . $p;
    $p = preg_replace('#/{2,}#', '/', $p) ?? $p;
    return cut($p, 190);
}

/** Slugify a title for use as a directory name. */
function slugify(string $s): string {
    $s = trim($s);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) $s = $t;
    }
    $s = strtolower($s);
    /* Drop apostrophes rather than turn them into separators, or "don't"
       becomes "don-t" — a URL that reads as a typo on a site that sells SEO.
       Straight and curly both, since titles come from a language model. */
    $s = str_replace(["'", "\u{2019}", "\u{2018}", '`'], '', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
    return trim(cut($s, 80), '-');
}

/* ── Bot / device detection ──────────────────────────────────
   The AI crawler list is the point of the GEO monitoring view:
   these agents are how ChatGPT, Claude, Perplexity and Google's AI
   surfaces discover the site, and none of them run JavaScript —
   which is exactly why the server-side front controller logs them. */
const WWT_BOTS = [
    'GPTBot'              => 'GPTBot',
    'ChatGPT-User'        => 'ChatGPT-User',
    'OAI-SearchBot'       => 'OAI-SearchBot',
    'ClaudeBot'           => 'ClaudeBot',
    'Claude-Web'          => 'Claude-Web',
    'anthropic-ai'        => 'anthropic-ai',
    'PerplexityBot'       => 'PerplexityBot',
    'Perplexity-User'     => 'Perplexity-User',
    'Google-Extended'     => 'Google-Extended',
    'GoogleOther'         => 'GoogleOther',
    'CCBot'               => 'CCBot',
    'Bytespider'          => 'Bytespider',
    'Amazonbot'           => 'Amazonbot',
    'meta-externalagent'  => 'meta-externalagent',
    'FacebookBot'         => 'FacebookBot',
    'Applebot-Extended'   => 'Applebot-Extended',
    'Applebot'            => 'Applebot',
    'Googlebot'           => 'Googlebot',
    'bingbot'             => 'Bingbot',
    'Yandex'              => 'YandexBot',
    'DuckDuckBot'         => 'DuckDuckBot',
    'Slurp'               => 'Yahoo Slurp',
    'AhrefsBot'           => 'AhrefsBot',
    'SemrushBot'          => 'SemrushBot',
    'MJ12bot'             => 'MJ12bot',
    'DotBot'              => 'DotBot',
    'PetalBot'            => 'PetalBot',
    'YisouSpider'         => 'YisouSpider',
];

/** Returns '' for a human, else the friendly bot name. */
function detect_bot(?string $ua): string {
    $ua = (string)$ua;
    if ($ua === '') return 'Unknown-Agent';
    foreach (WWT_BOTS as $needle => $name) {
        if (stripos($ua, $needle) !== false) return $name;
    }
    // Generic catch-all, deliberately after the named list.
    if (preg_match('/\b(bot|crawler|spider|crawl|scraper|curl|wget|python-requests|httpclient|headless)\b/i', $ua)) {
        return 'Other-Bot';
    }
    return '';
}

function detect_device(?string $ua): string {
    $ua = (string)$ua;
    if ($ua === '') return 'unknown';
    if (preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $ua)) return 'tablet';
    if (preg_match('/Mobi|Android|iPhone|iPod|Windows Phone/i', $ua))        return 'mobile';
    return 'desktop';
}

/**
 * Traffic-source grouping used across the analytics tables.
 * Deliberately small and explainable — no magic classification.
 */
function source_group(string $utmMedium, string $utmSource, string $refDomain): string {
    $m = strtolower($utmMedium);
    if ($m !== '') {
        if (in_array($m, ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social'], true)) return 'Campaign';
        if ($m === 'organic')  return 'Organic';
        if ($m === 'social')   return 'Social';
        if ($m === 'email')    return 'Email';
        if ($m === 'referral') return 'Referral';
        return 'Campaign';
    }
    if ($utmSource !== '') return 'Campaign';
    if ($refDomain === '') return 'Direct';

    $searchEngines = ['google.', 'bing.', 'duckduckgo.', 'yahoo.', 'ecosia.', 'brave.', 'yandex.', 'baidu.'];
    foreach ($searchEngines as $s) if (str_starts_with($refDomain, $s)) return 'Organic';

    $socials = ['facebook.com', 'instagram.com', 'linkedin.com', 'lnkd.in', 't.co', 'x.com',
                'twitter.com', 'youtube.com', 'pinterest.', 'reddit.com', 'whatsapp.com', 'l.wl.co'];
    foreach ($socials as $s) if ($refDomain === $s || str_starts_with($refDomain, $s)) return 'Social';

    $ai = ['chatgpt.com', 'chat.openai.com', 'perplexity.ai', 'claude.ai', 'gemini.google.com', 'copilot.microsoft.com'];
    foreach ($ai as $s) if ($refDomain === $s) return 'AI assistant';

    return 'Referral';
}

/** Append one line to a dated log file. Never throws. */
function wwt_log(string $channel, string $message, array $ctx = []): void {
    try {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $line = gmdate('Y-m-d H:i:s') . ' [' . $channel . '] ' . $message
              . ($ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '')
              . PHP_EOL;
        @file_put_contents($dir . '/' . gmdate('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable) { /* logging must never break a request */ }
}

/** Bytes → human. */
function human_bytes(int $b): string {
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($b >= 1024 && $i < 3) { $b = intdiv($b, 1024); $i++; }
    return $b . ' ' . $u[$i];
}

/**
 * The key that marks a form submission as a test. Lives in the encrypted
 * store (Connections → Feed & test keys); config.php is only the fallback
 * for an install that has not migrated yet.
 */
function wwt_test_key(): string {
    try { $k = Secrets::get('cron_key', ''); } catch (Throwable) { $k = ''; }
    return $k !== '' ? $k : (string)cfg('cron_key', '');
}
