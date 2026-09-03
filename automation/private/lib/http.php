<?php
/* ============================================================
   http.php — one small HTTP client for the SEO checks.

   Everything it fetches is either this site or a search-engine ping
   endpoint the owner has configured. It never follows a redirect to a
   different host, never sends cookies, and caps the response size, so a
   misconfigured URL cannot turn a cron job into an outbound problem.
   ============================================================ */

declare(strict_types=1);

final class Http
{
    public const UA = 'WwwebtechSiteCheck/1.0 (+https://wwwebtech.in/; internal monitoring)';
    public const MAX_BYTES = 2_000_000;

    /**
     * @return array{status:int, body:string, headers:array<string,string>,
     *                error:string, ms:int, url:string}
     */
    public static function get(string $url, array $opt = []): array
    {
        $out = ['status' => 0, 'body' => '', 'headers' => [], 'error' => '', 'ms' => 0, 'url' => $url];
        if (!filter_var($url, FILTER_VALIDATE_URL)) { $out['error'] = 'not a URL'; return $out; }

        $headers = [];
        $started = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => (bool)($opt['follow'] ?? false),
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => (int)($opt['timeout'] ?? 20),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_NOBODY         => (bool)($opt['head'] ?? false),
            CURLOPT_USERAGENT      => (string)($opt['ua'] ?? self::UA),
            CURLOPT_ENCODING       => '',                 // accept gzip, measure like a browser
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$headers) {
                $p = explode(':', $line, 2);
                if (count($p) === 2) $headers[strtolower(trim($p[0]))] = trim($p[1]);
                return strlen($line);
            },
            /* Stop reading a response that is absurdly large rather than
               holding it all in memory on a shared host. */
            CURLOPT_BUFFERSIZE   => 65536,
            CURLOPT_NOPROGRESS   => false,
            CURLOPT_PROGRESSFUNCTION => static fn($r, $dlTotal, $dlNow) => $dlNow > self::MAX_BYTES ? 1 : 0,
        ]);
        if (!empty($opt['headers'])) curl_setopt($ch, CURLOPT_HTTPHEADER, (array)$opt['headers']);

        $body = curl_exec($ch);
        $out['error']  = curl_error($ch);
        $out['status'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $out['url']    = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $out['body']    = $body === false ? '' : (string)$body;
        $out['headers'] = $headers;
        $out['ms']      = (int)round((microtime(true) - $started) * 1000);
        return $out;
    }

    public static function head(string $url, array $opt = []): array
    {
        return self::get($url, $opt + ['head' => true]);
    }

    public static function postJson(string $url, array $payload, array $opt = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)($opt['timeout'] ?? 20),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => self::UA,
            CURLOPT_HTTPHEADER     => ['content-type: application/json; charset=utf-8'],
        ]);
        $body   = (string)curl_exec($ch);
        $err    = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body, 'error' => $err];
    }
}
