<?php
/* ============================================================
   audit.php — the free website audit, public template.

   Same rules as the landing pages: inlined CSS, no render-blocking
   request, H1 as the LCP element, no image above the fold, and a form
   that is a plain form before any JavaScript touches it.

   The one thing this page must not do is exaggerate. The report is the
   product; if it cries wolf, the first phone call ends badly. So a good
   site is shown a green score and told to keep its money.

   Expects: $view (form|pending|report|failed), $errors, $old,
            $audit, $report, $base.
   ============================================================ */

declare(strict_types=1);

$SITE_URL = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
$PHONE_E  = '+918595250209';
$WA       = 'https://wa.me/918595250209';
$canonical = $SITE_URL . $base;

$readCss = static function (array $c): string {
    foreach ($c as $f) if ($f !== '' && is_file($f)) return (string)file_get_contents($f);
    return '';
};
$minify = static function (string $c): string {
    $c = preg_replace('#/\*[\s\S]*?\*/#', '', $c) ?? $c;
    $c = preg_replace('/\s+/', ' ', $c) ?? $c;
    return str_replace([' {', '{ ', ' }', '} ', '; ', ': ', ', '], ['{', '{', '}', '}', ';', ':', ','], $c);
};

$webroot = rtrim((string)cfg('site.webroot', ''), '/');
$repo    = dirname(__DIR__, 2);

$fontCss = $minify($readCss([$webroot . '/assets/css/fonts.css',
                             dirname($repo) . '/site/assets/css/fonts.css']));
$fontCss = str_replace("url('../fonts/", "url('/assets/fonts/", $fontCss);
$css     = $minify($readCss([$repo . '/webroot/lp/assets/lp.css',
                             $webroot . '/lp/assets/lp.css']));

$titles = [
    'form'    => 'Free website audit — see what is costing you enquiries | Wwwebtech',
    'pending' => 'Checking your site… | Wwwebtech',
    'report'  => 'Your website audit | Wwwebtech',
    'failed'  => 'We could not reach that site | Wwwebtech',
];
$title = $titles[$view] ?? $titles['form'];
$desc  = 'A free, honest audit of your website: speed, mobile, search visibility and how easy '
       . 'you are to contact. Seventeen checks run against your live page — no sales pitch, no invented scores.';

/* The score ring colour and the wording both come from one place, so the
   number and the sentence can never disagree. */
$band = static function (int $s): array {
    if ($s >= 90) return ['var(--ok)',        'Good shape'];
    if ($s >= 80) return ['var(--ok)',        'Sound'];
    if ($s >= 55) return ['var(--marigold-ink)', 'Worth some work'];
    return ['var(--bad)', 'Losing you enquiries'];
};
$stateWord = ['ok' => 'Good', 'warn' => 'Worth fixing', 'bad' => 'Fix this', 'info' => 'Not measured'];
?><!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($view === 'form'): ?>
<meta name="robots" content="index, follow, max-image-preview:large">
<?php else: ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($SITE_URL) ?>/og/home.png">
<link rel="preload" href="/assets/fonts/fraunces.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/archivo.woff2" as="font" type="font/woff2" crossorigin>
<?php if ($view === 'pending'): ?>
<?php /* The report is not ready. Refresh rather than poll with script, so it
         works with JavaScript off — which is also the honest way to wait. */ ?>
<meta http-equiv="refresh" content="12">
<?php endif; ?>
<style><?= $fontCss ?></style>
<style><?= $css ?></style>
<style>
.aud{max-width:46rem;margin-inline:auto}
.aud__score{display:flex;align-items:baseline;gap:.65rem;margin:var(--s5) 0 var(--s3)}
.aud__num{font:600 4rem/1 var(--display);letter-spacing:-.02em}
.aud__of{font:400 1.05rem/1 var(--font);color:var(--ink-3)}
.aud__pill{font:600 .78rem/1 var(--font);letter-spacing:.04em;text-transform:uppercase;
  border:1px solid currentColor;border-radius:2px;padding:.35rem .5rem;margin-left:auto}
.aud__meter{height:6px;border-radius:3px;background:var(--rule);overflow:hidden;margin-bottom:var(--s5)}
.aud__meter i{display:block;height:100%;border-radius:3px}
.check{border-top:1px solid var(--rule);padding:var(--s4) 0}
.check__hd{display:flex;gap:.6rem;align-items:baseline;flex-wrap:wrap}
.check__name{font:600 1.02rem/1.35 var(--font);color:var(--ink);margin:0}
.check__tag{font:600 .7rem/1 var(--font);letter-spacing:.05em;text-transform:uppercase;
  border:1px solid currentColor;border-radius:2px;padding:.25rem .4rem;white-space:nowrap}
