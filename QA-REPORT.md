# QA report — wwwebtech.in

Generated 2026-08-26 00:08 UTC · **36/36 checks pass**.

Re-run with `node tools/qa.mjs`. Every row below is an acceptance criterion
from the build brief, not a vanity metric.

## Budget

| | Check | Detail |
|---|---|---|
| ✅ | Total JS ≤ 90KB gzipped (GSAP + Lenis + ours) | 61.1K gzipped across 6 files |
| ✅ | No page image over 60KB | 3 images, largest 3.4K |
| ✅ | Fonts self-hosted and subset | 10 woff2 files, 251.4K total on disk |
| ✅ | Page weight / ≤ 500KB | 258.8K transferred |
| ✅ | Page weight /services/web-development/ ≤ 600KB | 258.4K transferred |
| ✅ | Page weight /blog/website-speed-india/ ≤ 600KB | 194.2K transferred |

## Vitals

| | Check | Detail |
|---|---|---|
| ✅ | LCP < 2.0s (throttled 4G / 4× CPU) | 0.68s |
| ✅ | LCP element is hero text, not an image | H1 |
| ✅ | CLS < 0.05 | 0.0000 |

## A11y

| | Check | Detail |
|---|---|---|
| ✅ | axe-core: no critical or serious violations | 20 pages clean |
| ✅ | Skip link is the first tab stop and becomes visible | {"cls":"skip-link","href":"#main","visible":true} |

## No-JS

| | Check | Detail |
|---|---|---|
| ✅ | All text visible and all pages navigable with JS off | 20 pages readable and navigable |

## Motion

| | Check | Detail |
|---|---|---|
| ✅ | prefers-reduced-motion: marquee animation off | animation-name: none |
| ✅ | prefers-reduced-motion: GSAP and Lenis never load | gsap=false lenis=false |
| ✅ | prefers-reduced-motion: sticky stack is static | position: static |
| ✅ | prefers-reduced-motion: counters show final value | first counter reads 100% |
| ✅ | Lenis active on desktop | window.__lenis present: true |
| ✅ | PageDown still scrolls under Lenis | scrollY 787 |
| ✅ | Programmatic/find-in-page scroll is not fought by Lenis | landed at 4000 (asked 4000) |

## Interactive

| | Check | Detail |
|---|---|---|
| ✅ | Mobile menu opens, locks scroll and moves focus inside | {"visible":true,"expanded":"true","locked":"hidden","focusIn":true} |
| ✅ | Escape closes the menu and returns focus to the button | {"hidden":true,"expanded":"false","unlocked":true,"focusBack":"burger"} |
| ✅ | Form blocks empty submit and shows field errors | {"errs":3,"aria":3,"url":"/contact/"} |
| ✅ | Honeypot swallows bot submissions | {"status":"Thanks — we’ll be in touch.","cleared":true} |
| ✅ | Honeypot is off-screen and not tabbable | {"offscreen":true,"tabindex":-1} |

## Build

| | Check | Detail |
|---|---|---|
| ✅ | No console errors on any page | 20 pages clean |
| ✅ | HTML validates (html-validate, recommended) | 20 pages clean |

## SEO

| | Check | Detail |
|---|---|---|
| ✅ | Titles ≤60ch, unique descriptions ≤155ch, one H1, canonical, valid JSON-LD | 20 pages clean |
| ✅ | Sitemap lists every indexable page and nothing else | 18 URLs; missing []; extra [] |
| ✅ | robots.txt present |  |
| ✅ | .htaccess present |  |
| ✅ | _redirects present |  |
| ✅ | site.webmanifest present |  |
| ✅ | favicon.svg present |  |
| ✅ | 404.html present |  |
| ✅ | Old service URLs 301 to their new homes | 9 301 rules |

## Lighthouse

| | Check | Detail |
|---|---|---|
| ✅ | skipped (--quick) | run without --quick to include |

