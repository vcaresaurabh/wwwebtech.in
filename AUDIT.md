# wwwebtech.in — full audit

Generated 2026-09-04. Everything below was checked against the live site and
the repository on that date, not recalled from memory. Where a number appears,
the command that produced it is named so it can be re-run.

---

## The one-paragraph version

The site, the admin panel, the landing pages, the follow-up funnel, the
conversations inbox, the free audit tool and the offline-conversions feed are
all built, tested and live. The one real performance problem — half a megabyte
of Google tag JavaScript loading before the first word could paint — is fixed
in this pass, along with a privacy policy that had become untrue, a dead mail
endpoint that was still accepting posts, and a handful of smaller things. What
is left is almost entirely switches only the owner can flip, and they are
listed at the end in the order to flip them.

---

## 1 · What is built, and how each piece is verified

| Area | What exists | Proven by |
|---|---|---|
| **Static site** | 20 pages from `src/`, rendered by `build.mjs`. Inlined CSS, self-hosted subset fonts, no image above the fold, motion stack loads only on desktop after idle. | `node tools/qa.mjs` — **38/38** (Lighthouse best-of-3, local 4G/4×CPU throttle: `/` 98·100·100·100, `/services/seo/` 98·100·100·100, blog post 99·100·100·100; LCP 0.56s, CLS 0.000) |
| **Link integrity** | Every internal href must resolve or the build fails. Server-rendered pages are declared in `SERVER_PAGES` and their controller must exist. | `node build.mjs` — "OK - every internal link and anchor resolves" |
| **Admin panel** | 13 pages: dashboard, leads, lead, analytics, conversations, funnel, landing pages, audits, blog, SEO health, integrations, settings, login. | `automation/tools/render-pages.php` — 13/13 on production |
| **Leads** | Saved to MySQL first, emailed second. Scored and banded on arrival. 30+ fields including click IDs, UTMs, consent version, dwell and pages seen. | `gate-phase2.sh` 59, `gate-phase3.sh` 47 |
| **Landing pages** | 7 pages under `/lp/`, one controller, data files per page. Message-match whitelist, sticky A/B variant, multi-step form that is a plain form first. | `gate-lp.sh` 49; PageSpeed mobile **100 / 100 / 100**, LCP 1.4s, CLS 0.002 |
| **Notifications** | Company email, personal email, Telegram — three independent channels, each wrapped so one failing cannot block the others. | `gate-phase4.sh` 80 |
| **Follow-up funnel** | 3 sequences (17 steps), AI-drafted messages held for approval, auto-send opt-in per sequence, quiet hours, daily caps, unsubscribe by link or by reply, kill switch that also cancels queued messages. | `tests/funnel_test.php` 64; `gate-funnel.sh` 27 |
| **Stop conditions** | Reply, booking, status change, opt-out, two hard bounces, partial or test lead — any of them halts the sequence. | `funnel_test.php` §1, `gate-phase7.sh` §1 |
| **Conversations** | Unified thread per lead, reply by email or WhatsApp, Claude draft, snippets, private notes. Sending stops the sequence. | `gate-phase7.sh` 28; render-pages `conversations:thread` |
| **Inbound replies** | IMAP polling, matched by `+lead` token, threading headers, then sender address (only when unambiguous). Auto-replies and bounces filtered. | `funnel_test.php` §2–4. IMAP is present on the production PHP build. |
| **Free audit tool** | 17 checks against a real page plus Google field data. Honest by construction: wwwebtech.in scores 100, Zoho 86, neverssl 42. Fixture pages score 100 and 25. | `gate-phase7.sh` §3–5 |
| **Offline conversions** | Google, Microsoft and Enhanced CSV feeds behind a rotatable key. Wrong or missing key → 404. Contact details leave only as SHA-256 hashes. | `gate-phase7.sh` §6; `funnel_test.php` §7 |
| **Blog** | Scheduled generation under a monthly spend cap, quality gates (banned claims, similarity, structure, meta lengths), published from the database on every deploy. | `gate-phase5.sh` 29; 6 posts live |
| **SEO automation** | Daily: meta lengths, schema, sitemap, robots, AI-crawler access, Core Web Vitals, IndexNow. | `run.php seo_daily` — 8/8 on production |
| **Security** | PDO prepared statements only, AES-256-GCM secrets, CSRF on every mutation, admin CSP, rate limits, honeypots, SSRF guard on the audit tool. | `gate-security.sh` 64 |
| **Deploy safety** | Backup before every deploy, one-command rollback, refuses to run if a web-root directory is not excluded from `--delete`. | `tools/deploy.sh` guard, exercised |

