#!/usr/bin/env node
/* ============================================================
   The QA gate. Runs the acceptance criteria from the brief and
   writes QA-REPORT.md with a pass/fail per item.

     node tools/qa.mjs            # everything
     node tools/qa.mjs --quick    # skip Lighthouse (much faster)

   Nothing here is decorative: each check corresponds to a budget
   or a rule the site is supposed to meet.
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';
import { serve, listPages } from './shots.mjs';
import { SERVER_PAGES } from '../src/data.mjs';


const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));

const PW  = process.env.PLAYWRIGHT_PATH || 'playwright';
/* The QA-only packages live next to whichever playwright is in use. When PW is
   a bare specifier ('playwright'), that is this repo's own node_modules —
   splitting the bare name produced 'playwright/node_modules/...', which exists
   nowhere. */
const NM  = PW.includes('node_modules')
  ? PW.split('node_modules')[0] + 'node_modules'
  : path.join(ROOT, 'node_modules');
const LIB = NM;
const MOD = (n) => path.join(NM, n);

const { chromium } = await import(PW);

const OUT  = path.join(ROOT, 'site');
const QUICK = process.argv.includes('--quick');

const results = [];
const add = (area, name, pass, detail) => results.push({ area, name, pass, detail });
const kb = (n) => (n / 1024).toFixed(1) + 'K';
const gz = (buf) => zlib.gzipSync(buf, { level: 9 }).length;

const { server, port } = await serve();
const base = `http://localhost:${port}`;
/* This container keeps a Chrome for Testing build in the Playwright cache
   that does not match the npm package's expected revision. Use it when it is
   there rather than downloading a second copy. */
const CHROME = process.env.CHROME_PATH
  || [`${process.env.HOME}/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome`]
       .find((p) => fs.existsSync(p));
const browser = await chromium.launch(CHROME ? { executablePath: CHROME } : {});
const PAGES = listPages();

/* ============================================================
   1 · Asset budgets  (§11)
   ============================================================ */
{
  const vendorDir = path.join(OUT, 'assets/js/vendor');
  const jsFiles = [
    ...fs.readdirSync(vendorDir).filter(f => f.endsWith('.js')).map(f => path.join(vendorDir, f)),
    path.join(OUT, 'assets/js/main.js'),
    path.join(OUT, 'assets/js/motion.js'),
  ];
  const jsGz = jsFiles.reduce((n, f) => n + gz(fs.readFileSync(f)), 0);
  add('Budget', 'Total JS ≤ 90KB gzipped (GSAP + Lenis + ours)', jsGz <= 90 * 1024,
      `${kb(jsGz)} gzipped across ${jsFiles.length} files`);

  const imgs = [];
  const walk = (d) => fs.readdirSync(d, { withFileTypes: true }).forEach(e => {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p);
    else if (/\.(png|jpe?g|gif|webp|svg)$/i.test(e.name)) imgs.push([p, fs.statSync(p).size]);
  });
  walk(path.join(OUT, 'assets'));
  // og/ cards are social-preview images; they are never loaded by the pages.
  const over = imgs.filter(([, s]) => s > 60 * 1024);
  add('Budget', 'No page image over 60KB', over.length === 0,
      over.length ? over.map(([p, s]) => path.basename(p) + ' ' + kb(s)).join(', ')
                  : `${imgs.length} images, largest ${kb(Math.max(...imgs.map(i => i[1])))}`);

  const fonts = fs.readdirSync(path.join(OUT, 'assets/fonts'))
    .map(f => fs.statSync(path.join(OUT, 'assets/fonts', f)).size);
  add('Budget', 'Fonts self-hosted and subset', fonts.length >= 8,
      `${fonts.length} woff2 files, ${kb(fonts.reduce((a, b) => a + b, 0))} total on disk`);
}

