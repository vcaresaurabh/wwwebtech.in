<?php
/* ============================================================
   funnel.php — the nurture sequence engine.

   The single worst failure mode in this whole build is a sequence that
   keeps messaging someone who already replied. Every stop condition in
   §6.4 is therefore checked in ONE place — stopReason() — which is
   consulted before every single send, not just at enrolment. There is
   no path to a message that skips it.

   Design notes worth keeping:

   · Offsets are in BUSINESS time for most steps, wall-clock for the
     first. The acknowledgement has to go out within a minute whenever it
     is; nothing else does.

   · Quiet hours QUEUE, they never send. A message deferred to 09:30 is
     better than one delivered at 02:00, which reads as automation and
     costs the trust the sequence was built to earn.

   · Everything runs off wwt_messages rows with send_after set, so the
     panel can see what is coming, hold it for review, and the owner can
     cancel it. Nothing is scheduled only in someone's head.
   ============================================================ */

declare(strict_types=1);

final class Funnel
{
    /* Business hours, IST. Monday–Saturday. */
    public const DAY_START = '09:30';
    public const DAY_END   = '20:00';
    public const QUIET_FROM = 21;   // 21:00
    public const QUIET_TO   = 9;    // 09:00

    /* Sending guardrails (§6.5). */
    public const MAX_PER_CHANNEL_PER_DAY = 1;
    public const MAX_PER_DAY             = 2;
    public const MIN_GAP_MINUTES         = 45;

    /* ── The global switch ─────────────────────────────────── */

    public static function enabled(): bool
    {
        return Settings::bool('funnel_enabled', false);
    }

    /** The kill switch (§6.5). One setting, checked before every send. */
    public static function killed(): bool
    {
        return Settings::bool('funnel_kill_switch', false);
    }

    /* ── Enrolment ─────────────────────────────────────────── */

    /** Which sequence a lead belongs in, by band (§6.2). */
    public static function sequenceFor(string $band): string
    {
        return ['hot' => 'hot', 'warm' => 'standard', 'cold' => 'nurture-light'][$band] ?? 'standard';
    }

