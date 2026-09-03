<?php
/* ============================================================
   leads.php — validation, storage and notification for enquiries.

   Kept out of the endpoint so the admin panel can reuse the same
   status list, labels and CSV shape, and so there is exactly one
   definition of what a valid lead is.
   ============================================================ */

declare(strict_types=1);

final class Leads
{
    /** The pipeline, in order. Keys match the ENUM in schema.sql. */
    public const STATUSES = [
        'new'       => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'won'       => 'Won',
        'lost'      => 'Lost',
    ];

    /* Per-IP submissions allowed per hour.
    
       Five was fine for a single contact page. With paid landing pages it is
       tight: a co-working space, a corporate NAT or a shared office in India
       can legitimately produce several enquiries an hour from one address,
       and blocking a real buyer costs far more than an extra spam row. Ten,
       and settable, with the honeypot and the time trap doing the actual
       spam work. */
    public const RATE_MAX_DEFAULT = 10;
    public const RATE_WINDOW      = 3600;

    public static function rateMax(): int
    {
        return max(1, min(200, Settings::int('lead_rate_per_hour', self::RATE_MAX_DEFAULT)));
    }

    /** Chips offered by the form. Anything else is discarded, not stored. */
    public const SERVICES = ['Website', 'CRM', 'SEO', 'Social', 'Not sure'];

    public static function replyPromise(): string
    {
        return Settings::get('reply_promise', '1 business day') ?: '1 business day';
    }

    /**
     * Validate a submission.
     * @return array{ok:bool, errors:array<string,string>, data:array<string,mixed>}
     */
    public static function validate(array $post, array $server = []): array
    {
        $errors = [];

        $name    = field($post, 'name', 100);
        $email   = strtolower(field($post, 'email', 150));
        $phone   = field($post, 'phone', 30);
        $budget  = field($post, 'budget', 40);
        $message = field($post, 'message', 2000);

        if ($name === '')                                   $errors['name']    = 'Please tell us your name.';
        if ($email === '')                                  $errors['email']   = 'We need an email to reply to.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email']   = 'That email doesn’t look right.';
        if ($message === '')                                $errors['message'] = 'Please tell us what’s not working.';
        elseif (mb_strlen($message) < 5)                    $errors['message'] = 'A little more detail, please.';

        /* Anything used in a mail header is rejected outright if it contains
           a newline — that is how header injection gets in. */
        foreach (['name' => $name, 'email' => $email] as $k => $v) {
            if (preg_match('/[\r\n]/', $v)) $errors[$k] = 'Invalid characters.';
        }

        /* Service chips: whitelist against what the form actually offers so
           the column can never hold arbitrary text. */
        $raw = $post['need'] ?? ($post['service'] ?? []);
        if (!is_array($raw)) $raw = [$raw];
        /* Iterate the whitelist, not the submission, so the stored value is
           always in the form's own order. Otherwise "SEO, Website" and
           "Website, SEO" are two different strings for the same answer and
           nothing downstream can group them. */
        $sent    = array_map(static fn($v) => strtolower(trim((string)$v)), array_slice($raw, 0, 8));
        $service = [];
        foreach (self::SERVICES as $allowed) {
            if (in_array(strtolower($allowed), $sent, true)) $service[] = $allowed;
        }

        /* Where they came from. JavaScript posts these explicitly; with
           JavaScript off we recover them from the Referer, which for a
           same-origin POST still carries the full URL and its query. */
        $page = clean_path(field($post, '_page', 190));
        $ref  = field($post, '_ref', 255);
        $utm  = [
            'utm_source'   => field($post, 'utm_source', 80),
            'utm_medium'   => field($post, 'utm_medium', 80),
            'utm_campaign' => field($post, 'utm_campaign', 120),
        ];

        $referer = (string)($server['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            if ($page === '/' || $page === '') $page = clean_path((string)parse_url($referer, PHP_URL_PATH));
            parse_str((string)parse_url($referer, PHP_URL_QUERY), $q);
            foreach ($utm as $k => $v) {
                if ($v === '' && !empty($q[$k])) $utm[$k] = cut((string)$q[$k], 120);
            }
        }
        if ($ref === '') $ref = cut($referer, 255);

        /* ── The funnel fields (§3.1, §3.2) ────────────────────
           Everything the landing page form adds beyond the original
           contact form. Whitelisted where there is a fixed set, capped
           where there is not. */
        $timeline = field($post, 'timeline', 20);
        if (!in_array($timeline, ['asap', '1-3m', 'research', ''], true)) $timeline = '';

        $hasSite = field($post, 'has_site', 3);
        if (!in_array($hasSite, ['yes', 'no', ''], true)) $hasSite = '';

        $siteUrl = field($post, 'site_url', 255);
        if ($siteUrl !== '') {
            /* A bare domain is what people actually type. */
            if (!preg_match('#^https?://#i', $siteUrl)) $siteUrl = 'https://' . $siteUrl;
            if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) $siteUrl = '';
        }