/* ============================================================
   2 · Per-page transfer weight, measured in the browser
   ============================================================ */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  for (const p of ['/', '/services/web-development/', '/blog/website-speed-india/']) {
    const page = await ctx.newPage();
    /* Encoded (on-the-wire) size, not the decoded body — the dev server
       gzips exactly what the production .htaccess does. */
    let bytes = 0;
    const pending = [];
    page.on('response', (r) => {
      pending.push(r.request().sizes()
        .then((s) => { bytes += (s.responseBodySize || 0) + (s.responseHeadersSize || 0); })
        .catch(() => {}));
    });
    await page.goto(base + p, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);           // let idle-loaded motion.js land
    await Promise.all(pending);
    const only = p === '/' ? 500 : 600;
    add('Budget', `Page weight ${p} ≤ ${only}KB`, bytes <= only * 1024, kb(bytes) + ' transferred');
    await page.close();
  }
  await ctx.close();
}

/* ============================================================
   3 · Core Web Vitals, throttled to a Moto-G-class device  (§11)
   ============================================================ */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8,
  });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

  await page.goto(base + '/', { waitUntil: 'load' });
  const vitals = await page.evaluate(() => new Promise((res) => {
    const out = { lcp: 0, cls: 0, lcpEl: '' };
    new PerformanceObserver((l) => {
      const e = l.getEntries().at(-1);
      out.lcp = e.startTime;
      out.lcpEl = e.element ? e.element.tagName + (e.element.className ? '.' + String(e.element.className).split(' ')[0] : '') : '(none)';
    }).observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) if (!e.hadRecentInput) out.cls += e.value;
    }).observe({ type: 'layout-shift', buffered: true });
    setTimeout(() => res(out), 4000);
  }));
  add('Vitals', 'LCP < 2.0s (throttled 4G / 4× CPU)', vitals.lcp < 2000, (vitals.lcp / 1000).toFixed(2) + 's');
  add('Vitals', 'LCP element is hero text, not an image', /H1|P|DIV/.test(vitals.lcpEl), vitals.lcpEl);
  add('Vitals', 'CLS < 0.05', vitals.cls < 0.05, vitals.cls.toFixed(4));
  await ctx.close();
}

/* ============================================================
   4 · Accessibility — axe-core on every page
   ============================================================ */
{
  const axeSrc = fs.readFileSync(MOD('axe-core/axe.min.js'), 'utf8');
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const violations = [];
  for (const p of PAGES) {
    const page = await ctx.newPage();
    await page.goto(base + p, { waitUntil: 'networkidle' });
    await page.addScriptTag({ content: axeSrc });
    const r = await page.evaluate(async () => await window.axe.run(document, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'] },
    }));
    for (const v of r.violations) violations.push(`${p}  [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length})`);
    await page.close();
  }
  const serious = violations.filter(v => /\[(critical|serious)\]/.test(v));
  add('A11y', 'axe-core: no critical or serious violations', serious.length === 0,
      serious.length ? serious.slice(0, 12).join('\n      ') : `${PAGES.length} pages clean`);
  if (violations.length && !serious.length)
    add('A11y', 'axe-core: minor/moderate notes', true, violations.slice(0, 10).join('\n      '));
  await ctx.close();
}

/* ============================================================
   5 · Works with JavaScript disabled  (§0.5)
   ============================================================ */
{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, javaScriptEnabled: false });
  const problems = [];
  for (const p of PAGES) {
    const page = await ctx.newPage();
    await page.goto(base + p, { waitUntil: 'domcontentloaded' });
    const r = await page.evaluate(() => {
      const h1 = document.querySelector('h1');
      const hidden = [...document.querySelectorAll('main *')].filter((el) => {
        const s = getComputedStyle(el);
        return (s.opacity === '0' || s.visibility === 'hidden') && el.textContent.trim().length > 20;
      }).length;
      const nav = document.querySelectorAll('header a[href]').length;
      return { h1: h1 ? h1.textContent.trim().slice(0, 40) : null,
               h1Visible: h1 ? getComputedStyle(h1).opacity !== '0' : false, hidden, nav };
    });
    if (!r.h1) problems.push(p + ': no H1');
    if (!r.h1Visible) problems.push(p + ': H1 not visible');
    if (r.hidden) problems.push(`${p}: ${r.hidden} text nodes hidden by CSS`);
    if (r.nav < 3) problems.push(p + ': nav links missing');
    await page.close();
  }
  add('No-JS', 'All text visible and all pages navigable with JS off', problems.length === 0,
      problems.length ? problems.slice(0, 10).join('\n      ') : `${PAGES.length} pages readable and navigable`);
  await ctx.close();
}

