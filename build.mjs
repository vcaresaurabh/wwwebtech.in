#!/usr/bin/env node
/* ============================================================
   Wwwebtech static builder.  Zero dependencies.

     node build.mjs

   Reads src/, writes site/. The site/ folder is the deploy artifact:
   plain HTML/CSS/JS, uploadable to any host as-is. Nothing in site/
   needs Node at runtime — this script only assembles it.
   ============================================================ */
import fs from 'node:fs';
import crypto from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { SITE, REDIRECTS, SERVER_PAGES } from './src/data.mjs';
import { layout } from './src/layout.mjs';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const SRC  = path.join(ROOT, 'src');
const OUT  = path.join(ROOT, 'site');
const CSSD = path.join(OUT, 'assets', 'css');

const read  = (p) => fs.readFileSync(p, 'utf8');
const write = (p, s) => { fs.mkdirSync(path.dirname(p), { recursive: true }); fs.writeFileSync(p, s); };

/* --- Conservative CSS minifier ------------------------------------
   Protects url(), quoted strings and data: URIs before touching
   whitespace, so the inline SVG chevrons survive intact. */
function minifyCss(css) {
  // Comments go FIRST. A lone apostrophe inside a comment ("the site's...")
  // otherwise reads as the start of a string literal and swallows whole files.
  css = css.replace(/\/\*[\s\S]*?\*\//g, '');

  const keep = [];
  const TOK = (i) => '«keep' + i + '»';   // cannot occur in our source CSS
  css = css.replace(/url\([^)]*\)|"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/g,
    (m) => TOK(keep.push(m) - 1));

  css = css
    .replace(/\s+/g, ' ')
    // NB: ':' is deliberately NOT in this character class. Collapsing the space
    // *before* a colon would turn the descendant selector `.band :focus-visible`
    // into the compound `.band:focus-visible` and silently change what it matches.
    .replace(/\s*([{};,>~])\s*/g, '$1')
    .replace(/:\s+/g, ':')
    .replace(/;\}/g, '}')
    .trim();

  return css.replace(/«keep(\d+)»/g, (_, i) => keep[+i]);
}

/* All CSS becomes one inline blob. Font URLs go absolute because the
   blob lives in the document, not in assets/css/. */
