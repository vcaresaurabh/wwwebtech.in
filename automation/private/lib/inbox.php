<?php
/* ============================================================
   inbox.php — inbound replies (§8).

   A reply is the single most important event in this system: it means a
   human is now in the conversation, and every automated sequence for that
   lead must stop within minutes. So this runs often and errs toward
   stopping — a sequence halted by a bounce notification is a small cost;
   one that keeps chasing someone who already answered is not.

   Matching, in order of reliability:
     1. The +token in the To/Delivered-To address (lead12@…) — exact.
     2. In-Reply-To / References against a message we sent — exact.
     3. The From address against a lead's email — good, but a shared
        family address or a forwarded reply can be ambiguous, so it only
        matches when exactly one lead has it.

   Requires the IMAP extension. Where it is missing — and it is missing on
   plenty of shared hosts — this degrades to a clear message in the panel
   rather than a fatal, and the owner can still see replies in their own
   mailbox.
   ============================================================ */

declare(strict_types=1);

final class Inbox
{
    public static function available(): bool
    {
        return function_exists('imap_open');
    }

    public static function configured(): bool
    {
        return self::host() !== '' && self::user() !== '' && self::pass() !== '';
    }

    public static function host(): string { return trim((string)Settings::get('imap_host', 'imap.hostinger.com')); }
    public static function port(): int    { return max(1, Settings::int('imap_port', 993)); }
    public static function user(): string { return trim((string)Settings::get('imap_user', '')); }
    public static function pass(): string { return Secrets::get('imap_pass', ''); }

    public static function whyNot(): string
    {
        if (!self::available())  return 'PHP on this server has no IMAP extension, so replies cannot be collected automatically. They still arrive in your mailbox as normal.';
        if (!self::configured()) return 'No mailbox is configured for reading replies (Settings → Conversations).';
        return '';
    }

    /**
     * Poll for new messages.
     * Returns a one-line summary for the cron heartbeat; never throws.
     */
    public static function poll(int $limit = 30): string
    {
        $why = self::whyNot();
        if ($why !== '') return 'skipped — ' . strtolower(substr($why, 0, 60));

        $mbox = null;
        try {
            $spec = sprintf('{%s:%d/imap/ssl}INBOX', self::host(), self::port());
            $mbox = @imap_open($spec, self::user(), self::pass(), 0, 1);
            if ($mbox === false) {
                $err = imap_last_error() ?: 'connection refused';
                wwt_log('inbox', 'connect failed', ['err' => $err]);
                return 'could not connect: ' . cut((string)$err, 90);
            }

            /* Unseen, above the watermark, oldest first, never twice.
               The watermark is the highest UID ever examined. Everything
               above it is new; everything at or below it was looked at once,
               matched or not, and unmatched mail is left unread for the
               humans. Because examined UIDs never come back, order cannot
               starve anything — a backlog bigger than $limit is simply
               worked through in ascending order over the next few ticks,
               and the watermark advances only past what was actually read.
               (The first cut read the oldest thirty with no watermark, which
               would have re-read the same thirty forever; the second read
               newest-first with a watermark at the bottom of the batch,
               which re-read the batch on the next tick. This one is
               verified against the live mailbox: N, then the remainder,
               then zero.) */
            $since = Settings::int('imap_last_uid', 0);
            $ids = array_map('intval', @imap_search($mbox, 'UNSEEN', SE_UID) ?: []);
            $ids = array_values(array_filter($ids, static fn(int $u) => $u > $since));
            sort($ids);
            $batch = array_slice($ids, 0, $limit);

            $matched = 0; $stopped = 0; $ignored = 0; $high = $since;
            foreach ($batch as $uid) {
                $r = self::ingest($mbox, $uid);
                if ($r === 'matched') $matched++;
                elseif ($r === 'stopped') { $matched++; $stopped++; }
                else $ignored++;
                if ($uid > $high) $high = $uid;
            }
            if ($high > $since) Settings::set('imap_last_uid', (string)$high);
            return sprintf('%d new · %d matched to a lead, %d sequences stopped, %d unmatched%s',
                count($batch), $matched, $stopped, $ignored,
                count($ids) > $limit ? ', ' . (count($ids) - $limit) . ' more next tick' : '');

        } catch (Throwable $t) {
            wwt_log('inbox', 'poll crashed', ['err' => $t->getMessage()]);
            return 'error: ' . cut($t->getMessage(), 90);
        } finally {
            if ($mbox) @imap_close($mbox);
        }
    }

