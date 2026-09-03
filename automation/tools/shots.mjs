#!/usr/bin/env node
/* ============================================================
   Screenshots of the admin panel, for the phase gates.

     node automation/tools/shots.mjs                    # every page
     node automation/tools/shots.mjs leads lead         # just these

   Signs in with the dev account, captures each page at 1440 and 390,
   and reports any console error it sees on the way. Writes to
   automation/.shots/.
   ============================================================ */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT  = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const SHOTS = path.join(ROOT, '.shots');
const BASE  = process.env.BASE  || 'http://127.0.0.1:8088';
const EMAIL = process.env.EMAIL || 'owner@wwwebtech.in';
const PASS  = process.env.PASS  || 'devpassword123';

const PAGES = {
  dashboard: '/admin/',
  leads:     '/admin/?p=leads&show=all',
  analytics: '/admin/?p=analytics',
  blog:      '/admin/?p=blog',
  seo:       '/admin/?p=seo',
  lead:      '/admin/?p=lead&id=__LEAD__',
  settings:  '/admin/?p=settings',
};

const want = process.argv.slice(2).filter((a) => !a.startsWith('-'));
const list = want.length ? want : Object.keys(PAGES);
const full = process.argv.includes('--full');

fs.mkdirSync(SHOTS, { recursive: true });
/* This container ships a Chrome for Testing build in the Playwright cache
   that does not match the npm package's expected revision. Use it directly
   rather than downloading a second copy. */
const CHROME = process.env.CHROME_PATH
  || [`${process.env.HOME}/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome`]
       .find((p) => fs.existsSync(p));
const browser = await chromium.launch(CHROME ? { executablePath: CHROME } : {});
const errors = [];

/* Sign in once; both viewports reuse the cookie. */
const ctx0 = await browser.newContext();
const p0 = await ctx0.newPage();
await p0.goto(`${BASE}/admin/?p=login`);
await p0.fill('input[name="email"]', EMAIL);
await p0.fill('input[name="password"]', PASS);
await Promise.all([p0.waitForURL(/admin/), p0.click('button[type="submit"]')]);
if (/p=login/.test(p0.url())) {
  console.error('could not sign in — is the dev server up and the password right?');
  process.exit(2);
}
const state = await ctx0.storageState();

/* Pick a real lead id so the detail shot is of actual data. */
await p0.goto(`${BASE}/admin/?p=leads&show=all`);
const href = await p0.getAttribute('a[href*="p=lead&id="]', 'href').catch(() => null);
const leadId = href ? (href.match(/id=(\d+)/) || [])[1] : '1';
await ctx0.close();

for (const [key, width] of [['d', 1440], ['m', 390]]) {
  const ctx = await browser.newContext({
    storageState: state,
    viewport: { width, height: width < 500 ? 844 : 950 },
    deviceScaleFactor: width < 500 ? 2 : 1,
    isMobile: width < 500,
    hasTouch: width < 500,
  });
  for (const name of list) {
    const url = (PAGES[name] || name).replace('__LEAD__', leadId);
    const page = await ctx.newPage();
    page.on('console', (m) => { if (m.type() === 'error') errors.push(`${name} @${width}  ${m.text()}`); });
    page.on('pageerror', (e) => errors.push(`${name} @${width}  ${e.message}`));
    const res = await page.goto(BASE + url, { waitUntil: 'networkidle' });
    if (res && res.status() >= 400) errors.push(`${name} @${width}  HTTP ${res.status()}`);
    /* The panel must never show a raw failure. Catch it here rather than
       letting a screenshot of an error page look like a passing gate. */
    const body = await page.textContent('body');
    if (/Something went wrong|Fatal error|Warning:/i.test(body || '')) {
      errors.push(`${name} @${width}  page rendered an error message`);
    }
    await page.screenshot({ path: path.join(SHOTS, `${process.env.PHASE || 'p2'}-${name}-${key}.png`), fullPage: full });
    await page.close();
  }
  await ctx.close();
}
await browser.close();

console.log(`shots -> ${SHOTS}`);
if (errors.length) { console.log('\nPROBLEMS:'); errors.forEach((e) => console.log('  ' + e)); process.exit(1); }
console.log('no console errors, no error pages');