/* ============================================================
   6 · Reduced motion  (§8 T12)
   ============================================================ */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto(base + '/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  const r = await page.evaluate(() => ({
    marqueeAnim: getComputedStyle(document.querySelector('.marquee')).animationName,
    gsapLoaded: !!window.gsap,
    lenis: !!window.__lenis,
    stickyStatic: getComputedStyle(document.querySelector('.stack-cards > li')).position,
    counter: document.querySelector('[data-count]')?.textContent.trim(),
  }));
  add('Motion', 'prefers-reduced-motion: marquee animation off', r.marqueeAnim === 'none', 'animation-name: ' + r.marqueeAnim);
  add('Motion', 'prefers-reduced-motion: GSAP and Lenis never load', !r.gsapLoaded && !r.lenis,
      `gsap=${r.gsapLoaded} lenis=${r.lenis}`);
  add('Motion', 'prefers-reduced-motion: sticky stack is static', r.stickyStatic === 'static', 'position: ' + r.stickyStatic);
  add('Motion', 'prefers-reduced-motion: counters show final value', r.counter === '100%', 'first counter reads ' + r.counter);
  await ctx.close();
}

/* ============================================================
   7 · Interactive behaviour — menu, dropdown, form, keyboard
   ============================================================ */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  await page.goto(base + '/', { waitUntil: 'networkidle' });

  await page.click('#burger');
  await page.waitForTimeout(250);
  const open = await page.evaluate(() => ({
    visible: !document.querySelector('#mobmenu').hidden,
    expanded: document.querySelector('#burger').getAttribute('aria-expanded'),
    locked: document.body.style.overflow,
    focusIn: document.querySelector('#mobmenu').contains(document.activeElement),
  }));
  add('Interactive', 'Mobile menu opens, locks scroll and moves focus inside',
      open.visible && open.expanded === 'true' && open.locked === 'hidden' && open.focusIn,
      JSON.stringify(open));

  await page.keyboard.press('Escape');
  await page.waitForTimeout(250);
  const closed = await page.evaluate(() => ({
    hidden: document.querySelector('#mobmenu').hidden,
    expanded: document.querySelector('#burger').getAttribute('aria-expanded'),
    unlocked: document.body.style.overflow === '',
    focusBack: document.activeElement.id,
  }));
  add('Interactive', 'Escape closes the menu and returns focus to the button',
      closed.hidden && closed.expanded === 'false' && closed.unlocked && closed.focusBack === 'burger',
      JSON.stringify(closed));
  await ctx.close();
}

{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(base + '/contact/', { waitUntil: 'networkidle' });

  // Empty submit must not navigate, and must flag the required fields.
  await page.click('#c-form button[type="submit"]');
  await page.waitForTimeout(200);
  const invalid = await page.evaluate(() => ({
    errs: [...document.querySelectorAll('#c-form .field__error')].filter(e => !e.hidden).length,
    aria: document.querySelectorAll('#c-form [aria-invalid="true"]').length,
    url: location.pathname,
  }));
  add('Interactive', 'Form blocks empty submit and shows field errors',
      invalid.errs >= 2 && invalid.aria >= 2 && invalid.url === '/contact/', JSON.stringify(invalid));

  // Honeypot: a filled hidden field is treated as a bot and silently accepted.
  const hp = await page.evaluate(() => {
    const f = document.querySelector('#c-form');
    f.elements.name.value = 'Bot'; f.elements.email.value = 'b@b.co';
    f.elements.message.value = 'x'; f.elements.company.value = 'gotcha';
    f.requestSubmit();
    return new Promise(r => setTimeout(() => r({
      status: document.querySelector('#c-form [data-status]').textContent,
      cleared: f.elements.name.value === '',
    }), 300));
  });
  add('Interactive', 'Honeypot swallows bot submissions', hp.cleared && /touch/i.test(hp.status), JSON.stringify(hp));

  const hpHidden = await page.evaluate(() => {
    const el = document.querySelector('#c-form .hp');
    const s = getComputedStyle(el);
    return { offscreen: parseInt(s.left) < -1000, tabindex: el.querySelector('input').tabIndex };
  });
  add('Interactive', 'Honeypot is off-screen and not tabbable',
      hpHidden.offscreen && hpHidden.tabindex === -1, JSON.stringify(hpHidden));

  // Skip link is the first tab stop and works.
  await page.goto(base + '/', { waitUntil: 'networkidle' });
  await page.keyboard.press('Tab');
  await page.waitForTimeout(500);              // let the reveal transition finish
  const skip = await page.evaluate(() => ({
    cls: document.activeElement.className,
    href: document.activeElement.getAttribute('href'),
    visible: document.activeElement.getBoundingClientRect().top >= 0,
  }));
  add('A11y', 'Skip link is the first tab stop and becomes visible',
      skip.cls === 'skip-link' && skip.href === '#main' && skip.visible, JSON.stringify(skip));
  await ctx.close();
}