.check--ok .check__tag{color:var(--ok)}
.check--warn .check__tag{color:var(--marigold-ink)}
.check--bad .check__tag{color:var(--bad)}
.check--info .check__tag{color:var(--ink-3)}
.check__find{margin:.45rem 0 0;color:var(--ink-2);font-size:.98rem;line-height:1.6}
.check__fix{margin:.55rem 0 0;padding-left:.75rem;border-left:2px solid var(--rule);
  color:var(--ink);font-size:.98rem;line-height:1.6}
.aud__wait{display:flex;gap:.7rem;align-items:center;color:var(--ink-2)}
.aud__dots{width:.6rem;height:.6rem;border-radius:50%;background:var(--marigold);flex:none;
  animation:aud-pulse 1.4s ease-in-out infinite}
@keyframes aud-pulse{0%,100%{opacity:.25}50%{opacity:1}}
@media (prefers-reduced-motion:reduce){.aud__dots{animation:none;opacity:.8}}
.aud__meta{color:var(--ink-3);font-size:.9rem;margin-top:var(--s5)}
</style>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

<header class="lp-head">
  <div class="lp-head__in shell">
    <a class="lp-head__logo" href="/" aria-label="Wwwebtech home"><?= wwt_logo_svg('#131614', '#7C817B') ?></a>
    <div class="lp-head__acts">
      <a class="lp-head__tel" href="tel:<?= e($PHONE_E) ?>">+91 85952 50209</a>
      <a class="btn btn--sm" href="<?= e($WA) ?>" rel="noopener">WhatsApp</a>
    </div>
  </div>
</header>

<main id="main">
<?php if ($view === 'report' && is_array($report)):
    [$ringColour, $bandWord] = $band((int)$report['score']);
    $order = ['bad' => 0, 'warn' => 1, 'ok' => 2, 'info' => 3];
    $checks = $report['checks'];
    usort($checks, static fn($x, $y) =>
        [$order[$x['state']], -$x['weight']] <=> [$order[$y['state']], -$y['weight']]);
?>
  <section class="sec">
    <div class="shell aud">
      <p class="pill">Website audit</p>
      <h1><?= e($report['host']) ?></h1>
      <div class="aud__score">
        <span class="aud__num" style="color:<?= $ringColour ?>"><?= (int)$report['score'] ?></span>
        <span class="aud__of">/ 100</span>
        <span class="aud__pill" style="color:<?= $ringColour ?>"><?= e($bandWord) ?></span>
      </div>
      <div class="aud__meter"><i style="width:<?= max(2, (int)$report['score']) ?>%;background:<?= $ringColour ?>"></i></div>
      <p class="lead"><?= e($report['headline']) ?></p>

      <?php foreach ($checks as $c): ?>
      <div class="check check--<?= e($c['state']) ?>">
        <div class="check__hd">
          <h2 class="check__name"><?= e($c['label']) ?></h2>
          <span class="check__tag"><?= e($stateWord[$c['state']]) ?></span>
        </div>
        <p class="check__find"><?= e($c['finding']) ?></p>
        <?php if ($c['fix'] !== ''): ?><p class="check__fix"><?= e($c['fix']) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>

      <p class="aud__meta">
        Every line above was measured against your live page on
        <?= e(gmdate('j F Y', strtotime((string)$report['checked']))) ?>.
        Nothing here is a template, and nothing is guessed.
      </p>
    </div>
  </section>

  <section class="sec sec--band">
    <div class="shell aud">
      <h2>Want the fixes done rather than listed?</h2>
      <p class="lead">Send this report back to us and we will tell you what each item would cost
      and what it is worth fixing first. If the answer is "nothing, your site is fine", that is
      what you will hear.</p>
      <p class="hero__cta">
        <a class="btn btn--lg" href="<?= e($WA) ?>" rel="noopener">Talk on WhatsApp</a>
        <a class="btn btn--ghost btn--lg" href="tel:<?= e($PHONE_E) ?>">Call +91 85952 50209</a>
      </p>
    </div>
  </section>