    /**
     * Put a lead into the right sequence and schedule step 0.
     * Safe to call twice: the enrolment table has a unique key per pair.
     */
    public static function enrol(int $leadId, ?string $sequenceKey = null): array
    {
        $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]);
        if (!$l) return ['ok' => false, 'error' => 'no such lead'];

        /* A partial lead never asked to be contacted. A test lead is not a
           person. Neither is enrolled, ever. */
        if ((int)$l['is_partial'] === 1) return ['ok' => false, 'error' => 'partial lead'];
        if ((int)$l['is_test'] === 1)    return ['ok' => false, 'error' => 'test lead'];

        $why = self::stopReason($l);
        if ($why !== '') return ['ok' => false, 'error' => $why];

        $key = $sequenceKey ?: self::sequenceFor((string)$l['band']);
        $seq = DB::one('SELECT * FROM wwt_sequences WHERE key_name = ? AND is_active = 1', [$key]);
        if (!$seq) return ['ok' => false, 'error' => 'sequence "' . $key . '" is not set up'];

        $existing = DB::one('SELECT * FROM wwt_sequence_enrollments WHERE lead_id = ? AND sequence_id = ?',
                            [$leadId, (int)$seq['id']]);
        if ($existing) return ['ok' => true, 'already' => true, 'enrollment' => (int)$existing['id']];

        $id = DB::insert(
            'INSERT INTO wwt_sequence_enrollments (lead_id, sequence_id, step, next_run_at, status, started_at)
             VALUES (?, ?, 0, UTC_TIMESTAMP(), \'active\', UTC_TIMESTAMP())',
            [$leadId, (int)$seq['id']]);

        Timeline::add($leadId, 'enrolled', '', 'sequence: ' . $key);
        return ['ok' => true, 'enrollment' => $id, 'sequence' => $key];
    }

    /* ── Seeding ───────────────────────────────────────────── */

    /**
     * Load the seed sequences and templates.
     * Existing rows are left alone: the owner can edit a template in the
     * panel and re-running this must not overwrite their wording.
     */
    public static function seed(): array
    {
        $seqs = require WWT_PRIVATE . '/data/sequences.php';
        $tpls = require WWT_PRIVATE . '/data/templates.php';
        $added = ['sequences' => 0, 'steps' => 0, 'templates' => 0];

        foreach ($tpls as $key => [$channel, $category, $subject, $body]) {
            if (DB::val('SELECT 1 FROM wwt_templates WHERE key_name = ?', [$key]) !== null) continue;
            DB::run('INSERT INTO wwt_templates (key_name, channel, category, subject, body, updated_at)
                     VALUES (?,?,?,?,?, UTC_TIMESTAMP())',
                    [$key, $channel, $category, $subject, trim($body)]);
            $added['templates']++;
        }

        foreach ($seqs as $key => $def) {
            $id = DB::val('SELECT id FROM wwt_sequences WHERE key_name = ?', [$key]);
            if ($id === null) {
                $id = DB::insert('INSERT INTO wwt_sequences (key_name, title, description) VALUES (?,?,?)',
                                 [$key, $def['title'], $def['description']]);
                $added['sequences']++;
            }
            foreach ($def['steps'] as [$no, $off, $biz, $channel, $tplKey, $purpose]) {
                if (DB::val('SELECT 1 FROM wwt_sequence_steps WHERE sequence_id = ? AND step_no = ?',
                            [(int)$id, $no]) !== null) continue;
                DB::run('INSERT INTO wwt_sequence_steps
                         (sequence_id, step_no, offset_minutes, business_hours, channel, template_key, purpose)
                         VALUES (?,?,?,?,?,?,?)',
                        [(int)$id, $no, $off, $biz ? 1 : 0, $channel, $tplKey, $purpose]);
                $added['steps']++;
            }
        }
        if (array_sum($added) > 0) audit('funnel_seed', json_encode($added));
        return $added;
    }

    /* ── Stop conditions (§6.4) ────────────────────────────── */

    /**
     * The ONE place that decides whether a lead may be messaged.
     *
     * Returns '' when it is fine to continue, or a human-readable reason
     * why not. Consulted before every send, not merely at enrolment —
     * a reply that arrives between scheduling and sending has to stop the
     * message that is already queued.
     */
    public static function stopReason(array $l): string
    {
        if ((int)($l['do_not_contact'] ?? 0) === 1) return 'do not contact';
        if ((int)($l['is_partial'] ?? 0) === 1)     return 'partial lead';
        if ((int)($l['is_test'] ?? 0) === 1)        return 'test lead';

        $status = (string)($l['status'] ?? 'new');
        if ($status !== 'new' && $status !== 'contacted') {
            return 'status is ' . $status;
        }

        $id = (int)($l['id'] ?? 0);
        if ($id > 0) {
            /* Any inbound message on any channel, ever. This is the
               condition that matters most: someone who replied is now a
               conversation, not a sequence. */
            if (DB::val('SELECT 1 FROM wwt_messages WHERE lead_id = ? AND direction = ? LIMIT 1',
                        [$id, 'in']) !== null) {
                return 'they replied';
            }
            if (DB::val('SELECT 1 FROM wwt_bookings WHERE lead_id = ? AND status = ? LIMIT 1',
                        [$id, 'booked']) !== null) {
                return 'a call is booked';
            }
            /* Two consecutive hard bounces means the address is wrong and
               continuing damages the sending domain's reputation. */
            $bounces = (int)DB::val(
                "SELECT COUNT(*) FROM wwt_messages WHERE lead_id = ? AND status = 'failed'
                 AND channel = 'email' AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)",
                [$id], 0);
            if ($bounces >= 2) return 'two hard bounces';
        }
        return '';
    }

    /** Halt an enrolment and record why. */
    public static function stop(int $leadId, string $reason): int
    {
        $n = DB::run(
            "UPDATE wwt_sequence_enrollments SET status = 'stopped', stop_reason = ?,
             ended_at = UTC_TIMESTAMP(), next_run_at = NULL
             WHERE lead_id = ? AND status IN ('active','paused')",
            [cut($reason, 120), $leadId])->rowCount();

        if ($n > 0) {
            /* Anything already queued but not yet sent goes too. Stopping
               the schedule while leaving a message in the outbox is how a
               customer gets contacted after they told you to stop. */
            DB::run("UPDATE wwt_messages SET status = 'skipped', error = ?
                     WHERE lead_id = ? AND status IN ('queued','pending_review')",
                    [cut('sequence stopped: ' . $reason, 240), $leadId]);
            Timeline::add($leadId, 'sequence_stopped', '', $reason);
        }
        return $n;
    }

    /** Called whenever anything happens that might mean "stop". */
    public static function checkStop(int $leadId): string
    {
        $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]);
        if (!$l) return '';
        $why = self::stopReason($l);
        if ($why !== '') self::stop($leadId, $why);
        return $why;
    }

    /* ── Time (§6.5) ───────────────────────────────────────── */

    public static function tz(): DateTimeZone
    {
        return new DateTimeZone(WWT_TZ_DISPLAY);
    }

    /** Holidays are a settings list so the owner can maintain them. */
    public static function holidays(): array
    {
        $raw = (string)Settings::get('funnel_holidays', '');
        return array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $raw) ?: [])));
    }

    public static function isHoliday(DateTimeImmutable $d): bool
    {
        return in_array($d->format('Y-m-d'), self::holidays(), true);
    }

    /** Inside quiet hours the message waits. It is never sent late at night. */
    public static function inQuietHours(DateTimeImmutable $local): bool
    {
        $h = (int)$local->format('G');
        return $h >= self::QUIET_FROM || $h < self::QUIET_TO;
    }

    public static function isBusinessTime(DateTimeImmutable $local): bool
    {
        if ((int)$local->format('N') === 7) return false;          // Sunday
        if (self::isHoliday($local)) return false;
        $hm = $local->format('H:i');
        return $hm >= self::DAY_START && $hm <= self::DAY_END;
    }

    /**
     * The next moment a message may actually go out, in UTC.
     *
     * $businessOnly false still respects quiet hours — "wall clock" in the
     * sequence table means "do not wait for business hours", not "wake
     * someone at 3am".
     */
    public static function nextSendableUtc(DateTimeImmutable $fromUtc, bool $businessOnly = true): DateTimeImmutable
    {
        $local = $fromUtc->setTimezone(self::tz());

        for ($i = 0; $i < 14 * 24; $i++) {
            $okQuiet = !self::inQuietHours($local);
            $okBiz   = !$businessOnly || self::isBusinessTime($local);
            if ($okQuiet && $okBiz) {
                return $local->setTimezone(new DateTimeZone('UTC'));
            }
            /* Jump to the start of the next window rather than crawling
               hour by hour — this runs on every scheduled step. */
            if (self::inQuietHours($local)) {
                $local = $local->modify('+1 day')->setTime(self::QUIET_TO, 30);
                if ((int)$local->format('G') >= self::QUIET_TO) continue;
            }
            $local = $local->modify('+1 hour');
        }
        /* Fourteen days of closed windows is not a real calendar; rather
           than loop forever, send at the next business-hours opening. */
        return $fromUtc->modify('+1 day');
    }

    /**
     * Add business minutes to a UTC instant.
     * Used for the "T+3 business hours" style offsets.
     */
    public static function addBusinessMinutes(DateTimeImmutable $fromUtc, int $minutes): DateTimeImmutable
    {
        if ($minutes <= 0) return self::nextSendableUtc($fromUtc, true);
        $cursor = self::nextSendableUtc($fromUtc, true)->setTimezone(self::tz());
        $left = $minutes;
        $guard = 0;
        while ($left > 0 && $guard++ < 20000) {
            $cursor = $cursor->modify('+15 minutes');
            if (self::isBusinessTime($cursor) && !self::inQuietHours($cursor)) $left -= 15;
        }
        return $cursor->setTimezone(new DateTimeZone('UTC'));
    }

    /* ── Rate limits (§6.5) ────────────────────────────────── */

    /* ── Unsubscribe (§9) ──────────────────────────────────── */

    /**
     * A signed, one-click unsubscribe URL.
     *
     * Signed rather than sequential so that guessing another lead's link is
     * not possible — an unsubscribe endpoint that takes a bare id lets
     * anyone silence anyone.
     */
    public static function unsubscribeUrl(int $leadId): string
    {
        return rtrim((string)cfg('site.url', ''), '/')
             . '/api/unsubscribe.php?l=' . $leadId . '&t=' . self::unsubToken($leadId);
    }

    public static function unsubToken(int $leadId): string
    {
        $salt = (string)cfg('secret_key', '') ?: (string)cfg('session_salt', '');
        return substr(hash_hmac('sha256', 'unsub:' . $leadId, $salt), 0, 32);
    }

    public static function checkUnsubToken(int $leadId, string $token): bool
    {
        return $token !== '' && hash_equals(self::unsubToken($leadId), $token);
    }

    /** Stop everything for this lead, permanently. */
    public static function optOut(int $leadId, string $how = 'unsubscribe link'): void
    {
        DB::run('UPDATE wwt_leads SET do_not_contact = 1, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$leadId]);
        DB::run('INSERT INTO wwt_consents (lead_id, ts, channel, granted, text_version, wording, ip_trunc, source)
                 VALUES (?, UTC_TIMESTAMP(), ?, 0, ?, ?, ?, ?)',
                [$leadId, 'all', Leads::CONSENT_VERSION, 'Withdrawn: ' . $how,
                 ip_truncate(client_ip()), $how]);
        self::stop($leadId, 'unsubscribed');
        Timeline::add($leadId, 'unsubscribed', '', $how, 'them');
        audit('lead_optout', 'id=' . $leadId . ' via ' . $how, 'public');
    }

    /** '' when a send is allowed now, else why it must wait. */
    public static function throttleReason(int $leadId, string $channel): string
    {
        $today = (int)DB::val(
            "SELECT COUNT(*) FROM wwt_messages WHERE lead_id = ? AND direction = 'out'
             AND status = 'sent' AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [$leadId], 0);
        if ($today >= self::MAX_PER_DAY) return 'already had ' . $today . ' messages today';

        $sameChannel = (int)DB::val(
            "SELECT COUNT(*) FROM wwt_messages WHERE lead_id = ? AND direction = 'out'
             AND status = 'sent' AND channel = ? AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)",
            [$leadId, $channel], 0);
        if ($sameChannel >= self::MAX_PER_CHANNEL_PER_DAY) return 'already had ' . $channel . ' today';

        $lastAt = DB::val(
            "SELECT ts FROM wwt_messages WHERE lead_id = ? AND direction = 'out' AND status = 'sent'
             ORDER BY ts DESC LIMIT 1", [$leadId]);
        if ($lastAt !== null) {
            $mins = (time() - strtotime((string)$lastAt . ' UTC')) / 60;
            if ($mins < self::MIN_GAP_MINUTES) {
                return sprintf('only %d minutes since the last message', (int)$mins);
            }
        }
        return '';
    }
}

