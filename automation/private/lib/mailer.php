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
}
