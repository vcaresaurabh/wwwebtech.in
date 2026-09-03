/* ============================================================
   The document shell. One place that decides what every page's
   <head>, chrome and script loading look like.
   ============================================================ */
import { SITE } from './data.mjs';
import { nav, footer, logoDefs } from './partials/chrome.mjs';
import { sprite } from './partials/icons.mjs';

const esc = (s = '') => String(s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/* Curly quotes read better on the page but must not reach <title>/<meta>
   as raw entities — they're fine as UTF-8, the doc is utf-8. */
export const layout = ({
  path, title, desc, body, css, schema, nav: navKey, og = 'home',
  wordPaths, noindex = false, bodyClass = '', ogType = 'website', extraHead = '',
}) => {
  const url = SITE.origin + path;
  const ogImg = `${SITE.origin}/og/${og}.png`;
  return `<!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>document.documentElement.classList.add('js-on')</script>
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<link rel="canonical" href="${url}">
${noindex ? '<meta name="robots" content="noindex, nofollow">' : '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">'}

<!-- The two faces above the fold. Nothing else is preloaded. -->
<link rel="preload" href="/assets/fonts/fraunces.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/archivo.woff2"  as="font" type="font/woff2" crossorigin>

<!-- All CSS, inlined. It gzips to ~10KB, so this costs one round trip fewer
     than a stylesheet link and never blocks the LCP text. (§11) -->
<style>${css}</style>

<meta property="og:type" content="${ogType}">
<meta property="og:site_name" content="Wwwebtech">
<meta property="og:locale" content="en_IN">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(desc)}">
<meta property="og:url" content="${url}">
<meta property="og:image" content="${ogImg}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(desc)}">
<meta name="twitter:image" content="${ogImg}">

<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#FAF8F4">
${extraHead}
<script type="application/ld+json">${schema}</script>

<!-- ANALYTICS: the owner's GA4 property is ${SITE.analyticsId}. It is deliberately
     NOT loaded — uncomment to switch it on, and update /legal/privacy/ to match.
<script async src="https://www.googletagmanager.com/gtag/js?id=${SITE.analyticsId}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}
gtag('js',new Date());gtag('config','${SITE.analyticsId}');</script>
-->
<!-- Tag manager. The admin panel writes between these markers and touches
     nothing else, so applying tags is repeatable and clearing them puts the
     page back exactly as the build left it. Do not remove. -->
<!--WWT_TAGS_HEAD_START--><!--WWT_TAGS_HEAD_END-->
</head>
<body${bodyClass ? ` class="${bodyClass}"` : ''}>
<!--WWT_TAGS_BODY_START--><!--WWT_TAGS_BODY_END-->
${nav()}
<main id="main">
${body}
</main>
${footer()}
${logoDefs(wordPaths.word, wordPaths.tail)}
${sprite()}
<script src="/assets/js/main.js" defer></script>
<!-- First-party analytics: no cookies, no identifier, ~1KB. It posts to
     /api/hit.php, which only exists once the automation layer is deployed;
     until then the beacon fails silently and nothing else is affected. -->
<script src="/assets/js/wa.js" defer></script>
</body>
</html>`;
};