/* ============================================================
   FunnelRunner — advances enrolments and sends what is due.

   Split from Funnel so the rules (above) stay readable and testable
   without the machinery around them.
   ============================================================ */
final class FunnelRunner
{
    /**
     * Advance every enrolment that is due.
     * Returns a one-line summary for the cron heartbeat.
     */
    public static function tick(int $limit = 50): string
    {
        if (Funnel::killed())   return 'skipped — the kill switch is on';
        if (!Funnel::enabled()) return 'skipped — the funnel is switched off';

        $due = DB::all(
            "SELECT e.*, s.key_name, s.auto_send, s.auto_after_minutes
             FROM wwt_sequence_enrollments e
             JOIN wwt_sequences s ON s.id = e.sequence_id
             WHERE e.status = 'active' AND e.next_run_at IS NOT NULL
               AND e.next_run_at <= UTC_TIMESTAMP()
             ORDER BY e.next_run_at LIMIT " . (int)$limit);

        $queued = 0; $stopped = 0; $done = 0; $held = 0;
        foreach ($due as $e) {
            $r = self::advance($e);
            if ($r === 'stopped') $stopped++;
            elseif ($r === 'done') $done++;
            elseif ($r === 'held') $held++;
            elseif ($r === 'queued') $queued++;
        }

        $sent = self::flush();
        return sprintf('%d due · %d queued, %d sent, %d awaiting review, %d stopped, %d finished',
            count($due), $queued, $sent, $held, $stopped, $done);
    }

    /** Move one enrolment to its next step. */
    private static function advance(array $e): string
    {
        $leadId = (int)$e['lead_id'];
        $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]);
        if (!$l) { Funnel::stop($leadId, 'lead deleted'); return 'stopped'; }