    /** Read one message, match it, record it, and stop the sequence. */
    private static function ingest($mbox, int $uid): string
    {
        $headerRaw = (string)@imap_fetchheader($mbox, $uid, FT_UID);
        $h = @imap_rfc822_parse_headers($headerRaw);
        if (!$h) return 'ignored';

        $from    = self::addr($h->from ?? []);
        $subject = self::decode((string)($h->subject ?? ''));
        $msgId   = trim((string)($h->message_id ?? ''));
        $inReply = trim((string)($h->in_reply_to ?? ''));
        $refs    = trim((string)($h->references ?? ''));

        /* Do not treat our own automated mail as a customer reply, and do
           not let a vacation responder or a bounce look like engagement. */
        if (self::isAutomated($headerRaw, $subject)) {
            @imap_setflag_full($mbox, (string)$uid, '\\Seen', ST_UID);
            return 'ignored';
        }

        $leadId = self::matchLead($headerRaw, $from, $inReply . ' ' . $refs);
        if ($leadId === 0) return 'ignored';

        /* Already recorded? IMAP UIDs are stable per mailbox, but a
           Message-ID is the safer key across reconnects. */
        if ($msgId !== '' && DB::val('SELECT 1 FROM wwt_messages WHERE provider_id = ? LIMIT 1', [$msgId]) !== null) {
            @imap_setflag_full($mbox, (string)$uid, '\\Seen', ST_UID);
            return 'ignored';
        }

        $body = self::body($mbox, $uid);

        DB::insert(
            "INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body,
             provider_id, in_reply_to, status)
             VALUES (?, UTC_TIMESTAMP(), 'in', 'email', ?, ?, ?, ?, 'received')",
            [$leadId, cut($subject, 255), $body, cut($msgId, 190), cut($inReply, 190)]);

        Timeline::add($leadId, 'reply_received', 'email', cut($subject, 200), 'them');
        @imap_setflag_full($mbox, (string)$uid, '\\Seen', ST_UID);

        /* Replying is worth 30 points, and it stops the sequence. */
        Score::apply($leadId);

        /* Unsubscribe by reply. People write "unsubscribe" or "stop" as a
           reply far more often than they click the link. */
        if (preg_match('/^\s*(unsubscribe|stop|remove me|opt out)\b/i', trim(strip_tags($body)))) {
            Funnel::optOut($leadId, 'replied "stop"');
            return 'stopped';
        }

