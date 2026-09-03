<?php
/* ============================================================
   api/unsubscribe.php — one click, and it is done.

   Two things matter here and neither is negotiable:

   · It must work on the FIRST click, with no confirmation step and no
     login. A working unsubscribe is what stops someone pressing the spam
     button instead, which costs the sending domain far more.

   · It must accept POST as well as GET. Gmail and Outlook surface a
     native "Unsubscribe" button driven by List-Unsubscribe-Post, and
     they send a POST with no user interaction at all.

   The link is signed per lead, so it cannot be used to silence someone
   else by guessing an id.
   ============================================================ */

declare(strict_types=1);

define('WWT_SOFT_BOOT', true);
require dirname(__DIR__) . '/_wwt.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$leadId = (int)($_GET['l'] ?? $_POST['l'] ?? 0);
$token  = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$ok = false;
$why = 'That unsubscribe link is not valid. Please reply to any of our emails and we will remove you.';

if (wwt_boot(__DIR__, true) && $leadId > 0 && Funnel::checkUnsubToken($leadId, $token)) {
    try {
        $l = DB::one('SELECT id, name, email, do_not_contact FROM wwt_leads WHERE id = ?', [$leadId]);
        if ($l) {
            if ((int)$l['do_not_contact'] !== 1) {
                Funnel::optOut($leadId, $isPost ? 'one-click unsubscribe' : 'unsubscribe link');
            }
            $ok = true;
        }
    } catch (Throwable $t) {
        wwt_log('unsubscribe', 'failed', ['lead' => $leadId, 'err' => $t->getMessage()]);
        $why = 'Something went wrong. Please reply to any of our emails and we will remove you.';
    }
}

/* A mail client's one-click POST expects a 200 and reads nothing. */
if ($isPost) {
    http_response_code($ok ? 200 : 400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $ok ? "Unsubscribed\n" : "Invalid\n";
    exit;
}

http_response_code($ok ? 200 : 400);
header('Content-Type: text/html; charset=utf-8');
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!DOCTYPE html>
<html lang="en-IN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $ok ? 'Unsubscribed' : 'Link not valid' ?> | Wwwebtech</title>
<style>
body{background:#FAF8F4;color:#4E5450;font:17px/1.6 -apple-system,'Segoe UI',Roboto,sans-serif;
     margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem}
main{max-width:34rem}
h1{font:500 clamp(1.8rem,5vw,2.6rem)/1.1 Georgia,serif;color:#131614;margin:0 0 1rem;letter-spacing:-.02em}
a.btn{display:inline-block;margin-top:1.8rem;background:#E07000;color:#131614;font-weight:600;
      text-decoration:none;padding:.8rem 1.4rem;border-radius:2px}
p.small{font-size:14px;color:#686D69;margin-top:1.2rem}
</style></head>
<body><main>
<?php if ($ok): ?>
  <h1>Done — you are unsubscribed.</h1>
  <p>You will not get any more emails from us about your enquiry. Nothing else
     is needed from you.</p>
  <p class="small">If you unsubscribed by mistake, or you do want to hear from
     us later, just email <a href="mailto:contact@wwwebtech.in">contact@wwwebtech.in</a>
     and we will pick it back up.</p>
<?php else: ?>
  <h1>That link is not valid.</h1>
  <p><?= $esc($why) ?></p>
<?php endif; ?>
<a class="btn" href="/">Back to the site</a>
</main></body></html>
