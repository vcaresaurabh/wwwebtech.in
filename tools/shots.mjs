#!/usr/bin/env node
/* ============================================================
   Visual QA. Serves site/ and screenshots pages at three widths.

     node tools/shots.mjs                       # every page, 3 widths
     node tools/shots.mjs / /about/             # just these
     node tools/shots.mjs --w 390 --full /      # one width, full page
     node tools/shots.mjs --motion off /        # prefers-reduced-motion
     node tools/shots.mjs --nojs /              # JavaScript disabled

   Writes PNGs to tools/.shots/ and prints any console errors.
   ============================================================ */
import http from 'node:http';
import zlib from 'node:zlib';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
/* Playwright is a QA-only dependency and deliberately not in package.json.
   `npm i -D playwright && npx playwright install chromium` to enable.
   Resolved lazily: serve() and listPages() are useful without a browser,
   and tools/serve.mjs must run on a machine that has never installed one. */
async function getChromium() {
  for (const p of ['playwright', process.env.PLAYWRIGHT_PATH].filter(Boolean)) {
    try { return (await import(p)).chromium; } catch {}
  }
  console.error('Playwright not found. npm i -D playwright && npx playwright install chromium');
  console.error('(or set PLAYWRIGHT_PATH to an existing install)');
  process.exit(2);
}

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const OUT  = path.join(ROOT, 'site');
const SHOTS= process.env.SHOTS_DIR || path.join(ROOT, 'tools', '.shots');

const MIME = { '.html':'text/html', '.css':'text/css', '.js':'text/javascript',
  '.svg':'image/svg+xml', '.woff2':'font/woff2', '.png':'image/png',
  '.json':'application/json', '.xml':'application/xml', '.txt':'text/plain' };

/* The old -> new URL map, so the dev server 301s exactly like the
   production .htaccess and _redirects do. */
const { REDIRECTS } = await import('../src/data.mjs');
const REDIRECT_MAP = new Map(REDIRECTS.map(([from, to]) => [from.replace(/\/$/, ''), to]));

export function serve(dir = OUT, port = 0) {
  const server = http.createServer((req, res) => {
    let p = decodeURIComponent(req.url.split('?')[0]);

    // Same 301s the live host performs.
    const hit = REDIRECT_MAP.get(p.replace(/\/$/, ''));
    if (hit && hit !== p) { res.writeHead(301, { location: hit }); res.end(); return; }

    /* The analytics beacon fires on every page load and expects the 204 the
       real endpoint returns. Answering 501 here would put a console error on
       every page of every QA run — a preview-server artefact reported as a
       site defect. Nothing is recorded either way. */
    if (p === '/api/hit.php') { res.writeHead(204).end(); return; }

    /* Everything else ending in .php: this is a static file server and cannot
       run PHP. Say so plainly rather than serving the source and letting
       someone think the form works locally. It does work on the real host. */
    if (p.endsWith('.php')) {
      res.writeHead(501, { 'content-type': 'text/plain' });
      res.end('This preview server does not execute PHP.\n' +
              'contact.php runs on the real host (Hostinger, PHP 8.3).\n' +
              'To test it locally: cd site && php -S 127.0.0.1:8099');
      return;
    }

    let f = path.join(dir, p);
    if (!f.startsWith(dir)) { res.writeHead(403).end(); return; }
    try {
      // Directories get a trailing slash, so /about and /about/ never both resolve.
      if (fs.existsSync(f) && fs.statSync(f).isDirectory() && !p.endsWith('/')) {
        res.writeHead(301, { location: p + '/' }); res.end(); return;
      }
      if (fs.existsSync(f) && fs.statSync(f).isDirectory()) f = path.join(f, 'index.html');
      if (!fs.existsSync(f)) {
        const e = path.join(dir, '404.html');
        if (fs.existsSync(e)) { res.writeHead(404, {'content-type':'text/html'}); res.end(fs.readFileSync(e)); return; }
        res.writeHead(404, {'content-type':'text/plain'}); res.end('404'); return;
      }
      const type = MIME[path.extname(f)] || 'application/octet-stream';
      const body = fs.readFileSync(f);
      /* Compress the same types the production .htaccess compresses, so
         Lighthouse and the weight budget measure a realistic transfer
         rather than raw uncompressed bytes. */
      const compressible = /^(text\/|application\/(javascript|json|xml))|image\/svg/.test(type);
      const accepts = String(req.headers['accept-encoding'] || '');
      if (compressible && /\bgzip\b/.test(accepts)) {
        const out = zlib.gzipSync(body, { level: 6 });
        res.writeHead(200, { 'content-type': type, 'content-encoding': 'gzip',
                             'content-length': out.length, 'vary': 'Accept-Encoding' });
        res.end(out);
      } else {
        res.writeHead(200, { 'content-type': type, 'content-length': body.length });
        res.end(body);
      }
    } catch (e) { res.writeHead(500).end(String(e)); }
  });
  return new Promise(r => server.listen(port, () => r({ server, port: server.address().port })));
}