Total: **9 gate scripts + 1 unit suite, 511 assertions, all passing** at the time of writing.

---

## 2 · What this pass found and fixed

Ordered by how much it mattered.

### 2.1 · Half a megabyte of tag JavaScript before first paint — fixed

PageSpeed on the live homepage showed LCP **3.6s** and total blocking time
**410ms**, against **1.4s / 0ms** on the landing pages served from the same
box with the same fonts. The difference was the analytics tags injected from
the panel's Integrations page: two copies of `gtag.js` (191KB each — the same
file, fetched twice because the query string differed) and a Tag Manager
container (112KB), all in `<head>`.

Nothing in those scripts is needed to draw the page. They now wait for it:
the visitor's first touch, scroll or keypress, or five seconds after the `load`
event, whichever comes first. `gtag()` calls made before then are queued and
replayed, so no event is lost. GA4 and Ads share **one** download of `gtag.js`.

Why five seconds rather than "the first idle moment": the idle version was
measured too, and the scripts still parsed and ran inside the window
Lighthouse counts as blocking — 421ms of it on a page that otherwise has none.
Five seconds is past that window on every device class. The only visitors it
costs are those who leave within five seconds without touching the page, and
the site's own server-side counter still records them.

The landing pages and the audit tool, which are PHP, never received the tags
at all — `Tags::apply()` only rewrites `.html` files, so their markers were
decorative. They now render the tags at request time, deferred, which means
"Apply to the site" in the panel finally means the whole site.

The Integrations page's stated costs ("~50KB") were guesses; they are now the
measured figures.

**Measured live, PageSpeed Insights, mobile.** "Before" is the diagnostic run
taken minutes before the fix; the homepage swung between 73 and 87 across the
morning's runs, so the before figures are a snapshot of a noisy number, and the
after figures are a single run each.

| Page | Before — perf · LCP · TBT | After — perf · LCP · TBT |
|---|---|---|
| `/` | 80 · 3.6s · 410ms | **98 · 2.04s · 0ms** |
| `/about/` | 72 · 5.9s · — | **99 · 1.66s · 0ms** |
| `/services/web-development/` | 73 · 6.9s · — | **98 · 2.00s · 0ms** |
| `/lp/custom-crm/` (never had tags) | 100 · 1.4s · 0ms | **100 · 1.36s · 0ms** |

Accessibility, best-practices and SEO are 100 on all four. Tag-script bytes
fetched inside the measured window: 494KB before, **0** after — they load
afterwards, by design.

The homepage's 2.04s LCP is at the brief's 2.0s line, not under it, on this
run. The same page measures 0.56s under identical throttling locally (`tools/qa.mjs`),
so the difference is real network distance between Google's test agent and the
Hostinger box, not page weight — the HTML is 20KB over the wire. There is no
field data yet to say what real visitors see; the daily SEO job records the
trend in `wwt_cwv`.

### 2.2 · The privacy policy said things that were not true — fixed

It stated *"This website loads no third-party analytics or advertising
scripts"* while the live site loaded GA4, Tag Manager and Google Ads tags. It
now names all three, says they set cookies, and says they load only after the
page has drawn. Dated 4 September 2026.

The panel had been warning about this on the Integrations page since the tags
were switched on. Nobody reads warnings on a page they visit once.

### 2.3 · A dead mail endpoint was still live — removed

`/contact.php` was the standalone mailer from before the automation layer. The
forms have posted to `/api/lead.php` since then, but the old file stayed on
the server accepting POSTs from anyone and sending mail from it — an open
relay with an 8-per-hour brake. Deleted.

