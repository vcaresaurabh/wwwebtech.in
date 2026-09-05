<?php
/* ============================================================
   claude.php — the Anthropic Messages API client.

   Raw HTTP rather than the official PHP SDK, deliberately: this whole
   codebase installs by copying files onto shared hosting with no
   Composer step, and pulling in an SDK plus its dependency tree for a
   single POST would be the largest thing in the deploy. One endpoint,
   one request shape, no autoloader.

   What this file is careful about:
     · Money. Every call is priced from the response's own token counts
       and checked against a monthly cap BEFORE it is made.
     · Refusals. A declined request returns HTTP 200 with
       stop_reason "refusal" — reading .content without checking would
       silently store an empty post.
     · Truncation. stop_reason "max_tokens" means the article was cut
       off mid-sentence. That is a failure, not a short post.
     · Retries. 429/500/529 are retried with backoff and the server's
       own retry-after; 400/401/403/404 never are.
   ============================================================ */

declare(strict_types=1);

final class Claude
{
    public const ENDPOINT   = 'https://api.anthropic.com/v1/messages';
    public const APIVERSION = '2023-06-01';

    /**
     * Models this panel offers, with USD per million tokens.
     *
     * `thinking` and `effort` differ by model and sending an unsupported
     * one is a 400, so the request is shaped from this table rather than
     * from an assumption about what every model accepts.
     *
     * Prices are the standard published rates; introductory discounts are
     * ignored on purpose, so the running total never under-reports.
     */
    public const MODELS = [
        'claude-opus-5' => [
            'label' => 'Opus 5 — best writing, highest cost',
            'in' => 5.00, 'out' => 25.00, 'thinking' => 'adaptive', 'effort' => true,
        ],
        'claude-sonnet-5' => [
            'label' => 'Sonnet 5 — strong, noticeably cheaper',
            'in' => 3.00, 'out' => 15.00, 'thinking' => 'adaptive', 'effort' => true,
        ],
        'claude-haiku-4-5' => [
            'label' => 'Haiku 4.5 — cheapest, shortest leash',
            'in' => 1.00, 'out' => 5.00, 'thinking' => null, 'effort' => false,
        ],
    ];

    public const DEFAULT_MODEL = 'claude-opus-5';

    /**
     * The panel setting wins, then config.php, then the built-in default.
     * config.php documented itself as the fallback but was never actually
     * read here, so an operator editing it saw no effect and no error.
     * Each candidate is checked against MODELS, so a typo or a retired id
     * falls through to something that exists rather than 404ing at the API.
     */
    public static function model(): string
    {
        foreach ([(string)Settings::get('blog_model', ''),
                  (string)cfg('claude.model', '')] as $m) {
            if ($m !== '' && isset(self::MODELS[$m])) return $m;
        }
        return self::DEFAULT_MODEL;
    }

    public static function apiKey(): string
    {
        if (self::$keyOverride !== '') return self::$keyOverride;
        return Secrets::get('anthropic_key', (string)cfg('anthropic.api_key', ''));
    }

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    /* ── Spend ─────────────────────────────────────────────── */

    public static function priceOf(string $model, int $in, int $out): float
    {
        $p = self::MODELS[$model] ?? null;
        if (!$p) return 0.0;                       // unknown model: recorded, not guessed
        return ($in / 1_000_000) * $p['in'] + ($out / 1_000_000) * $p['out'];
    }

    public static function monthlyCap(): float
    {
        $v = (string)Settings::get('blog_monthly_cap_usd', '');
        return $v !== '' ? (float)$v : (float)cfg('anthropic.monthly_cap_usd', 15.0);
    }