function buildCss() {
  const order = ['tokens.css', 'fonts.css', 'base.css', 'components.css', 'sections.css'];
  const raw = order.map(f => read(path.join(CSSD, f))).join('\n');
  return minifyCss(raw.replace(/url\('\.\.\/fonts\//g, "url('/assets/fonts/"));
}

/* The wordmark outlines, cut once and reused via <use> on every page. */
function wordPaths() {
  const svg = read(path.join(ROOT, 'public/assets/logos/wwwebtech.svg'));
  const d = /\sd="([^"]+)"/.exec(svg)[1];
  const subs = d.split('M').filter(s => s.trim()).map(s => 'M' + s);
  return { word: subs.slice(0, 7).join(' ').trim(), tail: subs.slice(7).join(' ').trim() };
}

/* --- Escape stray ampersands --------------------------------------
   Copy like "Content & on-page SEO" is written naturally in the page
   modules. A bare "&" in text is sloppy HTML (and html-validate flags
   it), so encode any that is not already the start of an entity. The
   contents of <script> and <style> are left completely alone — the
   JSON-LD in particular would be corrupted by escaping. */
function escapeStrayAmps(html) {
  const parts = html.split(/(<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>)/i);
  return parts.map((chunk, i) =>
    i % 2 ? chunk : chunk.replace(/&(?![a-zA-Z][a-zA-Z0-9]{1,9};|#\d{1,6};|#x[0-9a-fA-F]{1,6};)/g, '&amp;')
  ).join('');
}

/* --- Collect pages ------------------------------------------------ */
async function loadPages() {
  const dir = path.join(SRC, 'pages');
  const files = fs.readdirSync(dir).filter(f => f.endsWith('.mjs')).sort();
  const pages = [];
  for (const f of files) {
    const mod = await import(path.join(dir, f) + '?v=' + Date.now());
    const list = Array.isArray(mod.pages) ? mod.pages : [mod.page];
    for (const m of list) if (m) pages.push({ file: f, ...m });
  }
  return pages;
}

const outPathFor = (p) =>
  p.endsWith('.html') ? path.join(OUT, p.replace(/^\//, ''))
                      : path.join(OUT, p.replace(/^\//, ''), 'index.html');

/* --- Link checker -------------------------------------------------
   Crawls the built folder. Any internal href that does not resolve to a
   file on disk (or to an id on the target page) fails the build. */
function checkLinks(built) {
  const known = new Set([...built.map(b => b.path), ...SERVER_PAGES.map(p => p.path)]);
  const ids = new Map(built.map(b => [b.path, new Set(
    [...b.html.matchAll(/\sid="([^"]+)"/g)].map(m => m[1]))]));
  const problems = [];
  for (const b of built) {
    for (const m of b.html.matchAll(/\shref="([^"]+)"/g)) {
      const href = m[1];
      if (/^(https?:|mailto:|tel:|data:|#i-|#logo-)/.test(href)) continue;
      if (href.startsWith('#')) {
        if (href.length > 1 && !ids.get(b.path)?.has(href.slice(1)))
          problems.push(b.path + ' -> ' + href + '  (no such id on this page)');
        continue;
      }
      const [target, hash] = href.split('#');
      const clean = target || b.path;
      const onDisk = fs.existsSync(path.join(OUT, clean.replace(/^\//, '')));
      if (!known.has(clean) && !onDisk) {
        problems.push(b.path + ' -> ' + href + '  (no such page)');
        continue;
      }
      if (hash && known.has(clean) && !ids.get(clean)?.has(hash))
        problems.push(b.path + ' -> ' + href + '  (no such id on ' + clean + ')');
    }
  }
  return problems;
}

/* --- Generated files ---------------------------------------------- */
function sitemap(built) {
  const today = new Date().toISOString().slice(0, 10);
  const rows = built.filter(b => !b.noindex).map(b => {
    const lastmod = b.lastmod || today;
    const pr = b.path === '/' ? '1.0'
             : b.path.startsWith('/services') ? '0.9'
             : (b.path.startsWith('/blog/') && b.path !== '/blog/') ? '0.6' : '0.7';
    return '  <url><loc>' + SITE.origin + b.path + '</loc><lastmod>' + lastmod +
           '</lastmod><priority>' + pr + '</priority></url>';
  });
  for (const p of SERVER_PAGES.filter(p => p.inSitemap)) {
    rows.push('  <url><loc>' + SITE.origin + p.path + '</loc><lastmod>' + today +
              '</lastmod><priority>' + p.priority + '</priority></url>');
  }
  /* The automation layer adds server-generated blog posts between these
     markers. It edits only what is between them, so a rebuild here never
     loses a generated post and a generated post never rewrites this file. */
  return '<?xml version="1.0" encoding="UTF-8"?>\n' +
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + rows.join('\n') +
    '\n  <!--GENERATED_POSTS_START-->\n  <!--GENERATED_POSTS_END-->\n</urlset>\n';
}

const robots = () => [
  '# Everything is crawlable, including the AI crawlers - being cited by them',
  '# is the point of the GEO work we sell.',
  'User-agent: *',
  'Allow: /',
  '',
  'Sitemap: ' + SITE.origin + '/sitemap.xml',
  '',
].join('\n');

const htaccess = () => [
  '# Apache / LiteSpeed (Hostinger, cPanel shared hosting).',
  '# Upload alongside index.html at the web root.',
  'Options -Indexes',
  'DirectoryIndex index.html',
  '',
  '<IfModule mod_rewrite.c>',
  '  RewriteEngine On',
  '',
  '  # Force https first, so redirected old URLs land on the secure host.',
  '  RewriteCond %{HTTPS} off',
  '  RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]',
  '',
  '  # --- Old URLs from the previous site. 301, never 302. (brief section 3)',
  ...REDIRECTS.map(([from, to]) =>
    '  RewriteRule ^' + from.replace(/^\//, '') + '/?$ ' + to + ' [R=301,L]'),
  '',
  '  # Trailing slash for real directories, so /about and /about/ never both rank.',
  '  RewriteCond %{REQUEST_FILENAME} -d',
  '  RewriteCond %{REQUEST_URI} !/$',
  '  RewriteRule ^(.*)$ /$1/ [R=301,L]',
  '',
  '  # ---------------------------------------------------------------',
  '  # AI crawler logging.  DELETE THIS BLOCK TO SWITCH IT OFF.',
  '  #',
  '  # GPTBot, ClaudeBot, PerplexityBot and Google-Extended are how this',
  '  # site reaches AI answers, and none of them run JavaScript, so',
  '  # assets/js/wa.js never sees them. These rules route ONLY those',
  '  # crawlers through serve.php, which logs the visit and then serves',
  '  # the same file. Human traffic is untouched and never runs PHP.',
  '  #',
  '  # Needs the automation layer deployed (serve.php in the web root).',
  '  # Without it the rule simply never matches a file that exists, and',
  '  # the last condition below makes the whole block a no-op.',
  '  # ---------------------------------------------------------------',
  '  RewriteCond %{HTTP_USER_AGENT} (GPTBot|ChatGPT-User|OAI-SearchBot|ClaudeBot|Claude-Web|anthropic-ai|PerplexityBot|Perplexity-User|Google-Extended|Applebot-Extended|meta-externalagent|CCBot|Bytespider|Amazonbot) [NC]',
  '  RewriteCond %{DOCUMENT_ROOT}/serve.php -f',
  '  RewriteCond %{REQUEST_URI} !^/(api|admin)/',
  '  RewriteCond %{REQUEST_URI} \\.(html?)$ [OR]',
  '  RewriteCond %{REQUEST_URI} /$',
  '  RewriteRule ^ /serve.php [L]',
  '</IfModule>',
  '',
  'ErrorDocument 404 /404.html',
  '',
  '<IfModule mod_deflate.c>',
  '  AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/javascript application/json image/svg+xml',
  '</IfModule>',
  '',
  '<IfModule mod_expires.c>',
  '  ExpiresActive On',
  '  ExpiresByType text/css               "access plus 1 year"',
  '  ExpiresByType application/javascript "access plus 1 year"',
  '  ExpiresByType font/woff2             "access plus 1 year"',
  '  ExpiresByType image/svg+xml          "access plus 1 month"',
  '  ExpiresByType image/png              "access plus 1 month"',
  '  ExpiresByType text/html              "access plus 0 seconds"',
  '</IfModule>',
  '',
  '<IfModule mod_headers.c>',
  '  Header set X-Content-Type-Options "nosniff"',
  '  Header set Referrer-Policy "strict-origin-when-cross-origin"',
  '  # Tell browsers to use https for a year without asking first. Deliberately',
  '  # WITHOUT includeSubDomains: flow.wwwebtech.in shares this domain and is not',
  '  # this repo\'s to vouch for. Add it once every subdomain is known-https.',
  '  Header always set Strict-Transport-Security "max-age=31536000"',
  '</IfModule>',
  '',
  '# The private folder and the path finder are code, never content.',
  '<FilesMatch "^(_wwt|config|bootstrap)\\.php$">',
  '  Require all denied',
  '</FilesMatch>',
  '',
].join('\n');

const netlifyRedirects = () => [
  '# Netlify / Cloudflare Pages. Same map as .htaccess.',
  ...REDIRECTS.map(([from, to]) => from + '   ' + to + '   301!'),
  '/*   /404.html   404',
  '',
].join('\n');

const manifest = () => JSON.stringify({
  name: 'Wwwebtech', short_name: 'Wwwebtech',
  description: SITE.tagline,
  start_url: '/', display: 'minimal-ui',
  background_color: '#FAF8F4', theme_color: '#FAF8F4',
  icons: [
    { src: '/favicon.svg',  sizes: 'any',     type: 'image/svg+xml' },
    { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
    { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
    { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
  ],
}, null, 2) + '\n';

/* --- Asset fingerprints --------------------------------------------
   assets/js/ is served with a one-week max-age, so without a version in
   the URL a returning visitor keeps whatever they cached — which means a
   fix to the contact form would not reach them for seven days. The hash
   is of the file's own bytes, so the URL only changes when the file does. */
function assetVersion(rel) {
  const f = path.join(OUT, rel.replace(/^\//, ''));
  if (!fs.existsSync(f)) return '';
  return crypto.createHash('sha256').update(fs.readFileSync(f)).digest('hex').slice(0, 8);
}
function fingerprint(html) {
  return html.replace(/(src|href)="(\/assets\/(?:js|css)\/[^"?#]+)"/g, (m, attr, url) => {
    const v = assetVersion(url);
    return v ? `${attr}="${url}?v=${v}"` : m;
  });
}

/* --- Go ------------------------------------------------------------ */
const css = buildCss();
const wp  = wordPaths();
const pages = await loadPages();
const built = [];

for (const p of pages) {
  const body = typeof p.render === 'function' ? await p.render() : p.body;
  const html = fingerprint(escapeStrayAmps(layout({ ...p, body, css, wordPaths: wp })));
  built.push({ ...p, html });
  write(outPathFor(p.path), html);
}

/* --- Template for server-generated posts ---------------------------
   The automation layer writes new blog posts on the server, where Node is
   not available. Rather than reimplement this page's markup in PHP — two
   copies of a design that would drift apart on the first change here — the
   build renders the real post page once with placeholders in place of the
   content and hands PHP the result. Change the design in src/ and the
   generated posts follow automatically on the next build. */
await writePostTemplate();

async function writePostTemplate() {
  const tplDir = path.join(ROOT, 'automation', 'private', 'templates');
  if (!fs.existsSync(path.dirname(tplDir))) return;   // automation layer not present

  const { POSTS } = await import('./src/data.mjs');
  const { postPage } = await import('./src/partials/post.mjs');

  const SENTINEL = {
    slug: '__TEMPLATE__', title: '{{TITLE}}', dek: '{{DEK}}',
    date: '{{DATE_ISO}}', read: '{{READ}}', service: '/services/seo/',
  };
  POSTS.push(SENTINEL);
  try {
    const page = postPage({
      slug: '__TEMPLATE__',
      body: '{{BODY}}',
      tocHtml: '{{TOC}}',
      links: ['techseo', 'content'],
      caption: '',
    });
    const body = await page.render();
    let html = fingerprint(escapeStrayAmps(layout({ ...page, body, css, wordPaths: wp })));

    /* fmtDate() has already turned the sentinel into something unusable, and
       the canonical/OG URLs carry the sentinel slug. Normalise them all to
       placeholders the PHP side fills in. */
    html = html
      .replaceAll('/blog/__TEMPLATE__/', '/blog/{{SLUG}}/')
      .replace(/<time datetime="[^"]*">[^<]*<\/time>/, '<time datetime="{{DATE_ISO}}">{{DATE_HUMAN}}</time>')
      .replaceAll('/og/blog-__TEMPLATE__.png', '/og/blog.png');

    write(path.join(tplDir, 'post.html'), html);

    /* The teaser card, from the same source as every other card on the
       site, so a generated post's card and a hand-written one are the
       same markup. */
    const { postCard } = await import('./src/partials/blocks.mjs');
    write(path.join(tplDir, 'teaser.html'), postCard({
      slug: '{{SLUG}}', title: '{{TITLE}}', dek: '{{DEK}}',
      date: '{{DATE_ISO}}', read: '{{READ}}',
    }).replace(/<time datetime="[^"]*">[^<]*<\/time>/,
               '<time datetime="{{DATE_ISO}}">{{DATE_HUMAN}}</time>').trim() + '\n');

    console.log('  template  automation/private/templates/post.html + teaser.html');
  } finally {
    POSTS.pop();
  }
}

write(path.join(OUT, 'sitemap.xml'), sitemap(built));
write(path.join(OUT, 'robots.txt'), robots());
write(path.join(OUT, '.htaccess'), htaccess());
write(path.join(OUT, '_redirects'), netlifyRedirects());
write(path.join(OUT, 'site.webmanifest'), manifest());

/* A SERVER_PAGES entry whose controller has been moved or deleted turns a
   real 404 into a link the checker waves through. Verify the file. */
for (const sp of SERVER_PAGES) {
  if (!fs.existsSync(path.join(ROOT, sp.servedBy))) {
    console.error('\n  FAIL  ' + sp.path + ' is declared as server-rendered, but '
                + sp.servedBy + ' does not exist.');
    process.exit(1);
  }
}

const problems = checkLinks(built);
const totalBytes = built.reduce((n, b) => n + Buffer.byteLength(b.html), 0);

console.log('\n  built ' + built.length + ' pages   inline css ' + (css.length / 1024).toFixed(1) +
            'K   html ' + (totalBytes / 1024).toFixed(0) + 'K total');
for (const b of built) {
  console.log('    ' + (Buffer.byteLength(b.html) / 1024).toFixed(1).padStart(6) + 'K  ' +
              b.path + (b.noindex ? '   (noindex)' : ''));
}
if (problems.length) {
  console.error('\n  FAIL - ' + problems.length + ' broken link(s):');
  problems.forEach(p => console.error('      ' + p));
  process.exitCode = 1;
} else {
  console.log('\n  OK - every internal link and anchor resolves');
}