        /* Checked again here, not just at enrolment. Between scheduling and
           now the lead may have replied, been marked Won, or asked to stop. */
        $why = Funnel::stopReason($l);
        if ($why !== '') { Funnel::stop($leadId, $why); return 'stopped'; }

        $step = DB::one('SELECT * FROM wwt_sequence_steps WHERE sequence_id = ? AND step_no = ?',
                        [(int)$e['sequence_id'], (int)$e['step']]);
        if (!$step) {
            DB::run("UPDATE wwt_sequence_enrollments SET status = 'done', ended_at = UTC_TIMESTAMP(),
                     next_run_at = NULL WHERE id = ?", [(int)$e['id']]);
            Timeline::add($leadId, 'sequence_finished', '', (string)$e['key_name']);
            return 'done';
        }

        $result = self::runStep($l, $e, $step);

        /* Schedule the next step regardless of whether this one sent — a
           throttled or held message must not stall the whole sequence. */
        $next = DB::one('SELECT * FROM wwt_sequence_steps WHERE sequence_id = ? AND step_no = ?',
                        [(int)$e['sequence_id'], (int)$e['step'] + 1]);
        if ($next) {
            $at = self::whenFor($next);
            DB::run('UPDATE wwt_sequence_enrollments SET step = step + 1, next_run_at = ? WHERE id = ?',
                    [$at->format('Y-m-d H:i:s'), (int)$e['id']]);
        } else {
            DB::run("UPDATE wwt_sequence_enrollments SET step = step + 1, status = 'done',
                     ended_at = UTC_TIMESTAMP(), next_run_at = NULL WHERE id = ?", [(int)$e['id']]);
        }
        return $result;
    }

    /** When a step may run, honouring business hours and quiet hours. */
    private static function whenFor(array $step): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $off = (int)$step['offset_minutes'];

        if ((int)$step['business_hours'] === 1) {
            return Funnel::addBusinessMinutes($now, $off);
        }
        /* Wall clock still respects quiet hours — see nextSendableUtc. */
        return Funnel::nextSendableUtc($now->modify('+' . $off . ' minutes'), false);
    }

