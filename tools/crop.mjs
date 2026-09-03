#!/usr/bin/env node
/* Screenshot individual elements, for close reading during design QA.
   node tools/crop.mjs <path> <width> <selector> [selector...]        */
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { serve } from './shots.mjs';

const PW = process.env.PLAYWRIGHT_PATH || 'playwright';
const { chromium } = await import(PW);

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const SHOTS = process.env.SHOTS_DIR || path.join(ROOT, 'tools', '.shots');
fs.mkdirSync(SHOTS, { recursive: true });

const [pagePath, width, ...sels] = process.argv.slice(2);
const { server, port } = await serve();
const b = await chromium.launch();
const ctx = await b.newContext({
  viewport: { width: +width, height: 900 },
  deviceScaleFactor: +width < 500 ? 2 : 1,
  isMobile: +width < 500, hasTouch: +width < 500,
});
const p = await ctx.newPage();
await p.goto(`http://localhost:${port}${pagePath}`, { waitUntil: 'networkidle' });
await p.waitForTimeout(700);

for (const s of sels) {
  const el = p.locator(s).first();
  if (!(await el.count())) { console.log('  miss ' + s); continue; }
  const name = s.replace(/[^a-z0-9]+/gi, '_').slice(0, 40) + `-${width}.png`;
  await el.scrollIntoViewIfNeeded();
  await p.waitForTimeout(350);
  await el.screenshot({ path: path.join(SHOTS, name) });
  console.log('  ' + name);
}
await b.close(); server.close();
