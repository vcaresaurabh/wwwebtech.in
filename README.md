# wwwebtech.in

The Wwwebtech website. Plain HTML, CSS and JavaScript — no framework, no database,
no server-side code. The whole live site is the **`site/`** folder.

- **To put it live:** upload the contents of `site/` to your web root. That's it.
- **To change the words or add a page:** edit `src/`, run `node build.mjs`, upload `site/` again.

Everything below is written to be followed by someone who is not a developer.
Where you need to make a decision, it says so.

---

## 1. What is in this repository

| Folder | What it is |
|---|---|
| **`site/`** | **The website.** This is the folder you upload. Everything in it is a finished file — no build step runs on the server. |
| `src/` | The source the site is built from: page content, the shared header/footer, the design tokens. |
| `tools/` | Scripts we used to build fonts, logos, social images, screenshots and the QA report. Not needed to run the site. |
| `build.mjs` | Turns `src/` into `site/`. About 250 lines, no dependencies. |
| `QA-REPORT.md` | The results of the last quality check. Regenerate with `node tools/qa.mjs`. |

The previous Laravel application is still in this repository (`app/`, `resources/`,
`routes/`, `public/`) and in the git history. It is not used by the new site and can
be removed once you're happy. Its original README is kept as `README.laravel.md`.

---

## 2. Putting the site live

### Hostinger, cPanel, or any normal shared hosting

1. Open File Manager (or FTP) and go to `public_html`.
2. Delete or move aside what's there now.
3. Upload **everything inside `site/`** — not the `site` folder itself, its *contents*.
   The file `index.html` must end up directly in `public_html`.
4. Make sure the hidden file **`.htaccess`** came across. File managers hide files
   starting with a dot; switch on "show hidden files" and check. It handles the
   redirects from the old URLs, the 404 page, compression and caching.

### Netlify or Cloudflare Pages

Drag the `site/` folder onto the deploy area, or point the project at this repository
with build command `node build.mjs` and publish directory `site`. The `_redirects`
file is already there and does the same job as `.htaccess`.

### One thing to check after uploading

Visit `https://wwwebtech.in/a-page-that-does-not-exist`. You should see our 404 page,
and the browser should report a real 404 (not a 200). On Apache/Hostinger the
`.htaccess` handles this. On Netlify and Cloudflare Pages the `_redirects` file does.
If you use something else, point its "not found" setting at `/404.html`.

---

## 3. The contact form

**Already wired up.** wwwebtech.in runs on Hostinger PHP hosting, so the form
posts to `contact.php`, which sits next to `index.html` in the folder you upload.
It emails the enquiry to `contact@wwwebtech.in` and sends the person an
acknowledgement — the same job the old Laravel controller did.

It also has:

- a honeypot (a hidden field bots fill and people never see) — no CAPTCHA;
- a rate limit of 8 messages per hour per IP;
- rejection of newlines in the name and email fields, so nobody can inject
  extra mail headers;
- a plain HTML thank-you page for anyone browsing with JavaScript off, and JSON
  for the normal case.

**After you upload, send yourself one test enquiry.** If it does not arrive,
check Hostinger's email settings — `mail()` needs the domain's mail service to
be active. There is nothing to configure in the code.

### If you ever move off PHP hosting

On Netlify or Cloudflare Pages there is no PHP. Delete `contact.php`, then open
`site/assets/js/main.js` and change:

```js
const FORM_ENDPOINT = '/contact.php';
```

