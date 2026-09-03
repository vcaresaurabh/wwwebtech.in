#!/usr/bin/env node
/* ============================================================
   Local preview server for the built site.

     node tools/serve.mjs            # http://localhost:8080
     node tools/serve.mjs 3000       # a different port

   It behaves like the production host: gzip on the same file types
   the .htaccess compresses, a real 404 page with a real 404 status,
   trailing-slash redirects, and the 301s from the old site's URLs.

   It serves site/ — so run `node build.mjs` first if you have just
   changed anything in src/.
   ============================================================ */
import { serve, listPages } from './shots.mjs';

const port = Number(process.argv[2]) || 8080;

try {
  const { port: actual } = await serve(undefined, port);
  const pages = listPages();
  console.log(`\n  Wwwebtech — serving site/ on http://localhost:${actual}\n`);
  for (const p of pages) console.log(`    http://localhost:${actual}${p}`);
  console.log(`\n  ${pages.length} pages. Ctrl+C to stop.\n`);
} catch (e) {
  if (e.code === 'EADDRINUSE') {
    console.error(`\n  Port ${port} is already in use. Try: node tools/serve.mjs ${port + 1}\n`);
    process.exit(1);
  }
  throw e;
}
