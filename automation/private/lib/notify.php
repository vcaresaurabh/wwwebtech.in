<?php
/* ============================================================
   notify.php — the fan-out when a lead lands (§4).

   Three independent deliveries: the company mailbox, the owner's personal
   mailbox, and an instant push to their phone. Independent is the
   operative word — each is wrapped, and one failing never stops the
   others or the request. The lead is already saved by the time any of
   this runs; notification is a copy, not the record.

   The internal email is written to be a DECISION, not a data dump. The
   subject alone should be enough to decide whether to stop what you are
   doing, and the body should be actionable from a phone without opening
   a laptop.
   ============================================================ */

declare(strict_types=1);

final class Notify
{
    /** Everything that happened, so the caller can record and report it. */
    public static function newLead(int $leadId): array
    {
        $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]);
        if (!$l) return ['ok' => false, 'error' => 'no such lead'];

        /* A partial lead is a recoverable record, not a customer who asked
           to be contacted. It must never trigger any of this. */
        if ((int)$l['is_partial'] === 1) {
            return ['ok' => true, 'skipped' => 'partial lead — not notified by design'];
        }

        /* Every recipient decides what it receives. "Every lead" gets
           each enquiry; "hot leads only" gets just the ones worth
           interrupting someone for. Each address is its own attempt, so
           one bouncing address cannot stop the others. */
        $channels = [];
        foreach (self::recipients() as $r) {
            $wants = in_array('every_lead', $r['roles'], true)
                  || (in_array('hot_only', $r['roles'], true) && (string)$l['band'] === 'hot');
            if (!$wants) continue;
            $channels['email:' . $r['email']] = static fn() => self::email($l, $r['email']);
        }
        $channels['telegram'] = static fn() => Telegram::newLead($l);

        $out = [];
        foreach ($channels as $channel => $fn) {
            try {
                $r = $fn();
                $out[$channel] = $r;
            } catch (Throwable $t) {
                wwt_log('notify', $channel . ' threw', ['lead' => $leadId, 'err' => $t->getMessage()]);
                $out[$channel] = ['ok' => false, 'error' => cut($t->getMessage(), 200)];
            }
        }

        Timeline::add($leadId, 'notified', '', implode(', ', array_map(
            static fn($k, $r) => $k . '=' . (!empty($r['ok']) ? 'ok' : (!empty($r['skipped']) ? 'skipped' : 'failed')),
            array_keys($out), $out)));

        return ['ok' => true, 'channels' => $out];
    }

    /* ── Recipients (Connections card) ─────────────────────── */

    public const ROLES = ['every_lead' => 'Every lead', 'hot_only' => 'Hot leads only',
                          'digest' => 'Daily digest', 'errors' => 'System errors'];

    /**
     * Who is told what. Seeded from the two addresses that existed before
     * the list did, so the first render of the card is already true.
     *
     * @return list<array{id:string,email:string,label:string,roles:list<string>}>
     */
    public static function recipients(): array
    {
        $list = Settings::json('alert_recipients', []);
        $out = [];
        foreach ($list as $r) {
            if (!is_array($r) || !filter_var((string)($r['email'] ?? ''), FILTER_VALIDATE_EMAIL)) continue;
            $out[] = ['id' => (string)($r['id'] ?? substr(md5((string)$r['email']), 0, 8)),
                      'email' => strtolower(trim((string)$r['email'])), 'label' => cut((string)($r['label'] ?? ''), 60),
                      'roles' => array_values(array_intersect((array)($r['roles'] ?? []), array_keys(self::ROLES)))];
        }
        if (!$out) {
            $seed = [];
            $company = Mailer::leadRecipient();
            if ($company !== '') $seed[] = ['id' => 'company', 'email' => $company, 'label' => 'Company mailbox', 'roles' => ['every_lead', 'digest', 'errors']];
            $personal = trim((string)Settings::get('personal_email', (string)cfg('site.personal_email', '')));
            if ($personal !== '' && strcasecmp($personal, $company) !== 0) {
                $seed[] = ['id' => 'personal', 'email' => strtolower($personal), 'label' => 'Personal', 'roles' => ['every_lead', 'digest']];
            }
            return $seed;
        }
        return $out;
    }

    public static function saveRecipients(array $list): void
    {
        Settings::set('alert_recipients', json_encode(array_values($list), JSON_UNESCAPED_UNICODE) ?: '[]');
        /* Legacy keys keep pointing at something sensible. */
        $every = array_values(array_filter($list, static fn($r) => in_array('every_lead', (array)($r['roles'] ?? []), true)));
        if ($every) {
            Settings::set('lead_email', (string)$every[0]['email']);
            Settings::set('personal_email', (string)($every[1]['email'] ?? ''));
        }
    }

    public static function testRecipient(string $to): array
    {
        return Mailer::send(['to' => $to, 'subject' => 'Test from the Wwwebtech panel',
            'text' => "This address receives alerts from the Wwwebtech panel.\n\nSent " . local_time(gmdate('Y-m-d H:i:s')) . " IST\n"]);
    }

    /**
     * Something broke. Tell the owner on every channel that still works.
     * $broken names the channel that is known to be down, so the alert
     * about email failing is not sent by email.
     */
    public static function systemAlert(string $subject, string $text, string $broken = ''): array
    {
        $out = [];
        if ($broken !== 'telegram') {
            $r = Telegram::sendTo('all', '⚠️ <b>' . Telegram::esc($subject) . '</b>' . "\n\n" . Telegram::esc(cut($text, 1500)));
            $out['telegram'] = $r['ok'];
        }
        if ($broken !== 'mail') {
            foreach (self::recipients() as $r) {
                if (!in_array('errors', $r['roles'], true)) continue;
                $x = Mailer::send(['to' => $r['email'], 'subject' => '[Wwwebtech] ' . $subject, 'text' => $text]);
                $out['email:' . $r['email']] = $x['ok'];
            }
        }
        wwt_log('notify', 'system alert', ['subject' => $subject, 'channels' => $out]);
        return $out;
    }

    /** One email each morning to whoever asked for it. */
    public static function digest(): string
    {
        $to = array_values(array_filter(self::recipients(), static fn($r) => in_array('digest', $r['roles'], true)));
        if (!$to) return 'nobody asked for a digest';
        $leads = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE is_test = 0 AND is_partial = 0 AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [], 0);
        $hot   = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE is_test = 0 AND is_partial = 0 AND band = 'hot' AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [], 0);
        $new   = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE status = 'new' AND is_test = 0", [], 0);
        $sent  = (int)DB::val("SELECT COUNT(*) FROM wwt_messages WHERE direction = 'out' AND status = 'sent' AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [], 0);
        $repl  = (int)DB::val("SELECT COUNT(*) FROM wwt_messages WHERE direction = 'in' AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [], 0);
        $wait  = (int)DB::val("SELECT COUNT(*) FROM wwt_messages WHERE status = 'pending_review'", [], 0);
        $conn  = Connections::summary();
        $site  = rtrim((string)cfg('site.url', ''), '/');
        $text  = "Yesterday at " . (string)cfg('site.url', 'wwwebtech.in') . "\n\n"
               . "Leads: $leads ($hot hot)\nWaiting for a reply from you: $new\n"
               . "Follow-ups sent: $sent · Replies received: $repl · Awaiting your approval: $wait\n\n"
               . "Connections: " . $conn['line'] . "\n\nPanel: $site/admin/\n";
        $n = 0;
        foreach ($to as $r) { $x = Mailer::send(['to' => $r['email'], 'subject' => 'Wwwebtech daily: ' . $leads . ' lead' . ($leads === 1 ? '' : 's') . ($new ? ", $new waiting" : ''), 'text' => $text]); if ($x['ok']) $n++; }
        return "sent to $n of " . count($to);
    }

    /**
     * The decision-ready brief.
     *
     * Subject carries the three things that decide whether to act now:
     * how hot, who, and what they want at what budget.
     */
    public static function email(array $l, string $to): array
    {
        if (trim($to) === '') return ['ok' => true, 'skipped' => 'no address'];

        $band  = (string)$l['band'];
        $site  = rtrim((string)cfg('site.url', ''), '/');
        $panel = $site . '/admin/?p=lead&id=' . (int)$l['id'];
        $waNum = preg_replace('/\D/', '', (string)$l['phone']) ?? '';
        if (strlen($waNum) === 10) $waNum = '91' . $waNum;
        $wa = $waNum !== '' ? 'https://wa.me/' . $waNum : '';

        $subject = sprintf('%s %s lead — %s%s%s',
            Score::emoji($band), strtoupper(Score::label($band)), (string)$l['name'],
            $l['service'] !== '' ? ', ' . $l['service'] : '',
            $l['budget']  !== '' ? ', ' . $l['budget']  : '');

        $rows = array_filter([
            'Name'    => (string)$l['name'],
            'Phone'   => (string)$l['phone'],
            'Email'   => (string)$l['email'],
            'Company' => (string)$l['company'],
            'Wants'   => (string)$l['service'],
            'Budget'  => (string)$l['budget'],
            'When'    => self::timeline((string)$l['timeline']),
            'Site'    => (string)$l['site_url'],
        ], static fn($v) => trim((string)$v) !== '');

        $where = array_filter([
            'Converted on' => $l['landing_page'] !== '' ? '/lp/' . $l['landing_page'] . '/' : (string)$l['page'],
            'Campaign'     => trim(implode(' / ', array_filter([$l['utm_source'], $l['utm_medium'], $l['utm_campaign']]))),
            'Keyword'      => (string)$l['utm_term'],
            'Paid click'   => $l['gclid'] !== '' ? 'Google Ads' : ($l['msclkid'] !== '' ? 'Microsoft Ads' : ''),
            'Score'        => (int)$l['score'] . ' (' . Score::label($band) . ')',
            'Time on page' => (int)$l['dwell_secs'] > 0 ? (int)$l['dwell_secs'] . 's' : '',
        ], static fn($v) => trim((string)$v) !== '');

        /* Plain text first, because that is what a phone notification
           previews and what survives every mail client ever written. */
        $text = strtoupper(Score::label($band)) . " LEAD\n\n";
        foreach ($rows as $k => $v)  $text .= str_pad($k . ':', 10) . $v . "\n";
        $text .= "\nWhat they said:\n" . (string)$l['message'] . "\n\n---\n";
        foreach ($where as $k => $v) $text .= str_pad($k . ':', 15) . $v . "\n";
        $text .= "\nReply:  " . ($wa ?: 'no phone given')
               . "\nPanel:  " . $panel . "\n";

        $e = static fn($v) => e((string)$v);
        $tr = '';
        foreach ($rows as $k => $v) {
            $tr .= '<tr><td style="padding:5px 14px 5px 0;color:#686D69;font-size:13px;'
                 . 'white-space:nowrap;vertical-align:top">' . $e($k) . '</td>'
                 . '<td style="padding:5px 0;color:#131614;font-size:15px;font-weight:600">'
                 . $e($v) . '</td></tr>';
        }
        $meta = '';
        foreach ($where as $k => $v) {
            $meta .= '<div style="color:#686D69;font-size:12px;margin-top:3px">'
                   . $e($k) . ': ' . $e($v) . '</div>';
        }

        $accent = ['hot' => '#B3261E', 'warm' => '#E07000', 'cold' => '#686D69'][$band] ?? '#E07000';
        $btn = static fn(string $href, string $label, string $bg, string $fg) =>
            '<a href="' . $href . '" style="display:inline-block;background:' . $bg . ';color:' . $fg
          . ';font-weight:700;font-size:14px;text-decoration:none;padding:11px 18px;'
          . 'border-radius:2px;margin:0 6px 6px 0">' . $label . '</a>';

        $html = '<div style="background:#FAF8F4;padding:24px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
          . '<div style="max-width:580px;margin:0 auto;background:#fff;border:1px solid #E6E2D8;border-radius:3px">'
          . '<div style="border-top:4px solid ' . $accent . ';padding:20px 22px 6px">'
          . '<div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:' . $accent
          . ';font-weight:700">' . $e(Score::label($band)) . ' lead · score ' . (int)$l['score'] . '</div>'
          . '<h1 style="margin:6px 0 16px;font-size:22px;color:#131614;font-weight:600">' . $e($l['name']) . '</h1>'
          . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $tr . '</table>'
          . '<div style="margin:18px 0 4px;padding-top:16px;border-top:1px solid #E6E2D8;font-size:11px;'
          . 'letter-spacing:.1em;text-transform:uppercase;color:#686D69">What they said</div>'
          . '<div style="white-space:pre-wrap;color:#131614;font-size:15px;line-height:1.6">'
          . $e($l['message']) . '</div>'
          . '<div style="margin-top:20px">'
          . ($wa ? $btn($wa, 'WhatsApp ' . $e($l['name']), '#25D366', '#0b3d1e') : '')
          . ($l['phone'] !== '' ? $btn('tel:' . $e(preg_replace('/[^\d+]/', '', (string)$l['phone'])), 'Call', '#F2EEE6', '#131614') : '')
          . $btn($panel, 'Open in panel', '#E07000', '#131614')
          . '</div>'
          . '<div style="margin:16px 0 0;padding-top:14px;border-top:1px solid #E6E2D8">' . $meta . '</div>'
          . '<div style="height:14px"></div></div></div></div>';

        return Mailer::send([
            'to'            => $to,
            'subject'       => $subject,
            'text'          => $text,
            'html'          => $html,
            'reply_to'      => (string)$l['email'],
            'reply_to_name' => (string)$l['name'],
        ]);
    }

    private static function timeline(string $v): string
    {
        return ['asap' => 'As soon as possible', '1-3m' => 'In 1–3 months',
                'research' => 'Just researching'][$v] ?? $v;
    }
}

/* ============================================================
   Timeline — one place to record what happened to a lead.
   Everything the Conversations inbox renders comes from here.
   ============================================================ */
final class Timeline
{
    public static function add(int $leadId, string $kind, string $channel = '',
                               string $detail = '', string $actor = 'system',
                               array $meta = []): void
    {
        try {
            DB::run('INSERT INTO wwt_lead_events (lead_id, ts, kind, channel, detail, actor, meta)
                     VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?, ?)',
                [$leadId, cut($kind, 40), cut($channel, 20), cut($detail, 500), cut($actor, 150),
                 $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null]);
        } catch (Throwable $t) {
            wwt_log('timeline', 'write failed', ['lead' => $leadId, 'err' => $t->getMessage()]);
        }
    }

    /** @return array<int,array> newest last, so it reads as a conversation */
    public static function of(int $leadId, int $limit = 200): array
    {
        return DB::all('SELECT * FROM wwt_lead_events WHERE lead_id = ? ORDER BY ts, id LIMIT ' . (int)$limit,
                       [$leadId]);
    }
}
