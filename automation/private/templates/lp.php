<?php
/* ============================================================
   lp.php — the landing page template. All six pages render from here.

   Structural rules this file enforces, from §2.2 and §2.7:
     · No site navigation and no fat footer. Every removed exit is a
       retained visitor, and that is the entire reason these pages are
       separate from the service pages.
     · The H1 is the LCP element. No image above the fold, ever.
     · All CSS is inlined; there is no render-blocking request.
     · The form works with JavaScript off — it IS a plain form, and the
       script hides steps on top of it. Nothing is JS-only.

   Expects: $lp (the page data array), $matchLine (already resolved
   server-side), $variant, $csrfNonce.
   ============================================================ */

declare(strict_types=1);

if (!isset($lp) || !is_array($lp)) { http_response_code(500); exit('No landing page data.'); }

$SITE_URL  = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
$PHONE     = '+91 85952 50209';
$PHONE_E   = '+918595250209';
$WA        = 'https://wa.me/918595250209';
$canonical = $SITE_URL . '/lp/' . $lp['slug'] . '/';
/** Read a stylesheet from wherever this is running: repo or server. */
$readCss = static function (array $candidates): string {
    foreach ($candidates as $c) { if ($c !== '' && is_file($c)) return (string)file_get_contents($c); }
    return '';
};
/** Minify conservatively: comments and run-together whitespace only. */
$minify = static function (string $c): string {
    $c = preg_replace('#/\*[\s\S]*?\*/#', '', $c) ?? $c;
    $c = preg_replace('/\s+/', ' ', $c) ?? $c;
    return str_replace([' {', '{ ', ' }', '} ', '; ', ': ', ', '], ['{', '{', '}', '}', ';', ':', ','], $c);
};

$webroot = rtrim((string)cfg('site.webroot', ''), '/');
$repo    = dirname(__DIR__, 2);

$fontCss = $minify($readCss([
    $webroot . '/assets/css/fonts.css',
    dirname($repo) . '/site/assets/css/fonts.css',
]));
/* The font URLs are relative to /assets/css/; inlined into the page they must
   resolve from the document root instead. */
$fontCss = str_replace("url('../fonts/", "url('/assets/fonts/", $fontCss);

$css = $minify($readCss([
    $repo . '/webroot/lp/assets/lp.css',
    $webroot . '/lp/assets/lp.css',
]));

$tick = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" '
      . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10.5l4 4 8-9"/></svg>';

/* The FAQ block is also the FAQPage schema — one source, so they cannot
   disagree with each other. */
