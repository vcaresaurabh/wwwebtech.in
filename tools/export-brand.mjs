#!/usr/bin/env node
/* ============================================================
   Brand export pack — PNGs for social profiles, Google Business
   Profile, and anywhere else that can't use an SVG.

     node tools/export-brand.mjs

   Writes to brand-export/. Everything is rendered from the same
   outlines the website uses, so these can never drift from the site.

   Needs Playwright (QA-only dependency) — see tools/shots.mjs.
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const PW = process.env.PLAYWRIGHT_PATH || 'playwright';
let chromium;
for (const p of [PW, 'playwright']) { try { ({ chromium } = await import(p)); break; } catch {} }
if (!chromium) { console.error('Playwright not found. npm i -D playwright && npx playwright install chromium'); process.exit(2); }

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const OUT  = path.join(ROOT, 'brand-export');

/* --- Brand constants, matching site/assets/css/tokens.css ---------- */
const INK      = '#131614';
const INK_3    = '#686D69';
const PAPER    = '#FAF8F4';
const MARIGOLD = '#E07000';
const BAND_INK = '#F3F1EA';
const BAND_MUT = '#7C817B';
const RULE     = '#E3E0D8';

/* --- The wordmark outlines, cut the same way the site cuts them ---- */
const logoSvg = fs.readFileSync(path.join(ROOT, 'public/assets/logos/wwwebtech.svg'), 'utf8');
const d = /\sd="([^"]+)"/.exec(logoSvg)[1];
const subs = d.split('M').filter(s => s.trim()).map(s => 'M' + s);
const WORD = subs.slice(0, 7).join(' ').trim();   // w w w e b
const TAIL = subs.slice(7).join(' ').trim();      // t e c h

/* The w3 mark, straight out of the file brand.py generates. */
const markSvg  = fs.readFileSync(path.join(ROOT, 'site/assets/img/mark.svg'), 'utf8');
const MARK_VB    = /viewBox="([^"]+)"/.exec(markSvg)[1];
const MARK_INNER = /<g fill="currentColor">([\s\S]*?)<\/g>/.exec(markSvg)[1];

const wordmark = (h, word, tail, dot) =>
  `<svg viewBox="0 28 568 78" style="height:${h}px;width:auto;display:block">
     <path fill="${word}" d="${WORD}"/><path fill="${tail}" d="${TAIL}"/>
     <circle fill="${dot}" cx="554" cy="87" r="10"/></svg>`;

/* Just the 'w'. Below about 24px the superscript is illegible mud, so the
   small favicons use this instead of rendering a smudge. */
const W1 = subs[0];
const markTiny = (size, fill) =>
  `<svg viewBox="0.2 53.6 76.3 49.4" style="width:${size}px;height:auto;display:block">
     <path fill="${fill}" d="${W1}"/></svg>`;

const mark = (size, fill) =>
  `<svg viewBox="${MARK_VB}" style="width:${size}px;height:auto;display:block">
     <g fill="${fill}">${MARK_INNER}</g></svg>`;

const page = (w, h, bg, body, extra = '') => `<!doctype html><meta charset="utf-8"><style>
*{margin:0;box-sizing:border-box}
html,body{width:${w}px;height:${h}px}
body{background:${bg};display:flex;align-items:center;justify-content:center;overflow:hidden;
     font-family:system-ui,sans-serif}
${extra}</style>${body}`;

/* ============================================================
   What gets exported
   ============================================================ */

/* Square avatar. Every one of these platforms crops to a circle, so the
   mark is sized to sit well inside the inscribed circle, not the square. */
const avatar = (size, { bg = INK, fg = MARIGOLD, radius = 0.19, tiny = false } = {}) => page(size, size, 'transparent',
  `<div class="tile">${tiny ? markTiny(size * 0.44, fg) : mark(size * 0.68, fg)}</div>`,
  `.tile{width:${size}px;height:${size}px;background:${bg};border-radius:${size * radius}px;
         display:flex;align-items:center;justify-content:center}`);

/* Horizontal lockup on a transparent ground. */
const lockup = (h, variant) => {
  const pad = Math.round(h * 0.42);
  const c = variant === 'dark'  ? [BAND_INK, BAND_MUT, MARIGOLD]
          : variant === 'mono-black' ? [INK, INK, INK]
          : variant === 'mono-white' ? ['#FFFFFF', '#FFFFFF', '#FFFFFF']
          : [INK, INK_3, MARIGOLD];
  return page(1, 1, 'transparent',
    `<div class="w">${wordmark(h, ...c)}</div>`,
    `html,body{width:auto;height:auto}body{display:inline-block}
     .w{display:inline-block;padding:${pad}px ${pad}px}`);
};

/* Cover / banner. Keep everything important centred — every platform
   crops these differently, and some crop hard. */