    /**
     * Execute one step: internal ping, or compose and queue a message.
     * Never sends inline — everything goes through the outbox so the panel
     * can see it, hold it, and the owner can cancel it.
     */
    private static function runStep(array $l, array $e, array $step): string
    {
        $leadId  = (int)$l['id'];
        $channel = (string)$step['channel'];

        /* Internal steps never touch the customer. */
        if ($channel === 'internal') {
            self::internalStep($l, $step);
            return 'queued';
        }

        /* WhatsApp falls back to email when it is off or capped — the
           sequence must work in Phase A with WhatsApp entirely absent. */
        if ($channel === 'whatsapp' && !WhatsApp::sendable()) {
            $channel = 'email';
        }

        $throttle = Funnel::throttleReason($leadId, $channel);
        if ($throttle !== '') {
            Timeline::add($leadId, 'step_throttled', $channel,
                'step ' . $step['step_no'] . ': ' . $throttle);
            return 'throttled';
        }

        $msg = FunnelWriter::compose($l, $step, $channel);
        if (!$msg['ok']) {
            Timeline::add($leadId, 'step_failed', $channel,
                'step ' . $step['step_no'] . ': ' . $msg['error']);
            return 'failed';
        }

        $seq = DB::one('SELECT * FROM wwt_sequences WHERE id = ?', [(int)$e['sequence_id']]);
        /* Review-before-send is the default (§0.4). Auto is opt-in, per
           sequence, and never turned on by this code. */
        $needsReview = $msg['is_ai'] && (int)($seq['auto_send'] ?? 0) !== 1;

        DB::insert(
            'INSERT INTO wwt_messages
             (lead_id, ts, direction, channel, subject, body, template_key, status, is_ai, send_after)
             VALUES (?, UTC_TIMESTAMP(), \'out\', ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$leadId, $channel, cut($msg['subject'], 255), $msg['body'],
             (string)$step['template_key'], $needsReview ? 'pending_review' : 'queued',
             $msg['is_ai'] ? 1 : 0]);

        if ($needsReview) {
            Timeline::add($leadId, 'awaiting_review', $channel, 'step ' . $step['step_no']);
            self::pingReviewer($l);
            return 'held';
        }
        return 'queued';
    }

