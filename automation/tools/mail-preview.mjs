#!/usr/bin/env node
/* ============================================================
   Render a captured email so it can actually be looked at.

     node automation/tools/mail-preview.mjs            # newest message
     node automation/tools/mail-preview.mjs --all      # every message

   Reads the .eml files the SMTP sink wrote, pulls out the text/html
   part, and screenshots it at 680px — roughly a desktop mail client's
   reading pane. Writes automation/.shots/mail-*.png.
   ============================================================ */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT  = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const MAIL  = path.join(ROOT, '.dev', 'mail');
const SHOTS = path.join(ROOT, '.shots');

/** Decode an RFC 2047 encoded-word header ("=?utf-8?Q?...?="). */
function decodeHeader(v) {
  return v.replace(/=\?([^?]+)\?([QqBb])\?([^?]*)\?=/g, (_, cs, enc, txt) => {
    const bytes = enc.toUpperCase() === 'B'
      ? Buffer.from(txt, 'base64')
      : Buffer.from(txt.replace(/_/g, ' ').replace(/=([0-9A-F]{2})/gi,
          (_, h) => String.fromCharCode(parseInt(h, 16))), 'binary');
    return new TextDecoder(cs).decode(bytes);
  });
}

/** Split headers from body, unfolding continuation lines. */
function parse(raw) {
  const [head, ...rest] = raw.split(/\n\r?\n/);
  const body = rest.join('\n\n');
  const headers = {};
  head.replace(/\n[ \t]+/g, ' ').split('\n').forEach((line) => {
    const i = line.indexOf(':');
    if (i > 0) headers[line.slice(0, i).toLowerCase()] = line.slice(i + 1).trim();
  });
  return { headers, body };
}

/** Pull the text/html alternative out of a multipart body. */
function htmlPart(headers, body) {
  const ct = headers['content-type'] || '';
  const boundary = (ct.match(/boundary="?([^";]+)"?/) || [])[1];
  const decode = (part, enc) => {
    if (/base64/i.test(enc)) return Buffer.from(part.replace(/\s+/g, ''), 'base64').toString('utf8');
    if (/quoted-printable/i.test(enc)) {
      return part.replace(/=\r?\n/g, '')
                 .replace(/=([0-9A-F]{2})/gi, (_, h) => String.fromCharCode(parseInt(h, 16)));
    }
    return part;
  };
  if (!boundary) return /html/i.test(ct) ? decode(body, headers['content-transfer-encoding']) : null;
  for (const chunk of body.split('--' + boundary)) {
    const { headers: h, body: b } = parse(chunk.replace(/^\r?\n/, ''));
    if (/text\/html/i.test(h['content-type'] || '')) {
      return Buffer.from(decode(b, h['content-transfer-encoding']), 'binary')
        .toString('binary') === b ? decode(b, h['content-transfer-encoding'])
                                  : decode(b, h['content-transfer-encoding']);
    }
  }
  return null;
}

const files = fs.existsSync(MAIL)
  ? fs.readdirSync(MAIL).filter((f) => f.endsWith('.eml')).sort()
  : [];
if (!files.length) { console.error(`no messages in ${MAIL} — is the sink running?`); process.exit(2); }

const pick = process.argv.includes('--all') ? files : [files[files.length - 1]];
fs.mkdirSync(SHOTS, { recursive: true });

const CHROME = process.env.CHROME_PATH
  || [`${process.env.HOME}/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome`]
       .find((p) => fs.existsSync(p));
const browser = await chromium.launch(CHROME ? { executablePath: CHROME } : {});
const ctx = await browser.newContext({ viewport: { width: 680, height: 900 } });

let n = 0;
for (const f of pick) {
  const raw = fs.readFileSync(path.join(MAIL, f), 'utf8');
  const { headers, body } = parse(raw);
  const html = htmlPart(headers, body);
  const subject = decodeHeader(headers.subject || '(no subject)');
  if (!html) { console.log(`  skipped ${f} — no HTML part`); continue; }

  const page = await ctx.newPage();
  await page.setContent(html, { waitUntil: 'load' });
  const slug = subject.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40);
  const out = path.join(SHOTS, `mail-${slug || ++n}.png`);
  await page.screenshot({ path: out, fullPage: true });
  await page.close();
  console.log(`  ${subject}`);
  console.log(`    to ${headers.to}   reply-to ${decodeHeader(headers['reply-to'] || '—')}`);
  console.log(`    -> ${path.relative(ROOT, out)}`);
}
await browser.close();