        $why = Funnel::checkStop($leadId);
        return $why !== '' ? 'stopped' : 'matched';
    }

    /** Auto-replies and bounces are not engagement. */
    public static function isAutomated(string $headers, string $subject): bool
    {
        if (preg_match('/^(Auto-Submitted:(?!\s*no\b)|X-Autoreply:|X-Autorespond:|Precedence:\s*(bulk|auto_reply))/mi', $headers)) {
            return true;
        }
        if (preg_match('/^(out of office|automatic reply|auto:|undeliverable|delivery status notification|mail delivery)/i', trim($subject))) {
            return true;
        }
        /* A null return-path is how bounces are addressed. */
        if (preg_match('/^Return-Path:\s*<>\s*$/mi', $headers)) return true;
        return false;
    }

    /**
     * Which lead is this from?
     * Ordered most reliable first; the address match only counts when it
     * is unambiguous.
     */
    public static function matchLead(string $headers, string $from, string $references): int
    {
        /* 1 — the +token we put in the Reply-To. */
        if (preg_match('/\+lead(\d+)@/i', $headers, $m)) {
            $id = (int)$m[1];
            if (DB::val('SELECT 1 FROM wwt_leads WHERE id = ?', [$id]) !== null) return $id;
        }

        /* 2 — threading headers against something we sent. */
        if (preg_match_all('/<([^<>]+)>/', $references, $m)) {
            foreach ($m[1] as $ref) {
                $id = DB::val('SELECT lead_id FROM wwt_messages WHERE provider_id = ? LIMIT 1', ['<' . $ref . '>']);
                if ($id === null) $id = DB::val('SELECT lead_id FROM wwt_messages WHERE provider_id = ? LIMIT 1', [$ref]);
                if ($id !== null) return (int)$id;
            }
        }

        /* 3 — the sender's address, but only if exactly one lead has it.
           Two leads sharing an address is rare, and guessing between them
           would attach a reply to the wrong conversation. */
        if ($from !== '') {
            $ids = DB::all('SELECT id FROM wwt_leads WHERE email = ? ORDER BY id DESC LIMIT 2', [strtolower($from)]);
            if (count($ids) === 1) return (int)$ids[0]['id'];
            if (count($ids) > 1) {
                wwt_log('inbox', 'ambiguous sender', ['from' => $from]);
                return (int)$ids[0]['id'];   // newest: the live conversation
            }
        }
        return 0;
    }

    /** Prefer text/plain; fall back to a stripped HTML part. */
    private static function body($mbox, int $uid): string
    {
        $struct = @imap_fetchstructure($mbox, $uid, FT_UID);
        $text = '';

        if ($struct && !empty($struct->parts)) {
            foreach ($struct->parts as $i => $part) {
                $no = (string)($i + 1);
                if ((int)$part->type === 0 && strtoupper((string)($part->subtype ?? '')) === 'PLAIN') {
                    $text = self::decodePart((string)@imap_fetchbody($mbox, $uid, $no, FT_UID), (int)$part->encoding);
                    break;
                }
            }
            if ($text === '') {
                foreach ($struct->parts as $i => $part) {
                    if ((int)$part->type === 0 && strtoupper((string)($part->subtype ?? '')) === 'HTML') {
                        $html = self::decodePart((string)@imap_fetchbody($mbox, $uid, (string)($i + 1), FT_UID), (int)$part->encoding);
                        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        break;
                    }
                }
            }
        } else {
            $text = self::decodePart((string)@imap_body($mbox, $uid, FT_UID), (int)($struct->encoding ?? 0));
        }

        return cut(self::trimQuoted($text), 20000);
    }

    private static function decodePart(string $raw, int $encoding): string
    {
        if ($encoding === 3) return (string)base64_decode($raw, true);
        if ($encoding === 4) return quoted_printable_decode($raw);
        return $raw;
    }

    /**
     * Cut the quoted history off the bottom.
     * A thread view that repeats the entire conversation inside every
     * message is unreadable after three replies.
     */
    public static function trimQuoted(string $text): string
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*(On .{5,80} wrote:|-{2,}\s*Original Message|_{5,}|From:\s)/i', $line)) break;
            if (preg_match('/^\s*>/', $line) && count($out) > 2) break;
            $out[] = $line;
        }
        return trim(implode("\n", $out)) ?: trim($text);
    }

    private static function addr(array|object $list): string
    {
        $a = is_array($list) ? ($list[0] ?? null) : $list;
        if (!$a || empty($a->mailbox) || empty($a->host)) return '';
        return strtolower($a->mailbox . '@' . $a->host);
    }

    private static function decode(string $s): string
    {
        if ($s === '') return '';
        $parts = @imap_mime_header_decode($s);
        if (!$parts) return $s;
        $out = '';
        foreach ($parts as $p) {
            $cs = strtoupper((string)$p->charset);
            $out .= ($cs === 'DEFAULT' || $cs === 'UTF-8' || $cs === '')
                ? $p->text
                : (@mb_convert_encoding($p->text, 'UTF-8', $cs) ?: $p->text);
        }
        return $out;
    }
}