    /** What has been spent this calendar month, from stored token counts. */
    public static function spentThisMonth(): float
    {
        return (float)DB::val(
            "SELECT COALESCE(SUM(cost_usd), 0) FROM wwt_posts
             WHERE created_at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')", [], 0.0);
    }

    public static function budgetLeft(): float
    {
        return round(self::monthlyCap() - self::spentThisMonth(), 4);
    }

    /* ── The call ──────────────────────────────────────────── */

    /**
     * Send one message. Returns a normalised result; never throws for an
     * API-level problem, because the caller records the failure against
     * the post rather than losing the whole cron run.
     *
     * @return array{ok:bool, text:string, error:string, stop:string,
     *                in:int, out:int, cost:float, model:string}
     */
    public static function message(string $system, string $prompt, array $opt = []): array
    {
        $model = (string)($opt['model'] ?? self::model());
        $fail  = static fn(string $e): array => [
            'ok' => false, 'text' => '', 'error' => $e, 'stop' => '',
            'in' => 0, 'out' => 0, 'cost' => 0.0, 'model' => $model,
        ];

        $key = self::apiKey();
        if ($key === '') return $fail('No Anthropic API key is set (Settings → Blog).');

        /* Refuse before spending, not after. */
        if (empty($opt['ignore_cap'])) {
            $left = self::budgetLeft();
            if ($left <= 0.0) {
                return $fail(sprintf('Monthly cap of $%.2f reached — $%.4f spent. Nothing was sent.',
                    self::monthlyCap(), self::spentThisMonth()));
            }
        }

        $body = [
            'model'      => $model,
            'max_tokens' => (int)($opt['max_tokens'] ?? 16000),
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ];

        /* Shape the request to the model. Sending `thinking` to a model
           that does not take it, or `effort` to one that does not, is a
           400 — so both come from the capability table. */
        $caps = self::MODELS[$model] ?? ['thinking' => null, 'effort' => false];
        if (!empty($caps['thinking'])) $body['thinking'] = ['type' => $caps['thinking']];
        if (!empty($caps['effort'])) {
            $body['output_config'] = ['effort' => (string)($opt['effort'] ?? 'medium')];
        }

        $attempts = (int)($opt['retries'] ?? 3);
        $lastErr  = 'unknown error';

        /* A generation call runs for over a minute, which is longer than a
           shared host keeps an idle MySQL connection. Let go of it now rather
           than find it dead when there is a result worth saving. */
        DB::disconnect();

        for ($n = 1; $n <= $attempts; $n++) {
            [$status, $raw, $curlErr, $retryAfter] = self::post($body, $key, (int)($opt['timeout'] ?? 180));

            if ($curlErr !== '') {
                $lastErr = 'network: ' . $curlErr;
                if ($n < $attempts) { sleep(min(30, 2 ** $n)); continue; }
                return $fail($lastErr);
            }

            $json = json_decode($raw, true);

            if ($status === 200 && is_array($json)) {
                $stop = (string)($json['stop_reason'] ?? '');
                $in   = (int)($json['usage']['input_tokens'] ?? 0);
                $out  = (int)($json['usage']['output_tokens'] ?? 0);
                $cost = self::priceOf($model, $in, $out);

                /* A refusal is a 200. Reading content without checking here
                   is how an empty post gets published. */
                if ($stop === 'refusal') {
                    $why = (string)($json['stop_details']['category'] ?? 'unspecified');
                    return ['ok' => false, 'text' => '', 'stop' => $stop, 'in' => $in, 'out' => $out,
                            'cost' => $cost, 'model' => $model,
                            'error' => 'The model declined this request (' . $why . ').'];
                }

                $text = '';
                foreach ((array)($json['content'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'text') $text .= (string)($block['text'] ?? '');
                }

                if ($stop === 'max_tokens') {
                    return ['ok' => false, 'text' => $text, 'stop' => $stop, 'in' => $in, 'out' => $out,
                            'cost' => $cost, 'model' => $model,
                            'error' => 'Cut off at the token limit — the article is incomplete.'];
                }

                return ['ok' => trim($text) !== '', 'text' => $text, 'stop' => $stop,
                        'in' => $in, 'out' => $out, 'cost' => $cost, 'model' => $model,
                        'error' => trim($text) === '' ? 'The model returned nothing.' : ''];
            }

            $apiMsg = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
            $lastErr = 'HTTP ' . $status . ($apiMsg !== '' ? ': ' . $apiMsg : ': ' . cut($raw, 200));

            /* 400/401/403/404/413 are our fault and will fail identically
               on every retry. Only throttling and server-side faults are
               worth waiting for. */
            if (!in_array($status, [429, 500, 502, 503, 529], true)) return $fail($lastErr);

            if ($n < $attempts) {
                $wait = $retryAfter > 0 ? min(60, $retryAfter) : min(30, 2 ** $n);
                wwt_log('claude', 'retrying', ['status' => $status, 'wait' => $wait, 'attempt' => $n]);
                sleep($wait);
            }
        }

        return $fail($lastErr . ' (after ' . $attempts . ' attempts)');
    }

    /** @return array{0:int,1:string,2:string,3:int} status, body, curlError, retryAfter */
    private static function post(array $body, string $key, int $timeout): array
    {
        $ch = curl_init(self::ENDPOINT);
        $retryAfter = 0;
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . $key,
                'anthropic-version: ' . self::APIVERSION,
            ],
            CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$retryAfter) {
                if (stripos($line, 'retry-after:') === 0) {
                    $retryAfter = (int)trim(substr($line, 12));
                }
                return strlen($line);
            },
        ]);
        $raw    = (string)curl_exec($ch);
        $err    = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $raw, $err, $retryAfter];
    }

    /** A cheap round trip to prove the key works, for the Settings page. */
    /** A key under test, before it is saved. Set only for the duration of one call. */
    private static string $keyOverride = '';

    public static function testKey(string $key = ''): array
    {
        self::$keyOverride = $key;
        try { return self::testKeyNow(); } finally { self::$keyOverride = ''; }
    }

    private static function testKeyNow(): array
    {
        $r = self::message(
            'Reply with exactly one word.',
            'Say OK.',
            ['max_tokens' => 16, 'retries' => 1, 'ignore_cap' => true, 'effort' => 'low']
        );
        return ['ok' => $r['ok'], 'error' => $r['error'],
                'detail' => $r['ok'] ? sprintf('%s replied (%d in / %d out tokens, $%.5f)',
                    $r['model'], $r['in'], $r['out'], $r['cost']) : ''];
    }
}