        /* Click IDs. Long, opaque, and the only thing that lets an offline
           conversion attribute back to the ad that paid for the lead — so
           they are length-capped and character-restricted, never parsed. */
        $clickIds = [];
        foreach (['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid'] as $k) {
            $v = field($post, $k, 200);
            $clickIds[$k] = preg_match('/^[A-Za-z0-9._\-]{0,200}$/', $v) ? $v : '';
        }

        $lp = field($post, '_lp', 120);
        if (!preg_match('/^[a-z0-9-]{0,120}$/', $lp)) $lp = '';
        $variant = field($post, '_variant', 1);
        if (!in_array($variant, ['a', 'b', ''], true)) $variant = '';

        return [
            'ok'     => !$errors,
            'errors' => $errors,
            'data'   => [
                'name'    => $name,
                'email'   => $email,
                'phone'   => $phone,
                'service' => cut(implode(', ', $service), 120),
                'budget'  => in_array($budget, self::budgets(), true) ? $budget : cut($budget, 40),
                'message' => $message,
                'page'    => $page,
                'referrer'=> $ref,

                'timeline'     => $timeline,
                'has_site'     => $hasSite,
                'site_url'     => $siteUrl,
                'landing_page' => $lp,
                'variant'      => $variant,
                'company_name' => field($post, 'company_name', 120),
                'utm_term'     => field($post, 'utm_term', 160),
                'utm_content'  => field($post, 'utm_content', 160),

                /* Behaviour before submitting. Both are hints the visitor's
                   own browser supplied, so both are clamped to something
                   plausible rather than trusted. */
                'dwell_secs'   => max(0, min(86400, (int)($post['_dwell'] ?? 0))),
                'pages_seen'   => max(0, min(500, (int)($post['_seen'] ?? 0))),

                'consent'      => !empty($post['consent']),
                'is_partial'   => !empty($post['_partial']),
            ] + $utm + $clickIds,
        ];
    }

    /** The ranges the form offers, for the same reason as SERVICES. */
    public static function budgets(): array
    {
        return ['', 'Under ₹50k', '₹50k – ₹1.5L', '₹1.5L – ₹5L', '₹5L+', 'Ongoing retainer'];
    }

    /**
     * Store a validated lead, score it, and return the new id.
     *
     * A PARTIAL lead (the form was abandoned part-way but we have a way to
     * reach them) is stored the same way and flagged. It is never notified
     * and never enrolled in a sequence — it is a recoverable record, not a
     * customer who asked to be contacted.
     */
    public static function store(array $d, bool $isTest = false): int
    {
        $partial = !empty($d['is_partial']);

        $id = DB::insert(
            'INSERT INTO wwt_leads
             (ts, name, email, phone, service, budget, message, company, page, referrer,
              utm_source, utm_medium, utm_campaign, utm_term, utm_content,
              gclid, gbraid, wbraid, msclkid, fbclid,
              landing_page, variant, timeline, has_site, site_url,
              dwell_secs, pages_seen, session_hash,
              consent_at, consent_text, ip_trunc, is_test, is_partial, mail_status)
             VALUES (UTC_TIMESTAMP(), ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, \'pending\')',
            [
                $d['name'], $d['email'], $d['phone'], $d['service'], $d['budget'], $d['message'],
                /* `company` is the honeypot on the public form; the real company
                   name arrives as company_name and is the one worth keeping. */
                cut((string)($d['company_name'] ?? ''), 120),
                $d['page'], $d['referrer'],
                $d['utm_source'], $d['utm_medium'], $d['utm_campaign'],
                (string)($d['utm_term'] ?? ''), (string)($d['utm_content'] ?? ''),
                (string)($d['gclid'] ?? ''), (string)($d['gbraid'] ?? ''), (string)($d['wbraid'] ?? ''),
                (string)($d['msclkid'] ?? ''), (string)($d['fbclid'] ?? ''),
                (string)($d['landing_page'] ?? ''), (string)($d['variant'] ?? ''),
                (string)($d['timeline'] ?? ''), (string)($d['has_site'] ?? ''), (string)($d['site_url'] ?? ''),
                (int)($d['dwell_secs'] ?? 0), (int)($d['pages_seen'] ?? 0),
                self::sessionHash(),
                !empty($d['consent']) ? gmdate('Y-m-d H:i:s') : null,
                !empty($d['consent']) ? self::CONSENT_VERSION : '',
                ip_truncate(client_ip()), $isTest ? 1 : 0, $partial ? 1 : 0,
            ]
        );

        Score::apply($id);
        return $id;
    }

    /** The consent wording in force, stored with each lead so a later
        change to the text cannot rewrite what someone actually agreed to. */
    public const CONSENT_VERSION = '2026-09-v1';

    /** The exact words shown beside the checkbox, recorded with the consent. */
    public static function consentWording(): string
    {
        return (string)Settings::get('consent_wording',
            'I agree to Wwwebtech contacting me about this enquiry by email, phone '
          . 'and WhatsApp. No newsletter, and you can ask us to stop at any time.');
    }

    /** Same non-reversible, daily-rotating identifier the analytics use. */
    private static function sessionHash(): string
    {
        try { return Analytics::sessionHash(ip_truncate(client_ip()),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? '')); }
        catch (Throwable) { return ''; }
    }

    /** Record the outcome of the notification email against the lead. */
    public static function markMail(int $id, string $status, string $error = ''): void
    {
        DB::run('UPDATE wwt_leads SET mail_status = ?, mail_error = ? WHERE id = ?',
            [$status, cut($error, 240), $id]);
    }

    /* ── Email bodies ──────────────────────────────────────── */

    /** The enquiry, as it reaches the owner. */
    public static function ownerEmail(int $id, array $d, bool $isTest): array
    {
        $site  = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
        $rows  = [
            'Name'    => $d['name'],
            'Email'   => $d['email'],
            'Phone'   => $d['phone'],
            'Needs'   => $d['service'],
            'Budget'  => $d['budget'],
        ];
        $meta = array_filter([
            'Page'     => $d['page'] !== '' ? $site . $d['page'] : '',
            'Campaign' => trim(implode(' / ', array_filter([$d['utm_source'], $d['utm_medium'], $d['utm_campaign']]))),
            'Referrer' => ref_domain($d['referrer'], (string)(parse_url((string)cfg('site.url', ''), PHP_URL_HOST) ?: 'wwwebtech.in')),
        ], static fn($v) => $v !== '');

        $text = ($isTest ? "*** TEST SUBMISSION — not a real enquiry ***\n\n" : '');
        foreach ($rows as $k => $v) if ($v !== '') $text .= str_pad($k . ':', 9) . $v . "\n";
        $text .= "\nMessage:\n" . $d['message'] . "\n\n---\n";
        foreach ($meta as $k => $v) $text .= str_pad($k . ':', 11) . $v . "\n";
        $text .= "Received:  " . local_time(gmdate('Y-m-d H:i:s')) . " IST\n";
        $text .= "In panel:  " . $site . "/admin/?p=lead&id=" . $id . "\n";

        $esc = static fn($v) => e((string)$v);
        $tr  = '';
        foreach ($rows as $k => $v) {
            if ($v === '') continue;
            $tr .= '<tr><td style="padding:6px 14px 6px 0;color:#686D69;font-size:13px;'
                 . 'white-space:nowrap;vertical-align:top">' . $esc($k) . '</td>'
                 . '<td style="padding:6px 0;color:#131614;font-size:15px;font-weight:600">'
                 . $esc($v) . '</td></tr>';
        }
        $metaHtml = '';
        foreach ($meta as $k => $v) {
            $metaHtml .= '<div style="color:#686D69;font-size:12px;margin-top:3px">'
                       . $esc($k) . ': ' . $esc($v) . '</div>';
        }

        $html = '<div style="background:#FAF8F4;padding:24px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
          . '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #E6E2D8;border-radius:3px">'
          . ($isTest ? '<div style="background:#FFF4E0;color:#A34E00;padding:9px 22px;font-size:12px;'
                     . 'font-weight:700;letter-spacing:.06em;text-transform:uppercase">Test submission</div>' : '')
          . '<div style="border-top:3px solid #E07000;padding:22px 22px 6px">'
          . '<div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#686D69">New enquiry</div>'
          . '<h1 style="margin:6px 0 16px;font-size:21px;color:#131614;font-weight:600">' . $esc($d['name']) . '</h1>'
          . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $tr . '</table>'
          . '<div style="margin:18px 0 4px;padding-top:16px;border-top:1px solid #E6E2D8;'
          . 'font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#686D69">Message</div>'
          . '<div style="white-space:pre-wrap;color:#131614;font-size:15px;line-height:1.6">'
          . $esc($d['message']) . '</div>'
          . '<div style="margin-top:22px"><a href="' . $esc($site . '/admin/?p=lead&id=' . $id) . '"'
          . ' style="display:inline-block;background:#E07000;color:#131614;font-weight:700;font-size:14px;'
          . 'text-decoration:none;padding:10px 18px;border-radius:2px">Open in the panel</a></div>'
          . '<div style="margin:18px 0 0;padding-top:14px;border-top:1px solid #E6E2D8">' . $metaHtml
          . '<div style="color:#686D69;font-size:12px;margin-top:3px">Received: '
          . $esc(local_time(gmdate('Y-m-d H:i:s'))) . ' IST</div></div>'
          . '<div style="height:16px"></div></div></div></div>';

        return [
            'subject' => ($isTest ? '[TEST] ' : '') . 'New enquiry · ' . $d['name']
                       . ($d['service'] !== '' ? ' · ' . $d['service'] : ''),
            'text'    => $text,
            'html'    => $html,
        ];
    }

    /** The acknowledgement the enquirer gets. */
    public static function ackEmail(array $d): array
    {
        $promise = self::replyPromise();
        $site    = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
        $to      = (string)Mailer::leadRecipient();

        $text = "Hello {$d['name']},\n\n"
              . "Thanks for contacting Wwwebtech.\n\n"
              . "We have your message and a real person will come back to you within {$promise}.\n"
              . "If it is urgent, reply to this email — it reaches us directly.\n\n"
              . "For reference, this is what you sent:\n\n"
              . rtrim($d['message']) . "\n\n"
              . "Regards,\nWwwebtech\nEast Delhi, Delhi, India\n{$site}\n";

        $html = '<div style="background:#FAF8F4;padding:24px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
          . '<div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #E6E2D8;'
          . 'border-top:3px solid #E07000;border-radius:3px;padding:24px">'
          . '<h1 style="margin:0 0 12px;font-size:20px;color:#131614;font-weight:600">Thanks — we have it.</h1>'
          . '<p style="margin:0 0 14px;color:#4E5450;font-size:15px;line-height:1.6">Hello '
          . e($d['name']) . ', thanks for contacting Wwwebtech. A real person will come back to you within <b>'
          . e($promise) . '</b>. If it is urgent, just reply to this email — it reaches us directly.</p>'
          . '<div style="margin:18px 0 6px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;'
          . 'color:#686D69">What you sent</div>'
          . '<div style="white-space:pre-wrap;color:#4E5450;font-size:14px;line-height:1.6;'
          . 'background:#FAF8F4;border-left:2px solid #E6E2D8;padding:10px 14px">' . e($d['message']) . '</div>'
          . '<p style="margin:20px 0 0;color:#686D69;font-size:12px;line-height:1.6">Wwwebtech · East Delhi, Delhi, India<br>'
          . '<a href="' . e($site) . '" style="color:#A34E00">' . e(preg_replace('#^https?://#', '', $site)) . '</a>'
          . ' · <a href="mailto:' . e($to) . '" style="color:#A34E00">' . e($to) . '</a></p>'
          . '</div></div>';

        return ['subject' => 'We’ve received your enquiry — Wwwebtech', 'text' => $text, 'html' => $html];
    }

    /* ── CSV ───────────────────────────────────────────────── */

    public const CSV_COLUMNS = [
        'id' => 'ID', 'ts' => 'Received (IST)', 'status' => 'Status', 'name' => 'Name',
        'email' => 'Email', 'phone' => 'Phone', 'service' => 'Needs', 'budget' => 'Budget',
        'message' => 'Message', 'page' => 'Page', 'utm_source' => 'UTM source',
        'utm_medium' => 'UTM medium', 'utm_campaign' => 'UTM campaign',
        'referrer' => 'Referrer', 'country' => 'Country', 'notes' => 'Notes',
        'mail_status' => 'Email sent', 'is_test' => 'Test',
    ];

    /**
     * A spreadsheet cell starting with = + - @ is executed as a formula by
     * Excel and Sheets. Prefixing a single quote neutralises that without
     * changing what the reader sees.
     */
    public static function csvCell(mixed $v): string
    {
        $s = (string)$v;
        return ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) ? "'" . $s : $s;
    }

    public static function csvRow(array $lead): array
    {
        $out = [];
        foreach (array_keys(self::CSV_COLUMNS) as $k) {
            $v = $lead[$k] ?? '';
            if ($k === 'ts')      $v = local_time((string)$v, 'Y-m-d H:i');
            if ($k === 'is_test') $v = ((int)$v === 1) ? 'yes' : '';
            $out[] = self::csvCell($v);
        }
        return $out;
    }
}