const cover = (w, h, { tag = true } = {}) => page(w, h, PAPER,
  `<svg class="grid"><defs><pattern id="g" width="${Math.round(h / 8)}" height="${Math.round(h / 8)}"
     patternUnits="userSpaceOnUse"><path d="M${Math.round(h / 8)} 0H0V${Math.round(h / 8)}"
     fill="none" stroke="${RULE}" stroke-width="1"/></pattern></defs>
   <rect width="100%" height="100%" fill="url(#g)"/></svg>
   <div class="mid">
     ${wordmark(Math.round(h * 0.22), INK, INK_3, MARIGOLD)}
     ${tag ? `<div class="rule"></div>
     <p class="tag">Web development · SEO &amp; AI visibility · Social media</p>
     <p class="loc">East Delhi · Serving all of India</p>` : ''}
   </div>`,
  `.grid{position:absolute;inset:0;width:100%;height:100%;opacity:.5}
   .mid{position:relative;display:flex;flex-direction:column;align-items:center;gap:${Math.round(h * 0.04)}px}
   .rule{width:${Math.round(h * 0.16)}px;height:${Math.max(2, Math.round(h * 0.012))}px;background:${MARIGOLD}}
   .tag{font-size:${Math.round(h * 0.062)}px;color:${INK};letter-spacing:-.01em;
        text-align:center;max-width:${Math.round(w * 0.82)}px;text-wrap:balance;line-height:1.25}
   .loc{font-size:${Math.round(h * 0.05)}px;color:${INK_3};letter-spacing:.06em;
        text-transform:uppercase;text-align:center}`);

const JOBS = [
  /* --- Profile pictures / logos -------------------------------------- */
  ['profile/google-business-profile-720.png',   avatar(720),        720, 720],
  ['profile/instagram-1080.png',                avatar(1080),      1080, 1080],
  ['profile/linkedin-400.png',                  avatar(400),        400, 400],
  ['profile/facebook-512.png',                  avatar(512),        512, 512],
  ['profile/whatsapp-business-640.png',         avatar(640),        640, 640],
  ['profile/avatar-1024.png',                   avatar(1024),      1024, 1024],
  ['profile/avatar-512.png',                    avatar(512),        512, 512],
  ['profile/avatar-256.png',                    avatar(256),        256, 256],
  /* Louder alternate: ink mark on a marigold ground. */
  ['profile/alt-marigold-1024.png',             avatar(1024, { bg: MARIGOLD, fg: INK }), 1024, 1024],
  /* Perfectly round, for anywhere that will not crop for you. */
  ['profile/avatar-circle-1024.png',            avatar(1024, { radius: 0.5 }), 1024, 1024],

  /* --- Horizontal wordmark, transparent ------------------------------ */
  ['logo/wordmark-light-bg-2400.png',           lockup(360, 'light')],
  ['logo/wordmark-light-bg-1200.png',           lockup(180, 'light')],
  ['logo/wordmark-dark-bg-2400.png',            lockup(360, 'dark')],
  ['logo/wordmark-dark-bg-1200.png',            lockup(180, 'dark')],
  ['logo/wordmark-mono-black-2400.png',         lockup(360, 'mono-black')],
  ['logo/wordmark-mono-white-2400.png',         lockup(360, 'mono-white')],

  /* --- Covers / banners ---------------------------------------------- */
  ['cover/google-business-profile-1024x576.png', cover(1024, 576), 1024, 576],
  ['cover/linkedin-1128x191.png',                cover(1128, 191, { tag: false }), 1128, 191],
  ['cover/facebook-820x312.png',                 cover(820, 312), 820, 312],
  ['cover/x-twitter-1500x500.png',               cover(1500, 500), 1500, 500],

  /* --- Favicon set (same files the website uses) --------------------- */
  ['favicon/favicon-16.png',                     avatar(16,  { radius: 0.16, tiny: true }), 16, 16],
  ['favicon/favicon-32.png',                     avatar(32,  { radius: 0.16, tiny: true }), 32, 32],
  ['favicon/favicon-48.png',                     avatar(48,  { radius: 0.18 }), 48, 48],
  ['favicon/apple-touch-icon-180.png',           avatar(180), 180, 180],
  ['favicon/icon-192.png',                       avatar(192), 192, 192],
  ['favicon/icon-512.png',                       avatar(512), 512, 512],
];

/* ============================================================ */
fs.rmSync(OUT, { recursive: true, force: true });
const browser = await chromium.launch();

for (const [name, html, w, h] of JOBS) {
  const file = path.join(OUT, name);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  const p = await browser.newPage({
    viewport: { width: w || 2000, height: h || 800 },
    deviceScaleFactor: 1,
  });
  await p.setContent(html);
  await p.evaluate(() => document.fonts && document.fonts.ready);
  if (w) await p.screenshot({ path: file, omitBackground: true });
  else   await p.locator('.w').screenshot({ path: file, omitBackground: true });
  await p.close();
  const { width, height } = { width: w || '?', height: h || '?' };
  console.log(`  ${name.padEnd(46)} ${String(width).padStart(5)}x${String(height).padEnd(5)} ${(fs.statSync(file).size / 1024).toFixed(1)}K`);
}

/* The SVGs too — always prefer these where a platform accepts them. */
fs.mkdirSync(path.join(OUT, 'svg'), { recursive: true });
for (const [from, to] of [
  ['site/assets/img/logo.svg', 'svg/wordmark.svg'],
  ['site/assets/img/mark.svg', 'svg/mark-w3.svg'],
  ['site/favicon.svg',         'svg/favicon.svg'],
]) fs.copyFileSync(path.join(ROOT, from), path.join(OUT, to));

await browser.close();
console.log(`\n  -> ${path.relative(ROOT, OUT)}/`);