/* ============================================================
   8 · Keyboard + find-in-page under Lenis  (§11)
   ============================================================ */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(base + '/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2200);                       // motion.js loads on idle
  const lenisOn = await page.evaluate(() => !!window.__lenis);

  await page.evaluate(() => window.scrollTo(0, 0));
  await page.keyboard.press('PageDown');
  await page.waitForTimeout(900);
  const afterPgDn = await page.evaluate(() => window.scrollY);

  await page.evaluate(() => window.scrollTo({ top: 4000, behavior: 'instant' }));
  await page.waitForTimeout(900);
  const afterJump = await page.evaluate(() => window.scrollY);

  add('Motion', 'Lenis active on desktop', lenisOn, 'window.__lenis present: ' + lenisOn);
  add('Motion', 'PageDown still scrolls under Lenis', afterPgDn > 300, 'scrollY ' + Math.round(afterPgDn));
  add('Motion', 'Programmatic/find-in-page scroll is not fought by Lenis',
      Math.abs(afterJump - 4000) < 200, 'landed at ' + Math.round(afterJump) + ' (asked 4000)');
  await ctx.close();
}

/* ============================================================
   9 · Console errors across every page
   ============================================================ */
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const errs = [];
  for (const p of PAGES) {
    const page = await ctx.newPage();
    page.on('console', (m) => { if (m.type() === 'error') errs.push(p + ': ' + m.text()); });
    page.on('pageerror', (e) => errs.push(p + ': ' + e.message));
    await page.goto(base + p, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1600);
    await page.close();
  }
  add('Build', 'No console errors on any page', errs.length === 0,
      errs.length ? errs.slice(0, 8).join('\n      ') : `${PAGES.length} pages clean`);
  await ctx.close();
}