$faqLd = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
foreach ($lp['faq'] as [$q, $a]) {
    $faqLd['mainEntity'][] = ['@type' => 'Question', 'name' => $q,
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
}
$serviceLd = [
    '@context' => 'https://schema.org', '@type' => 'Service',
    'name' => $lp['h1'], 'description' => $lp['desc'],
    'areaServed' => ['@type' => 'Country', 'name' => 'India'],
    'provider' => ['@type' => 'Organization', 'name' => 'Wwwebtech', 'url' => $SITE_URL . '/'],
];
?><!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($lp['title']) ?></title>
<meta name="description" content="<?= e($lp['desc']) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($lp['title']) ?>">
<meta property="og:description" content="<?= e($lp['desc']) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($SITE_URL) ?>/og/home.png">
<?php /* The H1 is the LCP element and it is set in Fraunces, so that face is
         preloaded. Archivo follows immediately for everything else. Names are
         the real subset filenames — a preload pointing at a file that does not
         exist is a wasted round trip and a console warning. */ ?>
<link rel="preload" href="/assets/fonts/fraunces.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/archivo.woff2" as="font" type="font/woff2" crossorigin>
<?php /* fonts.css carries the @font-face rules AND the metric-matched fallback
         faces (size-adjust / ascent-override) that hold CLS near zero while a
         webfont swaps in. Without it this page would render in system fonts
         and shift when they arrived. Inlined rather than linked so there is
         no render-blocking request at all. */ ?>
<style><?= $fontCss ?><?= $css ?></style>
<script type="application/ld+json"><?= json_encode($serviceLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php /* The panel's tags, rendered live rather than written into a file:
         Tags::apply() only rewrites .html, so on a PHP page the markers it
         looks for were never filled. Deferred until after paint, so they
         cost nothing against the budget these pages are held to. Wrapped so
         a database hiccup cannot take a paid landing page down. */ ?>
<?php $tagsHead = ''; $tagsBody = '';
      if (!empty($ready) && class_exists('Tags')) {
          try { $tagsHead = Tags::headHtml(); $tagsBody = Tags::bodyHtml(); } catch (Throwable) {}
      } ?>
<?= $tagsHead ?></head>
<body>
<?= $tagsBody ?>
<a class="skip" href="#form">Skip to the form</a>

<?php /* ── 1 · Minimal header ─────────────────────────────── */ ?>
<header class="lp-head">
  <div class="shell lp-head__in">
    <a class="lp-head__logo" href="/" aria-label="Wwwebtech home"><?= wwt_logo_svg('#131614', '#7C817B') ?></a>
    <div class="lp-head__acts">
      <a class="lp-head__tel" href="tel:<?= e($PHONE_E) ?>"><span>Call </span><?= e($PHONE) ?></a>
      <a class="btn btn--sm" href="<?= e($WA) ?>" rel="noopener" data-cta="header-wa">WhatsApp</a>
    </div>
  </div>
</header>

<main id="main">

<?php /* ── 2 · Hero + 3 · Form ───────────────────────────── */ ?>
<section class="hero">
  <div class="shell hero__grid">
    <div>
      <h1><?= e($lp['h1']) ?></h1>
      <p class="lead hero__sub"><?= e($matchLine) ?></p>
      <ul class="hero__chips">
        <?php foreach ($lp['chips'] as $c): ?>
          <li class="chip"><?= $tick ?><?= e($c) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="hero__cta">
        <a class="btn btn--lg" href="#form" data-cta="hero"><?= e($lp['cta']) ?></a>
        <a class="btn btn--ghost btn--lg" href="<?= e($WA) ?>" rel="noopener" data-cta="hero-wa">WhatsApp us instead</a>
      </div>
    </div>
    <div id="form"><?php require __DIR__ . '/lp-form.php'; ?></div>
  </div>
</section>

<?php /* ── 4 · Trust strip ────────────────────────────────── */ ?>
<section class="sec sec--wash sec--rule" style="padding-block:var(--s5)">
  <div class="shell">
    <ul class="hero__chips" style="margin:0">
      <?php foreach ($lp['trust'] as $t): ?>
        <li class="chip"><?= $tick ?><?= e($t) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php /* ── 5 · Problem mirror ─────────────────────────────── */ ?>
<section class="sec">
  <div class="shell">
    <div class="sec__head">
      <p class="mono">If any of this sounds familiar</p>
      <h2>You are not disorganised. You have outgrown the tools.</h2>
    </div>
    <div class="grid grid--3">
      <?php foreach ($lp['pains'] as [$quote, $consequence]): ?>
        <div class="pain"><b><?= e($quote) ?></b><span><?= e($consequence) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php /* ── 6 · What you actually get ──────────────────────── */ ?>
<section class="sec sec--rule">
  <div class="shell">
    <div class="sec__head">
      <p class="mono">What you actually get</p>
      <h2>Deliverables, not adjectives.</h2>
    </div>
    <ul class="gets">
      <?php foreach ($lp['gets'] as $g): ?>
        <li><?= $tick ?><span><?= e($g) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php /* ── 7 · Proof ──────────────────────────────────────── */ ?>
<?php require __DIR__ . '/lp-proof.php'; ?>

<?php /* ── 8 · How it works ───────────────────────────────── */ ?>
<section class="sec sec--rule">
  <div class="shell">
    <div class="sec__head">
      <p class="mono">How it works</p>
      <h2>Four steps. You always know which one you are in.</h2>
    </div>
    <ol class="steps">
      <?php foreach ($lp['steps'] as [$name, $body, $when]): ?>
        <li><h3><?= e($name) ?></h3><p><?= e($body) ?></p><span class="when"><?= e($when) ?></span></li>
      <?php endforeach; ?>
    </ol>
    <p style="margin-top:var(--s6)"><a class="btn" href="#form" data-cta="steps"><?= e($lp['cta']) ?></a></p>
  </div>
</section>

<?php /* ── 9 · Objections ─────────────────────────────────── */ ?>
<section class="sec sec--band">
  <div class="shell">
    <div class="sec__head">
      <p class="mono" style="color:#7C817B">Before you ask</p>
      <h2>The questions we actually get.</h2>
    </div>
    <div class="grid grid--2">
      <?php foreach ($lp['objections'] as [$q, $a]): ?>
        <div class="card"><h3><?= e($q) ?></h3><p><?= e($a) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php /* ── 10 · Price anchor ──────────────────────────────── */ ?>
<section class="sec">
  <div class="shell">
    <div class="sec__head">
      <p class="mono">What it costs</p>
      <h2><?= $lp['price_from'] ? 'Where it starts, and what moves it.' : 'How we arrive at a number.' ?></h2>
    </div>
    <div class="price">
      <div>
        <?php if ($lp['price_from']): ?>
          <div class="price__fig">From <?= e((string)$lp['price_from']) ?></div>
          <p class="price__note">Fixed in writing before any work starts.</p>
        <?php else: ?>
          <div class="price__fig" style="font-size:1.5rem;white-space:normal;max-width:16ch">
            A fixed written price, after one call.</div>
          <p class="price__note">We publish how the number is built rather than a figure that
             would be wrong for most people who read it.</p>
        <?php endif; ?>
      </div>
      <ul class="price__moves">
        <?php foreach ($lp['price_moves'] as [$what, $why]): ?>
          <li><?= $tick ?><span><b><?= e($what) ?>.</b> <?= e($why) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<?php /* ── 11 · Risk reversal ─────────────────────────────── */ ?>
<section class="sec sec--wash sec--rule">
  <div class="shell">
    <div class="sec__head">
      <p class="mono">What you are not risking</p>
      <h2>The terms, before you ask for them.</h2>
    </div>
    <div class="grid grid--4">
      <div class="card"><h3>Fixed written scope</h3>
        <p>Agreed before work starts. If it is not in the document, it is not in the price.</p></div>
      <div class="card"><h3>No surprise invoices</h3>
        <p>Nothing is added without you agreeing to it in writing first.</p></div>
      <div class="card"><h3>You own everything</h3>
        <p>Code, domain, hosting, analytics and every third-party account, in your name from day one.</p></div>
      <div class="card"><h3>No lock-in</h3>
        <p>No minimum term and no clause that makes leaving expensive. Support is optional.</p></div>
    </div>
  </div>
</section>

<?php /* ── 12 · FAQ ───────────────────────────────────────── */ ?>
<section class="sec sec--rule">
  <div class="shell">
    <div class="sec__head">
      <p class="mono">Questions</p>
      <h2>Answered properly, not deflected.</h2>
    </div>
    <div class="faq">
      <?php foreach ($lp['faq'] as [$q, $a]): ?>
        <details><summary><?= e($q) ?></summary><div class="faq__body"><?= e($a) ?></div></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php /* ── 13 · Final CTA ─────────────────────────────────── */ ?>
<section class="sec sec--rule">
  <div class="shell" style="max-width:52ch;text-align:center">
    <h2><?= e($lp['final_h2']) ?></h2>
    <p class="lead" style="margin-top:var(--s4)"><?= e($lp['final_sub']) ?></p>
    <p style="margin-top:var(--s6);display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap">
      <a class="btn btn--lg" href="#form" data-cta="final"><?= e($lp['cta']) ?></a>
      <a class="btn btn--ghost btn--lg" href="<?= e($WA) ?>" rel="noopener" data-cta="final-wa">WhatsApp us</a>
    </p>
  </div>
</section>

</main>

<footer class="lp-foot">
  <div class="shell lp-foot__in">
    <span>&copy; <?= date('Y') ?> Wwwebtech · East Delhi, Delhi, India</span>
    <span>
      <a href="/legal/privacy/">Privacy</a> ·
      <a href="/legal/terms/">Terms</a> ·
      <a href="mailto:contact@wwwebtech.in">contact@wwwebtech.in</a> ·
      <a href="tel:<?= e($PHONE_E) ?>"><?= e($PHONE) ?></a>
    </span>
  </div>
</footer>

<?php /* Sticky mobile action bar — the three things a phone visitor does. */ ?>
<nav class="bar" aria-label="Contact">
  <a href="tel:<?= e($PHONE_E) ?>" data-cta="bar-call">Call</a>
  <a href="<?= e($WA) ?>" rel="noopener" data-cta="bar-wa">WhatsApp</a>
  <a href="#form" class="is-primary" data-cta="bar-form">Get proposal</a>
</nav>

<script src="/lp/assets/lp.js" defer></script>
</body>
</html>
