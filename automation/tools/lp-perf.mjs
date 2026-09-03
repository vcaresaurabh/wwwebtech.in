#!/usr/bin/env node
/* ============================================================
   Landing page performance gate (§2.7, §12.1).

     node automation/tools/lp-perf.mjs custom-crm [more slugs...]

   Runs Lighthouse mobile against each page and checks the budget the
   brief sets: performance >= 95, LCP < 2.0s, CLS < 0.05, INP/TBT sane.

   PHP's built-in server does not compress, and production (LiteSpeed)
   does. Measuring against the dev server directly would score the site
   on bytes it never actually sends, so this puts a small gzipping proxy
   in front — the same reasoning tools/shots.mjs already documents for
   the static site.
   ============================================================ */
import http from 'node:http';
import zlib from 'node:zlib';
import { launch } from 'chrome-launcher';
import lighthouse from 'lighthouse';

const UPSTREAM = process.env.UPSTREAM || 'http://127.0.0.1:8088';
const slugs = process.argv.slice(2).filter((a) => !a.startsWith('-'));
if (!slugs.length) { console.error('usage: lp-perf.mjs <slug> [slug...]'); process.exit(2); }

/* Compress exactly what the production .htaccess compresses. */
const COMPRESSIBLE = /^(text\/|application\/(javascript|json|xml))|image\/svg/;

const proxy = http.createServer((req, res) => {
  const url = new URL(req.url, UPSTREAM);
  const r = http.request(url, { method: req.method, headers: { ...req.headers, host: url.host } }, (up) => {
    const chunks = [];
    up.on('data', (c) => chunks.push(c));
    up.on('end', () => {
      const body = Buffer.concat(chunks);
      const type = String(up.headers['content-type'] || '');
      const accepts = String(req.headers['accept-encoding'] || '');
      const headers = { ...up.headers };
      delete headers['content-length'];
      delete headers['content-encoding'];

      if (COMPRESSIBLE.test(type) && /\bgzip\b/.test(accepts)) {
        const out = zlib.gzipSync(body, { level: 6 });
        res.writeHead(up.statusCode || 200,
          { ...headers, 'content-encoding': 'gzip', 'content-length': out.length, vary: 'Accept-Encoding' });
        res.end(out);
      } else {
        res.writeHead(up.statusCode || 200, { ...headers, 'content-length': body.length });
        res.end(body);
      }
    });
  });
  r.on('error', () => { res.writeHead(502).end('upstream error'); });
  req.pipe(r);
});

const port = await new Promise((r) => proxy.listen(0, () => r(proxy.address().port)));
const chrome = await launch({
  chromeFlags: ['--headless=new', '--no-sandbox', '--disable-gpu'],
  chromePath: process.env.CHROME_PATH
    || `${process.env.HOME}/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome`,
});

const BUDGET = { perf: 95, lcp: 2000, cls: 0.05, tbt: 200 };
let failed = 0;

/* Back-to-back audits against one dev server contend for it, and a page that
   scores 100 alone comes back 92 in a batch — the robots.txt fetch times out
   and `is-crawlable` fails. A short settle between pages makes the gate
   measure the page rather than the harness. */
const settle = (ms) => new Promise((r) => setTimeout(r, ms));

for (const [i, slug] of slugs.entries()) {
  if (i > 0) await settle(1500);
  /* The audit tool is a public page under the same budget, but it is not a
     landing page slug — it has its own controller. */
  const url = slug === 'tools/free-website-audit'
    ? `http://127.0.0.1:${port}/tools/index.php`
    : `http://127.0.0.1:${port}/lp/index.php?p=${encodeURIComponent(slug)}`;
  /* One retry on a result that looks like harness noise rather than a page
     fault: a zero score means nothing loaded at all. */
  let lhr;
  for (let attempt = 0; attempt < 2; attempt++) {
    ({ lhr } = await lighthouse(url, { port: chrome.port, output: 'json', logLevel: 'error' },
      { extends: 'lighthouse:default',
        settings: { onlyCategories: ['performance', 'accessibility', 'seo', 'best-practices'] } }));
    if ((lhr.categories.performance.score ?? 0) > 0) break;
    await settle(2000);
  }

  const perf = Math.round((lhr.categories.performance.score ?? 0) * 100);
  const a11y = Math.round((lhr.categories.accessibility.score ?? 0) * 100);
  const seo  = Math.round((lhr.categories.seo.score ?? 0) * 100);
  const bp   = Math.round((lhr.categories['best-practices'].score ?? 0) * 100);
  /* An audit can come back without a numeric value — a run that errored, or a
     metric Lighthouse could not compute. Treat that as unknown and say so,
     rather than crashing the whole sweep on the fourth page of seven. */
  const num = (id) => {
    const v = lhr.audits[id]?.numericValue;
    return (typeof v === 'number' && isFinite(v)) ? v : null;
  };
  const lcp = num('largest-contentful-paint');
  const cls = num('cumulative-layout-shift');
  const tbt = num('total-blocking-time');
  const lcpEl = lhr.audits['largest-contentful-paint-element']?.details?.items?.[0]?.items?.[0]?.node?.snippet
             || lhr.audits['largest-contentful-paint-element']?.details?.items?.[0]?.node?.snippet || '?';
  const show = (v, unit) => v === null ? 'n/a' : (unit === 'ms' ? Math.round(v) + 'ms' : Number(v.toFixed(3)));

  const bad = [];
  if (perf < BUDGET.perf) bad.push(`performance ${perf} < ${BUDGET.perf}`);
  if (lcp !== null && lcp > BUDGET.lcp) bad.push(`LCP ${Math.round(lcp)}ms > ${BUDGET.lcp}ms`);
  if (cls !== null && cls > BUDGET.cls) bad.push(`CLS ${cls.toFixed(3)} > ${BUDGET.cls}`);
  if (tbt !== null && tbt > BUDGET.tbt) bad.push(`TBT ${Math.round(tbt)}ms > ${BUDGET.tbt}ms`);
  if (lcp === null) bad.push('LCP could not be measured');
  /* SEO and accessibility are budgets too — a landing page that scores 92 on
     SEO has something specific wrong with its head, and silence about it is
     how that ships. */
  if (a11y < 100) bad.push(`accessibility ${a11y} < 100`);
  if (seo  < 100) bad.push(`seo ${seo} < 100`);
  if (bad.length) failed++;

  console.log(`\n  ${slug.includes('/') ? '/' + slug + '/' : '/lp/' + slug + '/'}`);
  console.log(`    performance ${perf}   accessibility ${a11y}   best-practices ${bp}   seo ${seo}`);
  console.log(`    LCP ${show(lcp, 'ms')}   CLS ${show(cls)}   TBT ${show(tbt, 'ms')}`);
  console.log(`    LCP element: ${String(lcpEl).slice(0, 78)}`);
  if (bad.length) {
    console.log(`    FAIL — ${bad.join('; ')}`);
    /* Name the failing audits, so a score is actionable rather than a number. */
    for (const [id, a] of Object.entries(lhr.audits)) {
      if (a.score !== null && a.score < 1 && a.scoreDisplayMode === 'binary'
          && (lhr.categories.seo.auditRefs.some((r) => r.id === id)
           || lhr.categories.accessibility.auditRefs.some((r) => r.id === id))) {
        console.log(`      · ${id}: ${a.title}`);
      }
    }
  } else {
    console.log('    within budget');
  }
}

await chrome.kill();
proxy.close();
console.log(failed ? `\n  ${failed} page(s) outside budget\n` : '\n  all pages within budget\n');
process.exit(failed ? 1 : 0);
