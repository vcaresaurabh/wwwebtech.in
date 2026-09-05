<?php
/* Chrome for every signed-in admin page.
   Usage:  layout_top('Leads', 'leads');  … page markup …  layout_bottom(); */
declare(strict_types=1);

/* Not a page. Requested directly it must 404, not execute: this file is an
   include, and on a misconfigured host a direct hit would otherwise run its
   side effects (or, with the wrong handler, print its own source). */
if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


const WWT_NAV = [
    ['leads',        'Leads',        'M4.5 5.5h15v13h-15z M4.5 8.5l7.5 5 7.5-5'],
    ['conversations','Conversations','M4.5 5.5h15v10h-9l-4 4z M8 9h8 M8 12h5'],
    ['funnel',       'Funnel',       'M3.5 5h17l-6.5 7.5V20l-4-2.5v-5z'],
    ['landing',      'Landing pages','M3.5 5.5h17v13h-17z M3.5 9.5h17 M7 13h6'],
    ['audits',       'Audits',       'M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13z M16 16l4 4 M8.5 11l1.8 1.8 3.2-3.6'],
    ['analytics',    'Analytics',    'M4 19V9 M9.5 19V5 M15 19v-7 M20.5 19v-11'],
    ['blog',         'Blog',         'M6 3.5h8l4 4v13H6z M14 3.5v4h4 M9 12.5h6 M9 16h4'],
    ['seo',          'SEO health',   'M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13z M16 16l4 4'],
    ['integrations', 'Integrations', 'M9 4.5v5 M15 4.5v5 M6.5 9.5h11v4a5.5 5.5 0 0 1-11 0z M12 19v2'],
    ['connections',  'Connections',  'M8 12h8 M5 12a3 3 0 1 0 0-.01 M19 12a3 3 0 1 0 0-.01 M12 5v4 M12 15v4'],
    ['settings',     'Settings',     'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1 2 2 0 1 1-4 0 1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3.5 15a2 2 0 1 1 0-4 1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 4.5a2 2 0 1 1 4 0 1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7 2 2 0 1 1 0 4z'],
];

require_once WWT_PRIVATE . '/templates/logo.php';

function layout_top(string $title, string $active = ''): void
{
    $flash = flash();
    ?><!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Wwwebtech admin</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/admin/assets/admin.css?v=<?= e(substr(hash('crc32b', (string)@filemtime(__DIR__ . '/assets/admin.css')), 0, 6)) ?>">
</head>
<body>
<div class="shell">
  <aside class="side">
    <div class="side__brand"><?= wwt_logo_svg('#F3F1EA', '#7C817B') ?></div>
    <p class="side__tag">Operations panel</p>
    <nav aria-label="Sections">
      <a href="/admin/"<?= $active === 'dashboard' ? ' aria-current="page"' : '' ?>>
        <svg class="ic" viewBox="0 0 24 24"><path d="M4 12.5 12 5l8 7.5V20H4z"/></svg> Dashboard</a>
      <?php foreach (WWT_NAV as [$slug, $label, $d]):
            // Viewers get read-only sections only.
            if (!Auth::isAdmin() && in_array($slug, ['integrations', 'settings', 'blog'], true)) continue; ?>
      <a href="/admin/?p=<?= e($slug) ?>"<?= $active === $slug ? ' aria-current="page"' : '' ?><?php
          /* The one nav item that can demand attention: a connection in Error. */
          if ($slug === 'connections' && Settings::bool('conn_attention', false)) echo ' class="side__attn" title="A connection needs attention"'; ?>>
        <svg class="ic" viewBox="0 0 24 24"><path d="<?= e($d) ?>"/></svg> <?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side__foot">
      <a href="https://wwwebtech.in" target="_blank" rel="noopener">View site ↗</a>
    </div>
  </aside>

  <div class="main">
    <header class="top">
      <span class="mono"><?= e($title) ?></span>
      <span class="top__who">
        <b><?= e(Auth::email()) ?></b>
        <?php if (!Auth::isAdmin()): ?><span class="pill pill--info" style="margin-left:.4rem">read-only</span><?php endif; ?>
        ·
        <?php /* A POST, not a link. A GET sign-out can be triggered by any
                 image tag on any page the owner happens to be reading. */ ?>
        <form method="post" action="/admin/?p=logout" style="display:inline">
          <?= Csrf::field() ?>
          <button type="submit" class="linkbtn">Sign out</button>
        </form>
      </span>
    </header>
    <main class="content">
      <?php if ($flash): ?>
        <div class="alert alert--<?= e($flash['type'] ?: 'info') ?>"><?= e($flash['msg']) ?></div>
      <?php endif; ?>
<?php
}

