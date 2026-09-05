<?php
/* ============================================================
   mailer.php — the only way this codebase sends email.

   SMTP through an authenticated mailbox, never PHP mail().
   mail() sends unauthenticated from the web user: it fails SPF/DKIM
   alignment, so it lands in spam or is dropped outright — and it
   returns true either way, which is the worst possible failure mode
   for a contact form.

   Every send returns a result instead of throwing, because a lead is
   already saved by the time we get here and a mail problem must never
   lose it. The failure is recorded against the lead and surfaced in
   the panel.
   ============================================================ */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class Mailer
{
    /** Seconds to wait on SMTP. A visitor is holding the connection open. */
    public const TIMEOUT = 12;

    private static bool $loaded = false;

    /**
     * Effective mailbox settings: what the owner saved in the panel, falling
     * back to config.php. config.php is what a fresh deploy has; the panel is
     * how the owner changes it later without touching a file.
     */
    public static function settings(): array
    {
        $fromDb = static function (string $k, string $fallback): string {
            $v = (string)Settings::get('smtp_' . $k, '');
            return $v !== '' ? $v : $fallback;
        };
        $pass = Secrets::get('smtp_pass', (string)cfg('smtp.pass', ''));
        return [
            'host'      => $fromDb('host',   (string)cfg('smtp.host', '')),
            'port'      => (int)$fromDb('port', (string)cfg('smtp.port', '465')),
            'secure'    => strtolower($fromDb('secure', (string)cfg('smtp.secure', 'ssl'))),
            'user'      => $fromDb('user',   (string)cfg('smtp.user', '')),
            'pass'      => $pass,
            'from_name' => $fromDb('from_name', (string)cfg('smtp.from_name', 'Wwwebtech')),
        ];
    }

    /** Where enquiries are sent. Panel first, then config.php. */
    public static function leadRecipient(): string
    {
        $v = (string)Settings::get('lead_email', '');
        return $v !== '' ? $v : (string)cfg('site.lead_email', 'contact@wwwebtech.in');
    }

    private static function load(): void
    {
        if (self::$loaded) return;
        $dir = WWT_PRIVATE . '/vendor/PHPMailer';
        require_once $dir . '/Exception.php';
        require_once $dir . '/PHPMailer.php';
        require_once $dir . '/SMTP.php';
        self::$loaded = true;
    }

    /* ── Sender identities (Connections card) ─────────────── */

    /**
     * Every mailbox the site may send from, each with its own login.
     * A provider will not let one mailbox send as another, so an identity
     * is a full SMTP account, not just a display name.
     *
     * @return list<array{id:string,label:string,name:string,email:string,host:string,port:int,secure:string,user:string,use:list<string>}>
     */
    public static function identities(): array
    {
        $list = Settings::json('mail_identities', []);
        $out = [];
        foreach ($list as $i) {
            if (!is_array($i) || (string)($i['id'] ?? '') === '') continue;
            $out[] = self::normaliseIdentity($i);
        }
        if (!$out) {
            /* Nothing saved yet: the legacy smtp_* keys ARE the default. */
            $s = self::settings();
            if ($s['user'] !== '') {
                $out[] = self::normaliseIdentity(['id' => 'default', 'label' => 'Company mailbox',
                    'name' => $s['from_name'], 'email' => $s['user'], 'host' => $s['host'], 'port' => $s['port'],
                    'secure' => $s['secure'], 'user' => $s['user'], 'use' => ['system', 'funnel', 'manual']]);
            }
        }
        return $out;
    }

    private static function normaliseIdentity(array $i): array
    {
        return [
            'id'     => preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$i['id'])) ?: 'default',
            'label'  => cut((string)($i['label'] ?? ''), 60),
            'name'   => cut((string)($i['name'] ?? ''), 80),
            'email'  => strtolower(trim((string)($i['email'] ?? ''))),
            'host'   => trim((string)($i['host'] ?? '')),
            'port'   => (int)($i['port'] ?? 465),
            'secure' => in_array((string)($i['secure'] ?? ''), ['ssl', 'tls', 'none'], true) ? (string)$i['secure'] : 'ssl',
            'user'   => trim((string)($i['user'] ?? $i['email'] ?? '')),
            'use'    => array_values(array_intersect((array)($i['use'] ?? []), ['system', 'funnel', 'manual'])),
        ];
    }

    /** By id, else the first identity assigned to that use, else the default. */
    public static function identity(string $idOrUse = 'default'): ?array
    {
        $all = self::identities();
        foreach ($all as $i) if ($i['id'] === $idOrUse) return $i;
        foreach ($all as $i) if (in_array($idOrUse, $i['use'], true)) return $i;
        foreach ($all as $i) if ($i['id'] === 'default') return $i;
        return $all[0] ?? null;
    }

    /** The password for an identity. The default one is the legacy key. */
    public static function identityPass(string $id): string
    {
        $v = Secrets::get('mail_identity_' . $id . '_pass', '');
        if ($v === '' && $id === 'default') $v = Secrets::get('smtp_pass', (string)cfg('smtp.pass', ''));
        return $v;
    }

    /**
     * Save the list. The default identity is written through to the
     * legacy smtp_* keys as well, so nothing else in the codebase changes.
     */
    public static function saveIdentities(array $list): void
    {
        $norm = array_map([self::class, 'normaliseIdentity'], array_values($list));
        Settings::set('mail_identities', json_encode($norm, JSON_UNESCAPED_UNICODE) ?: '[]');
        foreach ($norm as $i) {
            if ($i['id'] !== 'default') continue;
            Settings::set('smtp_host', $i['host']);
            Settings::set('smtp_port', (string)$i['port']);
            Settings::set('smtp_secure', $i['secure']);
            Settings::set('smtp_user', $i['user']);
            Settings::set('smtp_from_name', $i['name']);
        }
    }

    /** True when there is enough configuration to attempt a send. */
    public static function configured(): bool
    {
        $s = self::settings();
        return $s['host'] !== '' && $s['user'] !== '' && $s['pass'] !== '';
    }

    /**
     * @param array{to:string,to_name?:string,subject:string,text:string,
     *              html?:string,reply_to?:string,reply_to_name?:string} $m
     * @return array{ok:bool,error:string}
     */
    public static function send(array $m, bool $debug = false): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'error' => 'SMTP is not configured yet (see DEPLOY.md, SETUP-2).'];
        }

        $to = trim((string)($m['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Recipient address is not valid.'];
        }

        self::load();
        $log = '';
        $cfg = self::settings();

        /* An identity carries its own SMTP login. 'default' is the legacy
           smtp_* configuration, so callers that never heard of identities
           behave exactly as before. */
        $identity = !empty($m['identity_override']) && is_array($m['identity_override'])
            ? self::normaliseIdentity($m['identity_override']) + ['id' => '_override']
            : self::identity((string)($m['identity'] ?? 'default'));
        if (!empty($m['identity_override'])) $identity['id'] = '_override';
        if ($identity && $identity['id'] !== 'default' && $identity['host'] !== '') {
            $cfg = ['host' => $identity['host'], 'port' => $identity['port'], 'secure' => $identity['secure'],
                    'user' => $identity['user'], 'pass' => $identity['id'] === '_override' ? '' : self::identityPass($identity['id']),
                    'from_name' => $identity['name'] ?: $cfg['from_name']];
        }
        if (!empty($m['password_override'])) $cfg['pass'] = (string)$m['password_override'];

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host        = $cfg['host'];
            $mail->Port        = $cfg['port'];
            $mail->SMTPAuth    = true;
            $mail->Username    = $cfg['user'];
            $mail->Password    = $cfg['pass'];
            $mail->Timeout     = self::TIMEOUT;
            $mail->CharSet     = PHPMailer::CHARSET_UTF8;
            $mail->Encoding    = PHPMailer::ENCODING_BASE64;   // safe for UTF-8 bodies
            $mail->XMailer     = ' ';                          // don't advertise the stack

            $secure = $cfg['secure'];
            if ($secure === 'tls')      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            elseif ($secure === 'none') { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }
            else                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

            if ($debug) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str) use (&$log) { $log .= rtrim((string)$str) . "\n"; };
            }

            /* The envelope sender must be the authenticated mailbox or the
               host rejects it; the visitor goes in Reply-To instead. That is
               also what keeps SPF and DKIM aligned. */
            /* The envelope sender must remain the authenticated mailbox or the
               host rejects it, and SPF/DKIM alignment depends on it. A named
               sender changes the display name and the Reply-To, never the
               envelope. */
            $fromEmail = trim((string)($m['from_email'] ?? '')) ?: $cfg['user'];
            $fromName  = trim((string)($m['from_name'] ?? '')) ?: $cfg['from_name'];
            if (strcasecmp($fromEmail, $cfg['user']) !== 0) {
                /* A different address on the same domain is safe; a different
                   domain would fail alignment, so it is not used. */
                $sameDomain = substr(strrchr($fromEmail, '@') ?: '', 1)
                           === substr(strrchr($cfg['user'], '@') ?: '', 1);
                if (!$sameDomain) $fromEmail = $cfg['user'];
            }
            $mail->setFrom($fromEmail, $fromName);

            /* One-click unsubscribe headers (§9). Gmail and Outlook surface
               these as a native button, which is far better for the sending
               reputation than someone marking it spam. */
            if (!empty($m['unsubscribe'])) {
                $mail->addCustomHeader('List-Unsubscribe',
                    '<' . str_replace(['<', '>', "\r", "\n"], '', (string)$m['unsubscribe']) . '>');
                $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }
            $mail->addAddress($to, cut((string)($m['to_name'] ?? ''), 80));

            $replyTo = trim((string)($m['reply_to'] ?? ''));
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, cut((string)($m['reply_to_name'] ?? ''), 80));
            }

            $mail->Subject = self::headerSafe((string)($m['subject'] ?? '(no subject)'));

            if (!empty($m['html'])) {
                $mail->isHTML(true);
                $mail->Body    = (string)$m['html'];
                $mail->AltBody = (string)($m['text'] ?? strip_tags((string)$m['html']));
            } else {
                $mail->isHTML(false);
                $mail->Body = (string)($m['text'] ?? '');
            }

            $mail->send();
            Connections::touch('mail_send');
            return ['ok' => true, 'error' => '', 'log' => $log];

        } catch (PHPMailerException $ex) {
            wwt_log('mail', 'send failed', ['to' => $to, 'err' => $ex->getMessage()]);
            return ['ok' => false, 'error' => cut($ex->getMessage(), 240), 'log' => $log];
        } catch (Throwable $t) {
            wwt_log('mail', 'send crashed', ['to' => $to, 'err' => $t->getMessage()]);
            return ['ok' => false, 'error' => cut($t->getMessage(), 240), 'log' => $log];
        }
    }

    /**
     * Send AS the named human (§5.3).
     *
     * Funnel mail comes from a person with a replyable address, not
     * no-reply@. Automated mail signed by a real name gets answered;
     * no-reply gets ignored, and the whole funnel depends on the reply.
     *
     * The reply address carries a +token so an inbound reply can be matched
     * back to the lead without relying on the subject line surviving.
     */
    public static function sendAs(array $m): array
    {
        $sender = trim((string)Settings::get('funnel_sender_email', ''));
        $name   = trim((string)Settings::get('funnel_sender_name', ''));
        if ($sender === '') {
            /* No named identity configured yet: fall back to the ordinary
               mailbox rather than not sending. */
            return self::send($m + ['reply_to' => self::leadRecipient()]);
        }

        $leadId = (int)($m['lead_id'] ?? 0);
        $reply  = $leadId > 0 ? self::plusAddress($sender, 'lead' . $leadId) : $sender;

        return self::send([
            'identity'      => 'funnel',
            'to'            => (string)$m['to'],
            'to_name'       => (string)($m['to_name'] ?? ''),
            'subject'       => (string)$m['subject'],
            'text'          => (string)($m['text'] ?? ''),
            'html'          => (string)($m['html'] ?? ''),
            'reply_to'      => $reply,
            'reply_to_name' => $name,
            'from_email'    => $sender,
            'from_name'     => $name,
            'unsubscribe'   => (string)($m['unsubscribe'] ?? ''),
        ]);
    }

    /** user+tag@domain — the standard way to route a reply back. */
    public static function plusAddress(string $address, string $tag): string
    {
        $at = strrpos($address, '@');
        if ($at === false) return $address;
        return substr($address, 0, $at) . '+' . preg_replace('/[^a-z0-9]/i', '', $tag)
             . substr($address, $at);
    }

    /** Strip CR/LF so nothing reaching a mail header can inject one. */
    public static function headerSafe(string $v): string
    {
        return cut(trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''), 200);
    }

    /* ── The Connections card: DNS, probe, test ───────────── */

    /** DKIM selectors worth probing, by provider preset plus the usual suspects. */
    private const DKIM_SELECTORS = ['hostingermail-a', 'hostingermail-b', 'google', 'default', 'mail',
                                    'zmail', 'zoho', 'selector1', 'selector2', 'k1', 's1', 'dkim'];

    /**
     * SPF, DKIM and DMARC for the sending domain, with the record to add
     * for anything missing. Cached for a day; the health job refreshes it.
     */
    public static function dnsHealth(bool $refresh = false): array
    {
        $cached = Settings::json('mail_dns_health', []);
        if (!$refresh && $cached && strtotime((string)($cached['checked_at'] ?? '') . ' UTC') > time() - 86400) return $cached;

        $id = self::identity('default');
        $domain = strtolower(substr(strrchr($id['email'] ?? self::settings()['user'], '@') ?: '', 1));
        $out = ['domain' => $domain, 'checked_at' => gmdate('Y-m-d H:i:s'), 'available' => function_exists('dns_get_record')];
        if ($domain === '' || !$out['available']) {
            Settings::set('mail_dns_health', json_encode($out) ?: '{}');
            return $out;
        }
        $txt = static function (string $host): array {
            $r = @dns_get_record($host, DNS_TXT) ?: [];
            return array_values(array_filter(array_map(static fn($x) => (string)($x['txt'] ?? ''), $r)));
        };
        $spf = array_values(array_filter($txt($domain), static fn($t) => stripos($t, 'v=spf1') === 0));
        $out['spf'] = ['ok' => (bool)$spf, 'record' => $spf[0] ?? '',
                       'fix' => $spf ? '' : 'Add a TXT record on ' . $domain . ' with the value your provider gives — for Hostinger: v=spf1 include:_spf.mail.hostinger.com ~all'];

        $found = '';
        foreach (self::DKIM_SELECTORS as $sel) {
            $rec = $txt($sel . '._domainkey.' . $domain);
            if ($rec && (stripos(implode('', $rec), 'v=dkim1') !== false || stripos(implode('', $rec), 'p=') !== false)) { $found = $sel; break; }
            /* Some providers publish DKIM as a CNAME to their own key. */
            if (!$rec && @dns_get_record($sel . '._domainkey.' . $domain, DNS_CNAME)) { $found = $sel; break; }
        }
        $out['dkim'] = ['ok' => $found !== '', 'selector' => $found,
                        'fix' => $found !== '' ? '' : 'Turn on DKIM for the domain in your email provider (Hostinger: hPanel → Emails → the domain → DKIM) and add the record it shows.'];

        $dmarc = array_values(array_filter($txt('_dmarc.' . $domain), static fn($t) => stripos($t, 'v=dmarc1') === 0));
        $policy = '';
        if ($dmarc && preg_match('/\bp=([a-z]+)/i', $dmarc[0], $m)) $policy = strtolower($m[1]);
        $out['dmarc'] = ['ok' => (bool)$dmarc, 'policy' => $policy, 'record' => $dmarc[0] ?? '',
                         'fix' => $dmarc ? ($policy === 'none' ? 'Present, but p=none only monitors. Once SPF and DKIM have been green for a month, change it to p=quarantine.' : '')
                                         : 'Add a TXT record on _dmarc.' . $domain . ' with the value: v=DMARC1; p=none; rua=mailto:' . ($id['email'] ?? 'postmaster@' . $domain)];
        Settings::set('mail_dns_health', json_encode($out, JSON_UNESCAPED_SLASHES) ?: '{}');
        return $out;
    }

    /** Connect and sign in without sending anything. */
    public static function smtpProbe(string|array $identityId = 'default', string $passOverride = ''): array
    {
        $i = is_array($identityId) ? self::normaliseIdentity($identityId) : self::identity($identityId);
        if (!$i) return ['ok' => false, 'error' => 'No such identity.'];
        if ($i['id'] === 'default' && !is_array($identityId) && !self::configured()) return ['ok' => false, 'error' => 'No mailbox configured.'];
        if (is_array($identityId) && $i['id'] === 'default') $i['id'] = '_pending_default';   // read the array, not the store
        $host = $i['id'] === 'default' ? self::settings()['host'] : $i['host'];
        $port = $i['id'] === 'default' ? self::settings()['port'] : $i['port'];
        $sec  = $i['id'] === 'default' ? self::settings()['secure'] : $i['secure'];
        $user = $i['id'] === 'default' ? self::settings()['user'] : $i['user'];
        $pass = $passOverride !== '' ? $passOverride : self::identityPass($i['id'] === '_pending_default' ? 'default' : $i['id']);
        self::load();
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host; $mail->Port = $port; $mail->SMTPAuth = true;
            $mail->Username = $user; $mail->Password = $pass; $mail->Timeout = self::TIMEOUT;
            if ($sec === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            elseif ($sec === 'none') { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }
            else $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $ok = $mail->smtpConnect();
            $mail->smtpClose();
            return ['ok' => (bool)$ok, 'error' => $ok ? '' : 'The server refused the login.'];
        } catch (Throwable $t) {
            return ['ok' => false, 'error' => cut($t->getMessage(), 240)];
        }
    }

    /**
     * The card's Send-me-a-test: connect, sign in, deliver, and — when the
     * recipient is the mailbox the panel reads — fetch the message back and
     * read its Authentication-Results for SPF and DKIM.
     */
    public static function testIdentity(string|array $identityId, string $to, string $passOverride = ''): array
    {
        $checks = [];
        $probe = self::smtpProbe($identityId, $passOverride);
        $i = is_array($identityId) ? self::normaliseIdentity($identityId) : (self::identity($identityId) ?? []);
        $checks[] = ['Connected and signed in to ' . ($i['host'] ?? self::settings()['host']), $probe['ok'], $probe['ok'] ? 'as ' . ($i['user'] ?? '') : $probe['error']];
        if (!$probe['ok']) return ['ok' => false, 'checks' => $checks, 'error' => $probe['error']];

        $marker = 'wwt-test-' . bin2hex(random_bytes(4));
        $r = self::send([
            'identity' => is_array($identityId) ? 'default' : $identityId, 'identity_override' => is_array($identityId) ? $i : null,
            'password_override' => $passOverride,
            'to' => $to, 'subject' => 'Test from the Wwwebtech panel [' . $marker . ']',
            'text' => "This is a test message from the admin panel.\n\nIf you are reading it, this mailbox can send.\n\n"
                    . 'Sent ' . local_time(gmdate('Y-m-d H:i:s')) . " IST · " . $marker . "\n",
        ]);
        $checks[] = ['Test email accepted for delivery to ' . $to, $r['ok'], $r['ok'] ? '' : $r['error']];
        if (!$r['ok']) return ['ok' => false, 'checks' => $checks, 'error' => $r['error']];

        if (Inbox::configured() && strcasecmp($to, Inbox::user()) === 0) {
            $auth = Inbox::authResultsFor($marker, 12);
            if ($auth['found']) {
                $checks[] = ['SPF passed on arrival', $auth['spf'] === true, $auth['spf'] === null ? 'not reported' : ($auth['spf'] ? '' : 'the receiving server did not accept SPF — check the DNS strip')];
                $checks[] = ['DKIM passed on arrival', $auth['dkim'] === true, $auth['dkim'] === null ? 'not reported' : ($auth['dkim'] ? '' : 'the message was not signed — turn DKIM on at the provider')];
            } else {
                $checks[] = ['Message read back from the mailbox', false, 'it had not arrived within 12 seconds — check the mailbox by hand'];
            }
        }
        $ok = !in_array(false, array_map(static fn($c) => $c[1], array_slice($checks, 0, 2)), true);
        return ['ok' => $ok, 'checks' => $checks, 'error' => ''];
    }
}