    /** Step 1 of the hot sequence: nudge the owner, contact nobody. */
    private static function internalStep(array $l, array $step): void
    {
        $leadId = (int)$l['id'];
        $opened = DB::val("SELECT 1 FROM wwt_lead_events WHERE lead_id = ? AND kind = 'opened_in_panel' LIMIT 1",
                          [$leadId]);
        if ($opened !== null) {
            Timeline::add($leadId, 'escalation_skipped', 'internal', 'already opened in the panel');
            return;
        }
        $mins = max(1, (int)round(abs((int)$step['offset_minutes'])));
        Telegram::escalate($l, $mins);
        Timeline::add($leadId, 'escalated', 'telegram', 'not opened after ' . $mins . ' minutes');
    }

    private static function pingReviewer(array $l): void
    {
        if (!Settings::bool('funnel_review_ping', true)) return;
        $site = rtrim((string)cfg('site.url', ''), '/');
        Telegram::send(
            '✍️ <b>A reply is waiting for your approval</b>' . "\n\n"
            . Telegram::esc((string)$l['name']) . ' — ' . Telegram::esc((string)$l['service']),
            ['buttons' => [['Review it', $site . '/admin/?p=funnel']]]);
    }

    /**
     * Send everything that is queued and due.
     * Re-checks the stop conditions per message: a reply can arrive between
     * a message being queued and this running.
     */
    public static function flush(int $limit = 40): int
    {
        if (Funnel::killed()) return 0;

        $rows = DB::all(
            "SELECT m.*, l.name, l.email, l.phone, l.status AS lead_status,
                    l.do_not_contact, l.is_test, l.is_partial
             FROM wwt_messages m JOIN wwt_leads l ON l.id = m.lead_id
             WHERE m.direction = 'out' AND m.status = 'queued'
               AND (m.send_after IS NULL OR m.send_after <= UTC_TIMESTAMP())
             ORDER BY m.id LIMIT " . (int)$limit);

        $sent = 0;
        foreach ($rows as $m) {
            $leadId = (int)$m['lead_id'];
            $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]);
            $why = $l ? Funnel::stopReason($l) : 'lead deleted';
            if ($why !== '') {
                DB::run("UPDATE wwt_messages SET status = 'skipped', error = ? WHERE id = ?",
                        [cut('stopped: ' . $why, 240), (int)$m['id']]);
                Funnel::stop($leadId, $why);
                continue;
            }

            $local = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(Funnel::tz());
            if (Funnel::inQuietHours($local)) {
                $next = Funnel::nextSendableUtc(new DateTimeImmutable('now', new DateTimeZone('UTC')), false);
                DB::run('UPDATE wwt_messages SET send_after = ? WHERE id = ?',
                        [$next->format('Y-m-d H:i:s'), (int)$m['id']]);
                continue;
            }

            $r = self::deliver($m, $l);
            if (!empty($r['ok'])) {
                $sent++;
                DB::run("UPDATE wwt_messages SET status = 'sent', ts = UTC_TIMESTAMP(),
                         provider_id = ?, cost_paise = ? WHERE id = ?",
                        [cut((string)($r['provider_id'] ?? ''), 190), (int)($r['cost_paise'] ?? 0), (int)$m['id']]);
                Timeline::add($leadId, 'message_sent', (string)$m['channel'],
                    cut((string)$m['subject'] ?: (string)$m['template_key'], 200));
            } else {
                DB::run("UPDATE wwt_messages SET status = 'failed', error = ? WHERE id = ?",
                        [cut((string)($r['error'] ?? 'unknown'), 240), (int)$m['id']]);
                Timeline::add($leadId, 'message_failed', (string)$m['channel'],
                    cut((string)($r['error'] ?? ''), 200));
            }
        }
        return $sent;
    }

    /** Hand one message to its channel. */
    private static function deliver(array $m, array $l): array
    {
        if ((string)$m['channel'] === 'whatsapp') {
            return WhatsApp::sendTemplate($l, (string)$m['template_key'], (string)$m['body']);
        }

        $unsub = Funnel::unsubscribeUrl((int)$l['id']);
        $body  = (string)$m['body'];
        return Mailer::sendAs([
            'to'         => (string)$l['email'],
            'to_name'    => (string)$l['name'],
            'subject'    => (string)$m['subject'],
            'text'       => $body . "\n\n—\nTo stop these emails: " . $unsub . "\n",
            'html'       => FunnelWriter::html($body, $l, $unsub),
            'unsubscribe'=> $unsub,
            'lead_id'    => (int)$l['id'],
        ]);
    }
}