const WIDTHS = { d: 1440, t: 768, m: 390 };

async function main() {
  const argv = process.argv.slice(2);
  const flag = (n, d) => { const i = argv.indexOf('--' + n); return i < 0 ? d : (argv[i + 1]); };
  const has  = (n) => argv.includes('--' + n);
  const only = argv.filter((a, i) => a.startsWith('/') && argv[i - 1] !== '--w');

  const reduce = flag('motion', 'on') === 'off';
  const nojs   = has('nojs');
  const full   = has('full');
  const oneW   = flag('w', null);

  const pages = only.length ? only : listPages();
  const widths = oneW ? { x: +oneW } : WIDTHS;

  fs.mkdirSync(SHOTS, { recursive: true });
  const { server, port } = await serve();
  const chromium = await getChromium();
  const browser = await chromium.launch();

  const errors = [];
  for (const [key, width] of Object.entries(widths)) {
    const ctx = await browser.newContext({
      viewport: { width, height: width < 500 ? 844 : 900 },
      deviceScaleFactor: width < 500 ? 2 : 1,
      isMobile: width < 500,
      hasTouch: width < 500,
      javaScriptEnabled: !nojs,
      reducedMotion: reduce ? 'reduce' : 'no-preference',
  });
    for (const p of pages) {
      const page = await ctx.newPage();
      page.on('console', m => { if (m.type() === 'error') errors.push(`${p} @${width}  ${m.text()}`); });
      page.on('pageerror', e => errors.push(`${p} @${width}  ${e.message}`));
      await page.goto(`http://localhost:${port}${p}`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(reduce || nojs ? 250 : 900);
      const name = (p === '/' ? 'home' : p.replace(/^\/|\/$/g, '').replace(/\//g, '-'))
        + `-${key}${reduce ? '-rm' : ''}${nojs ? '-nojs' : ''}${full ? '-full' : ''}.png`;
      await page.screenshot({ path: path.join(SHOTS, name), fullPage: full });
      await page.close();
    }
    await ctx.close();
  }
  await browser.close(); server.close();
  console.log(`shots -> ${SHOTS}`);
  if (errors.length) { console.log('\nCONSOLE ERRORS:'); errors.forEach(e => console.log('  ' + e)); }
  else console.log('no console errors');
}

export function listPages(dir = OUT, base = '') {
  const out = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (e.isDirectory() && !['assets', 'og'].includes(e.name))
      out.push(...listPages(path.join(dir, e.name), base + '/' + e.name));
    else if (e.name === 'index.html') out.push((base || '') + '/');
    else if (e.name.endsWith('.html')) out.push(base + '/' + e.name);
  }
  return out.sort();
}

if (import.meta.url === `file://${process.argv[1]}`) main();