### 2.4 · Meta descriptions over the length Google shows — fixed (earlier today)

The blog gate allowed 400 characters while its own message said "too long for
a meta description"; three published posts were truncated in results, and the
audit tool's own page shipped at 66/185 characters. Gate is now 160, the
prompt asks for 120–155, the three descriptions were rewritten by hand rather
than cut.

### 2.5 · Smaller things

- The QA gate's Lighthouse step could not find Chrome in this container and
  had been silently failing; it is wired to the same Chrome Playwright uses.
- The QA gate's sitemap check did not know about server-rendered pages and
  flagged the audit tool as "extra". Build, sitemap and QA now read one list.
- The Settings page printed "the last 1 leads split…" and invited the owner to
  move scoring thresholds on the strength of one lead. Below five it now says
  there are too few to judge.
- The config file's documented model fallback was never actually read; it is
  now, and it named a retired model id.
- Two copies of `schema.sql`, one live, one stale. The stale one is gone.

---

## 3 · What is left

### 3.1 · Owner actions — nothing sends until these are done

These are in the ready-to-paste Chrome runbook
(`automation/deploy/CLAUDE-IN-CHROME-FINISH.md`), in this order:

1. **Add the 5-minute cron** (`run.php frequent`) in hPanel. Without it a
   reply can take an hour to stop a sequence.
2. **Reading replies** — mailbox password in Settings. This is the mechanism
   that keeps the automation polite.
3. **Sender identity** — your name and a real address in Settings, plus the
   alert address.
4. Telegram bot (free, optional). Search Console sitemap resubmit.
5. **Rotate every credential that was pasted into a chat**: mailbox, SSH,
   Anthropic, PageSpeed. The database password is deliberately not in the
   browser runbook — it needs a file changed on the server at the same moment
   and is safer done over SSH.
6. Then read `FUNNEL-SETUP.md` and turn the funnel on, one sequence at a time,
   after reading what it wants to send.

### 3.2 · Decisions, not tasks

- **Cookie consent.** With GA4, Tag Manager and Ads tags on, the site sets
  cookies. The policy now says so. Whether that needs a consent banner under
  the DPDP Act is a judgement about your risk appetite and your traffic; most
  Indian SME sites run GA without one. If you want one, it should gate the
  deferred loader — a one-line change now that loading is centralised.
- **Two GA4 properties exist.** `G-3EMCNLKC8Q` is the one written into the
  site's source (commented out, never loaded). `G-L0NEHDTSN4` is the one you
  switched on in the panel and the one receiving data. If the first is a
  property you still look at, it is empty and will stay empty.
- **Google Ads** — excluded from this pass at your request. The offline
  conversion feeds are built and the runbook for the Ads side is
  `ADS-SETUP.md`.
- **WhatsApp** — built, off. Needs Meta business verification and can cost
  money. `WHATSAPP-SETUP.md` has the honest case for and against.

### 3.3 · Engineering left on the table

- **Homepage lab LCP is noisy** even with the tags deferred, because it is
  lab-only: there has never been enough traffic for field data. The stored
  history (`wwt_cwv`) will show the real trend within a few weeks of the fix.
- **A Content-Security-Policy for the public site.** The admin panel has one;
  the static pages do not. Adding one now means allowlisting Google's tag
  hosts and using nonces for the inline styles — worth doing, not urgent.
- **The italic Fraunces face is the largest font (63KB).** It is used below
  the fold and loads with `swap`, so it costs nothing measurable. It could be
  subset further if it ever shows up in a trace.
- **Blog posts are lab-tested via one static post.** Server-generated posts
  use the same shell, so this is low risk, but the QA gate does not fetch a
  generated post from the server.

---

## 4 · How to re-run everything

```
node build.mjs                          # static site + link check
node tools/qa.mjs                       # 38 acceptance checks incl. Lighthouse
bash automation/tools/dev.sh            # local PHP + mail sink
bash automation/tools/gate-*.sh         # nine gates
php automation/tests/funnel_test.php    # the judgement calls
php automation/tools/render-pages.php   # every panel page against the real DB
```

On the server: `php wwt_private/cron/run.php selftest` and `seo_daily`.