function layout_bottom(): void
{
    ?>    </main>
  </div>
</div>
</body>
</html>
<?php
}

/**
 * A daily timeseries as inline SVG. No chart library: this is one path
 * and some ticks, and shipping a 200KB dependency to draw it would be
 * embarrassing on a site sold on page weight.
 *
 * @param array<int,array{d:string,views:int,visitors:int}> $rows
 */
function svg_timeseries(array $rows, int $h = 170): string
{
    $n = count($rows);
    if ($n < 2) return '<div class="t-empty"><b>Not enough data yet</b>A line needs at least two days.</div>';

    $w = 1000;                       // viewBox units; the SVG scales to its box
    $padT = 12; $padB = 26; $padL = 38; $padR = 8;
    $plotH = $h - $padT - $padB;
    $plotW = $w - $padL - $padR;

    $max = 0;
    foreach ($rows as $r) $max = max($max, (int)$r['views']);
    /* A flat zero series must not divide by zero, and must not be drawn as
       if it were a full-height line either. */
    $scaleMax = $max > 0 ? (int)(ceil($max / 4) * 4) : 4;

    $x = static fn(int $i): float => $padL + ($plotW * ($n === 1 ? 0 : $i / ($n - 1)));
    $y = static fn(int $v): float => $padT + $plotH - ($plotH * ($v / $scaleMax));

    $lineViews = []; $lineVis = []; $area = [];
    foreach ($rows as $i => $r) {
        $lineViews[] = round($x($i), 1) . ',' . round($y((int)$r['views']), 1);
        $lineVis[]   = round($x($i), 1) . ',' . round($y((int)$r['visitors']), 1);
    }
    $area = 'M' . $x(0) . ',' . ($padT + $plotH) . ' L' . implode(' L', $lineViews)
          . ' L' . $x($n - 1) . ',' . ($padT + $plotH) . ' Z';

    /* Gridlines and their labels. */
    $grid = '';
    for ($g = 0; $g <= 4; $g++) {
        $v  = (int)round($scaleMax * $g / 4);
        $gy = round($y($v), 1);
        $grid .= '<line x1="' . $padL . '" y1="' . $gy . '" x2="' . ($w - $padR) . '" y2="' . $gy
               . '" stroke="var(--rule)" stroke-width="1" vector-effect="non-scaling-stroke"/>'
               . '<text x="' . ($padL - 6) . '" y="' . ($gy + 3.5) . '" text-anchor="end"'
               . ' font-size="10" fill="var(--ink-3)">' . $v . '</text>';
    }

    /* Date labels: first, middle, last — more than that is unreadable. */
    $ticks = '';
    foreach ([0, intdiv($n - 1, 2), $n - 1] as $i) {
        $label = date('j M', strtotime($rows[$i]['d']));
        $anchor = $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle');
        $ticks .= '<text x="' . round($x($i), 1) . '" y="' . ($h - 8) . '" text-anchor="' . $anchor
                . '" font-size="10" fill="var(--ink-3)">' . e($label) . '</text>';
    }

    $totalViews = array_sum(array_column($rows, 'views'));
    $totalVis   = array_sum(array_column($rows, 'visitors'));
    $alt = sprintf('%s pageviews from %s visitors across %d days, %s to %s.',
        number_format($totalViews), number_format($totalVis), $n,
        date('j M', strtotime($rows[0]['d'])), date('j M', strtotime($rows[$n - 1]['d'])));

    return '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none"'
         . ' role="img" aria-label="' . e($alt) . '">'
         . $grid
         . '<path d="' . $area . '" fill="var(--marigold-wash)"/>'
         . '<polyline points="' . implode(' ', $lineViews) . '" fill="none" stroke="var(--marigold)"'
         . ' stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>'
         . '<polyline points="' . implode(' ', $lineVis) . '" fill="none" stroke="var(--ink)"'
         . ' stroke-width="1.5" stroke-dasharray="3 3" vector-effect="non-scaling-stroke"'
         . ' stroke-linejoin="round"/>'
         . $ticks
         . '</svg>';
}

/** A horizontal proportion bar for the "top N" tables. */
function bar(int $value, int $max): string
{
    $pct = $max > 0 ? max(1.5, round($value / $max * 100, 1)) : 0;
    return '<span class="bar"><span style="width:' . $pct . '%"></span></span>';
}

/** Standard empty state. */
function empty_state(string $headline, string $body): void
{
    echo '<div class="t-empty"><b>' . e($headline) . '</b>' . e($body) . '</div>';
}