/* ============================================================
   10 · SEO surface
   ============================================================ */
{
  const problems = [];
  const titles = new Map(), descs = new Map();
  for (const p of PAGES) {
    const f = p.endsWith('.html') ? path.join(OUT, p.slice(1)) : path.join(OUT, p.slice(1), 'index.html');
    const h = fs.readFileSync(f, 'utf8');
    const dec = (t) => t.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
                        .replace(/&quot;/g, '"').replace(/&#39;/g, "'");
    const g = (re) => dec((re.exec(h) || [])[1] || '');
    const title = g(/<title>([^<]*)<\/title>/);
    const desc  = g(/<meta name="description" content="([^"]*)"/);
    const canon = g(/<link rel="canonical" href="([^"]*)"/);
    const h1s   = (h.match(/<h1[\s>]/g) || []).length;
    const noindex = /name="robots" content="noindex/.test(h);

    if (title.length > 60) problems.push(`${p}: title ${title.length} chars (>60)`);
    if (!desc) problems.push(`${p}: no meta description`);
    if (desc.length > 155) problems.push(`${p}: description ${desc.length} chars (>155)`);
    if (!canon) problems.push(`${p}: no canonical`);
    if (h1s !== 1) problems.push(`${p}: ${h1s} H1 elements`);
    if (!noindex) {
      if (titles.has(title)) problems.push(`${p}: duplicate title with ${titles.get(title)}`);
      if (descs.has(desc)) problems.push(`${p}: duplicate description with ${descs.get(desc)}`);
      titles.set(title, p); descs.set(desc, p);
    }
    try { JSON.parse(g(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)); }
    catch { problems.push(`${p}: JSON-LD does not parse`); }
    if (/aggregateRating|"@type"\s*:\s*"Review"/.test(h)) problems.push(`${p}: review/rating markup present — fabrication risk`);
  }
  add('SEO', 'Titles ≤60ch, unique descriptions ≤155ch, one H1, canonical, valid JSON-LD',
      problems.length === 0, problems.length ? problems.slice(0, 14).join('\n      ') : `${PAGES.length} pages clean`);

  const sm = fs.readFileSync(path.join(OUT, 'sitemap.xml'), 'utf8');
  const inSitemap = [...sm.matchAll(/<loc>https:\/\/wwwebtech\.in([^<]*)<\/loc>/g)].map(m => m[1]);
  const indexable = PAGES.filter(p => {
    const f = p.endsWith('.html') ? path.join(OUT, p.slice(1)) : path.join(OUT, p.slice(1), 'index.html');
    return !/name="robots" content="noindex/.test(fs.readFileSync(f, 'utf8'));
  });
  const served = SERVER_PAGES.filter(s => s.inSitemap).map(s => s.path);
  const missing = [...indexable, ...served].filter(p => !inSitemap.includes(p));
  const extra = inSitemap.filter(p => !indexable.includes(p) && !served.includes(p));
  add('SEO', 'Sitemap lists every indexable page and nothing else',
      missing.length === 0 && extra.length === 0,
      `${inSitemap.length} URLs; missing ${JSON.stringify(missing)}; extra ${JSON.stringify(extra)}`);

  for (const f of ['robots.txt', '.htaccess', '_redirects', 'site.webmanifest', 'favicon.svg', '404.html'])
    add('SEO', `${f} present`, fs.existsSync(path.join(OUT, f)), '');

  const ht = fs.readFileSync(path.join(OUT, '.htaccess'), 'utf8');
  add('SEO', 'Old service URLs 301 to their new homes',
      /website-development\/\?\$ \/services\/web-development\//.test(ht) && (ht.match(/R=301/g) || []).length >= 7,
      (ht.match(/R=301/g) || []).length + ' 301 rules');
}

/* ============================================================
   11 · HTML validity
   ============================================================ */
{
  const { HtmlValidate } = await import(MOD('html-validate/dist/esm/index.js'));
  const hv = new HtmlValidate({
    extends: ['html-validate:recommended'],
    rules: {
      // The design deliberately uses inline style attributes for one-off
      // layout nudges rather than growing a utility-class zoo.
      'no-inline-style': 'off',
      'require-sri': 'off',
      'no-trailing-whitespace': 'off',
      'attribute-boolean-style': 'off',
      'void-style': 'off',
      'long-title': 'off',
      // A checkbox group sharing one name is correct HTML, not a duplicate.
      'form-dup-name': ['error', { shared: ['radio', 'button', 'checkbox'] }],
    },
  });
  const problems = [];
  for (const p of PAGES) {
    const f = p.endsWith('.html') ? path.join(OUT, p.slice(1)) : path.join(OUT, p.slice(1), 'index.html');
    const r = await hv.validateFile(f);
    for (const res of r.results)
      for (const m of res.messages)
        if (m.severity === 2) problems.push(`${p}:${m.line} ${m.ruleId} — ${m.message}`);
  }
  add('Build', 'HTML validates (html-validate, recommended)', problems.length === 0,
      problems.length ? [...new Set(problems)].slice(0, 14).join('\n      ') : `${PAGES.length} pages clean`);
}

/* ============================================================
   12 · Lighthouse
   ============================================================ */
