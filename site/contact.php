<?php
/* ============================================================
   contact.php — the contact form handler.

   The site is static; this is the one server-side file, and it exists
   because wwwebtech.in already runs on PHP hosting. It does what the
   old Laravel ContactController did: emails you the enquiry, and sends
   the person an acknowledgement.

   If you ever move to a host with no PHP (Netlify, Cloudflare Pages),
   delete this file and point FORM_ENDPOINT in assets/js/main.js at a
   Formspree-style URL instead. Nothing else changes.

   Returns JSON, because assets/js/main.js posts here with fetch().
   ============================================================ */

declare(strict_types=1);

const TO_ADDRESS   = 'contact@wwwebtech.in';
const SITE_NAME    = 'Wwwebtech';
const SITE_URL     = 'https://wwwebtech.in';
const MAX_PER_HOUR = 8;          // per IP, a crude but effective brake

/* ------------------------------------------------------------------
   assets/js/main.js posts here with fetch() and wants JSON. A browser
   with JavaScript switched off posts the form normally and needs a
   readable page back — so answer in whichever the caller asked for.   */
$wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . ($wantsJson ? 'application/json' : 'text/html') . '; charset=utf-8');

function page(string $title, string $body, string $link): string {
    $t = htmlspecialchars($title, ENT_QUOTES); $b = htmlspecialchars($body, ENT_QUOTES);
    return <<<HTML
    <!DOCTYPE html><html lang="en-IN"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex"><title>$t | Wwwebtech</title><style>
    body{background:#FAF8F4;color:#4E5450;font:17px/1.6 Archivo,-apple-system,'Segoe UI',sans-serif;
         margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem}
    main{max-width:34rem}h1{font:500 clamp(2rem,5vw,3rem)/1.05 Fraunces,Georgia,serif;
         color:#131614;letter-spacing:-.02em;margin:0 0 1rem}
    a{display:inline-block;margin-top:2rem;background:#E07000;color:#131614;font-weight:600;
      text-decoration:none;padding:.8rem 1.4rem;border-radius:2px}</style></head>
    <body><main><h1>$t</h1><p>$b</p><a href="$link">Back to the site</a></main></body></html>
    HTML;
}

function fail(int $code, string $msg): never {
    global $wantsJson;
    http_response_code($code);
    echo $wantsJson ? json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE)
                    : page('That didn’t send', $msg, '/contact/');
    exit;
}
function done(string $msg): never {
    global $wantsJson;
    echo $wantsJson ? json_encode(['ok' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE)
                    : page('Message sent', $msg, '/');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail(405, 'Method not allowed.');

/* --- Honeypot. A bot filled the hidden field. Look successful and stop. */
if (trim((string)($_POST['company'] ?? '')) !== '') done('Thanks — we’ll be in touch.');

/* --- Crude per-IP rate limit ---------------------------------------- */
$ip   = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$file = sys_get_temp_dir() . '/wwwebtech_rl_' . sha1($ip);
$now  = time();
$hits = [];
if (is_readable($file)) {
    $hits = array_filter(
        (array)json_decode((string)file_get_contents($file), true),
        static fn($t) => is_int($t) && $t > $now - 3600
    );
}
if (count($hits) >= MAX_PER_HOUR) fail(429, 'Too many messages. Please email ' . TO_ADDRESS . '.');
$hits[] = $now;
@file_put_contents($file, json_encode(array_values($hits)), LOCK_EX);

/* --- Read and validate ---------------------------------------------- */
/* Truncate to $max CHARACTERS, not bytes, without assuming mbstring is
   installed. If it is missing and we called mb_substr(), this file would
   die with a blank 500 and the enquiry would vanish silently. */
function cut(string $v, int $max): string {
    if (function_exists('mb_substr')) return mb_substr($v, 0, $max);
    return preg_match('/^.{0,' . $max . '}/us', $v, $m) ? $m[0] : substr($v, 0, $max);
}

$clean = static function (string $key, int $max): string {
    $v = (string)($_POST[$key] ?? '');
    $v = str_replace(["\0"], '', $v);
    return cut(trim($v), $max);
};

$name    = $clean('name', 100);
$email   = $clean('email', 150);
$phone   = $clean('phone', 30);
$budget  = $clean('budget', 40);
$message = $clean('message', 2000);
$page    = $clean('_page', 120);

/* Checkbox group: the browser sends need[] or repeated need fields. */
$rawNeed = $_POST['need'] ?? [];
$need    = implode(', ', array_map(
    static fn($v) => cut(preg_replace('/[^\p{L}\p{N} \-\/]/u', '', (string)$v) ?? '', 30),
    is_array($rawNeed) ? array_slice($rawNeed, 0, 8) : [$rawNeed]
));

if ($name === '' || $message === '')                       fail(422, 'Please fill in your name and message.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))            fail(422, 'That email doesn’t look right.');

/* Header injection: a newline in a value that reaches a mail header would
   let someone add their own headers. Values used in headers are rejected
   outright if they contain one. */
foreach ([$name, $email] as $headerValue) {
    if (preg_match('/[\r\n]/', $headerValue)) fail(422, 'Invalid characters in name or email.');
}

/* --- Compose --------------------------------------------------------- */
$lines = [
    "Name:    $name",
    "Email:   $email",
    $phone   !== '' ? "Phone:   $phone"   : null,
    $need    !== '' ? "Needs:   $need"    : null,
    $budget  !== '' ? "Budget:  $budget"  : null,
    '',
    'Message:',
    $message,
    '',
    '---',
    $page !== '' ? "Sent from: " . SITE_URL . $page : null,
    'IP: ' . $ip,
    'Time: ' . gmdate('Y-m-d H:i:s') . ' UTC',
];
$body = implode("\n", array_filter($lines, static fn($l) => $l !== null));

$fromDomain = (string)($_SERVER['HTTP_HOST'] ?? 'wwwebtech.in');
$from       = 'website@' . preg_replace('/^www\./', '', $fromDomain);

$headers = [
    'From: ' . SITE_NAME . ' website <' . $from . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = @mail(TO_ADDRESS, 'New website enquiry | wwwebtech.in', $body, implode("\r\n", $headers));
if (!$sent) fail(500, 'Could not send. Please email ' . TO_ADDRESS . ' directly.');

/* --- Acknowledgement to the sender ----------------------------------- */
/* Note: this says ONE business day, matching the promise made all over the
   site. The old Laravel autoresponder said "1-2" — if you would rather
   promise two, change it here AND on the site so they agree. */
$ack = <<<TXT
Hello {$name},

Thanks for contacting Wwwebtech.

We've got your message and we'll come back to you within 1 business day.
If it's urgent, just reply to this email and it reaches us directly.

Regards,
Wwwebtech
East Delhi, Delhi, India
https://wwwebtech.in
TXT;

@mail($email, 'We’ve received your enquiry — Wwwebtech', $ack, implode("\r\n", [
    'From: ' . SITE_NAME . ' <' . $from . '>',
    'Reply-To: ' . TO_ADDRESS,
    'Content-Type: text/plain; charset=UTF-8',
]));

done('Got it. A real person replies within 1 business day.');