to a form URL from [Formspree](https://formspree.io) or
[Basin](https://usebasin.com):

```js
const FORM_ENDPOINT = 'https://formspree.io/f/abcdwxyz';
```

Setting it to `''` makes the form compose the message in the visitor's own mail
app instead — a last-resort fallback that never silently drops anything.

**Whichever you use, update `/legal/privacy/`** to name it. The place to edit is
marked with a comment in `src/pages/legal.mjs`.

---

## 4. Switching on the proof sections

The site deliberately contains **no invented client names, logos, testimonials, review
scores or result numbers.** Three sections are built and waiting for real evidence, and
until it exists an honest alternative renders in their place.

Everything lives in one file: **`site/assets/js/proof-config.js`**. Open it — it explains
each field. In summary:

| Set this | And you get |
|---|---|
| `PROJECTS = 40` | The real number in the hero line and the stat row |
| `FOUNDED = 2019` | "since 2019" in the hero, the About page and the search-engine data |
| `LAUNCH_WEEKS = '4-6'` | A real launch window in the stat row and the FAQ |
| `RETENTION = 85` | Replaces the "100% client-owned" stat with your retention figure |
| `CLIENTS = [...]` + `PROOF.clients = true` | The client logo wall on the homepage |
| `CASES = [...]` + `PROOF.work = true` | Real case studies on the homepage and `/work/` |
| `QUOTES = [...]` + `PROOF.quotes = true` | Named testimonials in the trust section |
| `CREDENTIALS = [...]` | The "on the record" chips — delete any line that isn't literally true |

After editing, run `node build.mjs` and re-upload `site/`.

> Please don't put anything in that file the client hasn't actually given you, in writing,
> with permission. The entire argument this website makes is that you are the agency that
> doesn't do that.

If you're not sure you *have* proof yet: you don't need to change anything. The site reads
perfectly well as it stands, and says so on purpose.

---

## 5. Making changes

You need [Node.js](https://nodejs.org) installed (any recent version). No other setup,
no `npm install`.

```bash
node build.mjs      # rebuild site/ from src/
```

It prints every page it wrote and then checks that **every internal link and anchor on the
site actually resolves**. If you mistype a link, the build fails and tells you where. That
check is the reason the old site's broken footer links can't come back.

### Changing words on an existing page

Each page is one file in `src/pages/`, named after the page. The words are in normal
sentences inside backticks. Change them, run `node build.mjs`, upload.

### Changing the phone number, email or address

`src/data.mjs`, at the top. It's used by the header, footer, contact page, and the data
search engines read — change it once, it changes everywhere.

### Adding a blog post

1. Add an entry to the `POSTS` list in `src/data.mjs`:

   ```js
   {
     slug: 'your-url-slug',
     title: 'The headline',
     dek:  'One or two sentences that appear under the title and in Google.',
     date: '2026-09-01', read: 7, service: '/services/seo/',
   },
   ```

2. Copy `src/pages/blog-speed.mjs` to `src/pages/blog-yourname.mjs`, change `slug` to
   match, and write the post between the backticks. Use `<h2 id="something">` for each
   section — the "In this piece" sidebar builds itself from those.
3. `node build.mjs`, then `node tools/images.mjs` to draw the social preview image.

The post automatically gets its own page, appears on `/blog/`, on the homepage, and in
`sitemap.xml`.

### Adding or renaming a service

`src/data.mjs` holds the three pillars and the twelve services. The navigation menu,
the footer, the homepage and the sitemap are all generated from it, so adding a service
there adds it everywhere at once.

---

## 6. Checking your work

```bash
node tools/qa.mjs           # the full check, writes QA-REPORT.md
node tools/qa.mjs --quick   # same but skips Lighthouse (much faster)
```

This checks page weight, Core Web Vitals, accessibility, that the site still works with
JavaScript switched off, that the motion respects "reduce motion" settings, that the
contact form rejects empty submissions, that the HTML is valid, and that the titles and
descriptions are the right length. It writes a pass/fail table to `QA-REPORT.md`.

These extra tools need a one-off install:

```bash
npm i -D playwright axe-core lighthouse html-validate
npx playwright install chromium

# the two Python tools, only if you need to regenerate fonts or the logo
pip install fonttools brotli svgelements
```

Other useful commands:

```bash
node tools/shots.mjs                    # screenshot every page at 1440 / 768 / 390
node tools/shots.mjs --nojs /           # what the homepage looks like with JS off
node tools/shots.mjs --motion off /     # with "reduce motion" turned on
node tools/images.mjs                   # redraw favicons and social preview images
python3 tools/fonts.py                  # re-download and re-subset the fonts
python3 tools/brand.py                  # redraw the logo and favicon files
```

There is also an internal reference page at `/styleguide.html` showing every component
in one place. It isn't linked from the site and search engines are told to ignore it.

---

## 7. Analytics

**No analytics or tracking is loaded.** The site sets no cookies at all, which is why the
privacy policy is short and there's no cookie banner.

Your existing Google Analytics property (`G-3EMCNLKC8Q`) is present but commented out in
`src/layout.mjs`. To switch it on, remove the `<!--` and `-->` around that block, rebuild —
**and update `/legal/privacy/` to say so**, because at that point you *are* collecting data
in people's browsers.

---

## 8. What still needs you

These are the facts only you can supply. Nothing is broken without them — the site has an
honest alternative in every case — but each one makes it stronger.

| # | What's needed | Where it goes |
|---|---|---|
| V:1 | How many projects you've shipped | `PROJECTS` in `proof-config.js` |
| V:2 | The year Wwwebtech started | `FOUNDED` in `proof-config.js`, and `foundingYear` in `src/data.mjs` |
| V:3 | Client logos, with written permission | `CLIENTS` in `proof-config.js` |
| V:4 | Honest typical launch window in weeks | `LAUNCH_WEEKS` in `proof-config.js` |
| V:5 | Retention percentage, if you can actually count it | `RETENTION` in `proof-config.js` |
| V:6 | 3–6 case studies, one measured number each | `CASES` in `proof-config.js` |
| V:7 | 2–4 testimonials with name, role and company | `QUOTES` in `proof-config.js` |
| V:8 | Which credentials genuinely exist today | `CREDENTIALS` in `proof-config.js` |
| V:9 | Confirm the LinkedIn and Instagram URLs are current | `src/data.mjs` |
| V:10 | Google Business Profile link | `gbp` in `src/data.mjs` |
| V:11 | Team names, roles, photos | `src/pages/about.mjs`, marked with a comment |
| V:12 | Legal review of Privacy and Terms | `src/pages/legal.mjs`, marked with comments |

**One inconsistency to settle:** the site promises a reply **within 1 business day**, in
several places. The autoresponder in the old Laravel app said "1–2 business days". Pick
one and make both match — the promise is the most checkable thing on the site, so it
should be the one you're most certain you can keep.

---

## 9. Notes for whoever works on this next

- **The design tokens are in `site/assets/css/tokens.css`.** Colours, type scale, spacing.
  Change a value there and it changes everywhere. `--marigold` is the only accent colour;
  it earns its impact by being rare.
- **All CSS is inlined into every page** at build time — it gzips to about 7KB, which costs
  less than a separate request would. The files in `site/assets/css/` are the source.
- **Three of the twelve techniques in the brief were deliberately left out**: horizontal
  scroll, WebGL distortion, and scroll-scrubbed video. The reasons are written in the
  comments at the top of `site/assets/js/motion.js`. Please read them before adding
  any of the three back.
- **Motion is only ever added, never used to hide.** Nothing on this site starts invisible
  and waits for JavaScript to reveal it. That's what makes it work with JS disabled, and
  it's checked on every page by `tools/qa.mjs`.
- **`site/` is generated.** Editing it directly works, but the next `node build.mjs` will
  overwrite your changes. Edit `src/` instead — except `site/assets/css/`,
  `site/assets/js/` and `site/assets/fonts/`, which are hand-maintained sources.
