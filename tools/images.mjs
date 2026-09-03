#!/usr/bin/env node
/* ============================================================
   Rasterises the favicon set and renders one Open Graph card per
   page. Run after `node build.mjs`:

     node tools/images.mjs

   Every card is drawn from the page's own <title> and <meta og:image>,
   so this can never drift from what the pages actually declare.
   Needs Playwright (QA-only dependency) — see tools/shots.mjs.
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { listPages, serve } from './shots.mjs';

const PW = process.env.PLAYWRIGHT_PATH || 'playwright';
const { chromium } = await import(PW);

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const OUT  = path.join(ROOT, 'site');
const OG   = path.join(OUT, 'og');
fs.mkdirSync(OG, { recursive: true });

const read = (p) => fs.readFileSync(p, 'utf8');
const grab = (html, re) => (re.exec(html) || [])[1] || '';

/* --- 1. Favicons ---------------------------------------------------- */
const ICONS = [
  { svg: 'site/assets/img/mark-tiny.svg', out: 'favicon-32.png',      size: 32  },
  { svg: 'site/favicon.svg',              out: 'apple-touch-icon.png', size: 180 },
  { svg: 'site/favicon.svg',              out: 'icon-192.png',        size: 192 },
  { svg: 'site/favicon.svg',              out: 'icon-512.png',        size: 512 },
];

/* --- 2. OG card template -------------------------------------------- */
const FONTS = `
@font-face{font-family:Fraunces;src:url('/assets/fonts/fraunces.woff2') format('woff2');font-weight:400 600}
@font-face{font-family:Archivo;src:url('/assets/fonts/archivo.woff2') format('woff2');font-weight:400 600}
@font-face{font-family:Plex;src:url('/assets/fonts/plexmono-500.woff2') format('woff2');font-weight:500}`;

const ogCard = ({ headline, kicker, wordPath, tailPath }) => `<!doctype html><meta charset="utf-8">
<style>
${FONTS}
*{margin:0;box-sizing:border-box}
body{width:1200px;height:630px;background:#FAF8F4;color:#131614;
     font-family:Archivo,sans-serif;position:relative;overflow:hidden}
.grid{position:absolute;inset:0;width:100%;height:100%;opacity:.5;color:#E3E0D8}
.pad{position:relative;height:100%;padding:72px 80px;display:flex;flex-direction:column;justify-content:space-between}
.logo svg{height:34px;width:auto}
h1{font-family:Fraunces,Georgia,serif;font-weight:500;font-size:74px;line-height:1.02;
   letter-spacing:-.022em;max-width:15.5ch;text-wrap:balance}
.rule{width:104px;height:6px;background:#E07000;margin-bottom:34px}
.foot{display:flex;justify-content:space-between;align-items:flex-end;
      font-family:Plex,monospace;font-size:17px;letter-spacing:.11em;text-transform:uppercase;color:#8A908B}
.kick{font-family:Plex,monospace;font-size:17px;letter-spacing:.11em;text-transform:uppercase;
      color:#B65600;margin-bottom:26px}
</style>
<svg class="grid" aria-hidden="true"><defs><pattern id="g" width="72" height="72" patternUnits="userSpaceOnUse">
<path d="M72 0H0V72" fill="none" stroke="currentColor" stroke-width="1"/></pattern></defs>
<rect width="100%" height="100%" fill="url(#g)"/></svg>
<div class="pad">
  <div class="logo"><svg viewBox="0 28 568 78"><path fill="#131614" d="${wordPath}"/>
    <path fill="#8A908B" d="${tailPath}"/><circle fill="#E07000" cx="554" cy="87" r="10"/></svg></div>
  <div>
    ${kicker ? `<div class="kick">${kicker}</div>` : ''}
    <div class="rule"></div>
    <h1>${headline}</h1>
  </div>
  <div class="foot"><span>wwwebtech.in</span><span>East Delhi · All of India</span></div>
</div>`;

/* --- Go -------------------------------------------------------------- */
const logoSvg = read(path.join(ROOT, 'public/assets/logos/wwwebtech.svg'));
const d = /\sd="([^"]+)"/.exec(logoSvg)[1];
const subs = d.split('M').filter(s => s.trim()).map(s => 'M' + s);
const wordPath = subs.slice(0, 7).join(' ').trim();
const tailPath = subs.slice(7).join(' ').trim();

const { server, port } = await serve();
const browser = await chromium.launch();

/* favicons */
for (const ic of ICONS) {
  const page = await browser.newPage({ viewport: { width: ic.size, height: ic.size } });
  await page.goto('data:text/html,' + encodeURIComponent(
    `<style>*{margin:0}body{width:${ic.size}px;height:${ic.size}px}svg{width:100%;height:100%;display:block}</style>`
    + read(path.join(ROOT, ic.svg))));
  await page.screenshot({ path: path.join(OUT, ic.out), omitBackground: true });
  await page.close();
  console.log('  ' + ic.out);
}

/* OG cards, one per built page */
const seen = new Set();
const ogPage = await browser.newPage({ viewport: { width: 1200, height: 630 } });
for (const p of listPages()) {
  const file = p.endsWith('.html') ? path.join(OUT, p.replace(/^\//, ''))
                                   : path.join(OUT, p.replace(/^\//, ''), 'index.html');
  const html = read(file);
  const img = grab(html, /property="og:image" content="[^"]*\/og\/([^"]+)\.png"/);
  if (!img || seen.has(img)) continue;
  seen.add(img);

  const title = grab(html, /<title>([^<]*)<\/title>/)
    .replace(/\s*\|\s*Wwwebtech\s*$/, '').replace(/&amp;/g, '&').trim();
  const crumb = grab(html, /<li><span aria-current="page">([^<]+)<\/span><\/li>/);
  const kicker = p === '/' ? 'Web · SEO · Social'
    : p.startsWith('/blog/') && p !== '/blog/' ? 'From the blog'
    : p.startsWith('/services/') ? 'Services' : (crumb || '');

  await ogPage.goto(`http://localhost:${port}/`);   // same origin, so the fonts resolve
  await ogPage.setContent(ogCard({ headline: title, kicker, wordPath, tailPath }));
  await ogPage.evaluate(() => document.fonts.ready);
  await ogPage.screenshot({ path: path.join(OG, img + '.png') });
  console.log(`  og/${img}.png   ${title.slice(0, 52)}`);
}

await browser.close(); server.close();

const total = fs.readdirSync(OG).reduce((n, f) => n + fs.statSync(path.join(OG, f)).size, 0);
console.log(`\n  ${seen.size} OG cards, ${(total / 1024).toFixed(0)}K total`);