<?php elseif ($view === 'pending'): ?>
  <section class="sec">
    <div class="shell aud">
      <p class="pill">Website audit</p>
      <h1>We are checking <?= e((string)($audit['host'] ?? 'your site')) ?></h1>
      <p class="lead aud__wait"><span class="aud__dots" aria-hidden="true"></span>
        Seventeen checks against your live page. It takes about a minute.</p>
      <p>This page refreshes itself. You can also close it — the full report is on its way to
        <strong><?= e((string)($audit['email'] ?? 'your inbox')) ?></strong>, and this link keeps
        working:</p>
      <p class="mono"><?= e($SITE_URL . $base . 'report/?t=' . (string)($audit['token'] ?? '')) ?></p>
      <noscript><p class="hint">If nothing changes, reload the page in a minute.</p></noscript>
    </div>
  </section>

<?php elseif ($view === 'failed'): ?>
  <section class="sec">
    <div class="shell aud">
      <p class="pill">Website audit</p>
      <h1>We could not reach that site</h1>
      <p class="lead"><?= e((string)($audit['error'] ?? 'The site did not answer.')) ?></p>
      <p>That usually means a typo in the address, a site that is temporarily down, or a firewall
        that blocks anything that is not a person. Check the address and try again — or send it to
        us and we will look by hand.</p>
      <p class="hero__cta">
        <a class="btn" href="<?= e($base) ?>">Try another address</a>
        <a class="btn btn--ghost" href="<?= e($WA) ?>" rel="noopener">Send it on WhatsApp</a>
      </p>
    </div>
  </section>

<?php else: ?>
  <section class="sec">
    <div class="shell aud">
      <p class="pill">Free · no account · one email</p>
      <h1>Find out what your website is costing you</h1>
      <p class="lead">Seventeen checks run against your live page — speed, mobile layout, how
        Google reads you, and how easy you are to contact. You get the findings and the fix for
        each one, in plain language. If your site is already in good shape, the report says so.</p>

      <?php if ($errors): ?>
        <div class="err" role="alert">
          <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="formwrap">
        <div class="formwrap__head">
          <h2>Where should we look?</h2>
          <p>The report lands in your inbox in about a minute.</p>
        </div>
        <form method="post" action="<?= e($base) ?>" novalidate>
          <div class="field">
            <label for="a-url">Your website address</label>
            <input class="input" id="a-url" name="url" type="text" inputmode="url"
                   autocomplete="url" placeholder="mysite.in" required
                   value="<?= e($old['url']) ?>">
          </div>
          <div class="field">
            <label for="a-name">Your name</label>
            <input class="input" id="a-name" name="name" type="text" autocomplete="name"
                   required value="<?= e($old['name']) ?>">
          </div>
          <div class="field">
            <label for="a-email">Email</label>
            <input class="input" id="a-email" name="email" type="email" autocomplete="email"
                   required value="<?= e($old['email']) ?>">
            <p class="hint">This is where the report goes. We do not add you to a newsletter.</p>
          </div>
          <div class="field">
            <label for="a-phone">Phone <span class="hint">(optional)</span></label>
            <input class="input" id="a-phone" name="phone" type="tel" inputmode="tel"
                   autocomplete="tel" value="<?= e($old['phone']) ?>">
          </div>

          <div class="hp" aria-hidden="true">
            <label for="a-company">Company</label>
            <input id="a-company" name="company" type="text" tabindex="-1" autocomplete="off">
          </div>

          <label class="consent">
            <input type="checkbox" name="consent" value="1" required>
            <span><?= e(Leads::consentWording()) ?></span>
          </label>

          <p><button class="btn btn--lg btn--wide" type="submit">Run my free audit</button></p>
          <p class="hint">One audit per site per day. We check the page you give us and nothing else.</p>
        </form>
      </div>

      <h2>What gets checked</h2>
      <ul class="gets">
        <li>Whether Google is being told to ignore you — one wrong tag can hide a whole site.</li>
        <li>How the page behaves on a phone, which is where most Indian traffic comes from.</li>
        <li>Real speed data from Google, where it exists for your site.</li>
        <li>Your title, description and structured data — what a search result actually shows.</li>
        <li>How many taps it takes a customer to reach you.</li>
      </ul>
      <p class="hint">We do not crawl your whole site, store your pages, or sell anything to
        anyone. The report is generated from one visit to one page.</p>
    </div>
  </section>
<?php endif; ?>
</main>

<footer class="lp-foot">
  <div class="lp-foot__in shell">
    <p>Wwwebtech · East Delhi, Delhi</p>
    <p><a href="/legal/privacy/">Privacy</a> · <a href="/">Main site</a></p>
  </div>
</footer>
</body>
</html>