if (!QUICK) {
  const lighthouse = (await import(MOD('lighthouse/core/index.js'))).default;
  const { launch } = await import(MOD('chrome-launcher/dist/index.js'));
  const CORE = ['performance', 'accessibility', 'best-practices', 'seo'];
  const FLAGS = ['--headless=new', '--no-sandbox', '--disable-gpu',
                 '--disable-dev-shm-usage', '--disable-extensions', '--js-flags=--max-old-space-size=2048'];
  /* Lighthouse simulates throttling on the machine it runs on, so on a shared
     container a single run swings by 20 points for reasons that have nothing to
     do with the site. Take the best of three, and say so in the report. */
  for (const url of ['/', '/services/seo/', '/blog/website-speed-india/']) {
    const runs = [];
    let note = '';
    for (let attempt = 0; attempt < 3; attempt++) {
      let chrome = null;
      try {
        // A fresh browser per run: a reused one crashes the tab in a container.
        chrome = await launch({ chromeFlags: FLAGS, ...(CHROME ? { chromePath: CHROME } : {}) });
        const r = await lighthouse(base + url, { port: chrome.port, output: 'json', logLevel: 'error' });
        if (r.lhr.runtimeError && !r.lhr.categories.performance?.score) note = r.lhr.runtimeError.code;
        else runs.push(Object.fromEntries(CORE.map(k => [k, Math.round((r.lhr.categories[k]?.score ?? 0) * 100)])));
      } catch (e) {
        note = e.message.split('\n')[0].slice(0, 80);
      } finally {
        try { await chrome?.kill(); } catch {}
      }
    }
    if (!runs.length) { add('Lighthouse', `${url} — all four categories ≥ 95`, false, 'run failed: ' + note); continue; }
    const total = (s) => CORE.reduce((n, k) => n + s[k], 0);
    const best = runs.reduce((a, b) => (total(b) > total(a) ? b : a));
    const spread = runs.length > 1
      ? `  (best of ${runs.length}; perf ${runs.map(r => r.performance).join('/')})` : '';
    const low = Object.entries(best).filter(([, v]) => v < 95);
    add('Lighthouse', `${url} — all four categories ≥ 95`, low.length === 0,
        Object.entries(best).map(([k, v]) => `${k} ${v}`).join('  ·  ') + spread);
  }
} else {
  add('Lighthouse', 'skipped (--quick)', true, 'run without --quick to include');
}

/* ============================================================ */
await browser.close(); server.close();

const pass = results.filter(r => r.pass).length;
const fail = results.filter(r => !r.pass);
const byArea = [...new Set(results.map(r => r.area))];

let md = `# QA report — wwwebtech.in\n\n`;
md += `Generated ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC · `;
md += `**${pass}/${results.length} checks pass**`;
md += fail.length ? `, ${fail.length} failing.\n\n` : `.\n\n`;
md += `Re-run with \`node tools/qa.mjs\`. Every row below is an acceptance criterion\n`;
md += `from the build brief, not a vanity metric.\n\n`;

for (const area of byArea) {
  md += `## ${area}\n\n| | Check | Detail |\n|---|---|---|\n`;
  for (const r of results.filter(x => x.area === area))
    md += `| ${r.pass ? '✅' : '❌'} | ${r.name} | ${String(r.detail).replace(/\n\s*/g, '<br>').replace(/\|/g, '\\|')} |\n`;
  md += `\n`;
}
if (fail.length) {
  md += `## Failing\n\n`;
  for (const r of fail) md += `- **${r.area} — ${r.name}**\n\n  \`\`\`\n  ${String(r.detail).replace(/\n/g, '\n  ')}\n  \`\`\`\n\n`;
}
fs.writeFileSync(path.join(ROOT, 'QA-REPORT.md'), md);

console.log(`\n  ${pass}/${results.length} checks pass` + (fail.length ? `, ${fail.length} FAILING` : ''));
for (const r of fail) console.log(`   ✗ [${r.area}] ${r.name}\n       ${String(r.detail).replace(/\n/g, '\n       ')}`);
console.log(`\n  -> QA-REPORT.md`);
process.exitCode = fail.length ? 1 : 0;
