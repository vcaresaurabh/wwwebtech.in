# Wwwebtech — brand export pack

Generated from the same outlines the website uses, so these can never drift
from the live site. Regenerate any time with `node tools/export-brand.mjs`.

## What changed from the old logo

| | Old | New |
|---|---|---|
| "wwweb" | `#0E0E11` near-black | `#131614` ink |
| "tech" | `#0E0E11` — same as the rest | `#686D69` grey — the two halves now read as two words |
| The dot | `#4F46E5` indigo | `#E07000` marigold |

The wordmark shapes are **unchanged** — same letterforms, same spacing. Only the
colour split is new, so this reads as a refresh rather than a different company.

---

## Which file do I upload where?

### Profile picture / logo

Every one of these platforms crops your picture to a **circle**. These files are
built for that: the `w³` sits well inside the circle, so nothing gets clipped.

| Platform | Use this file |
|---|---|
| Google Business Profile → Logo | `profile/google-business-profile-720.png` |
| Instagram | `profile/instagram-1080.png` |
| LinkedIn company page | `profile/linkedin-400.png` |
| Facebook page | `profile/facebook-512.png` |
| WhatsApp Business | `profile/whatsapp-business-640.png` |
| Anything else | `profile/avatar-1024.png` |

`profile/alt-marigold-1024.png` is the same mark inverted — dark `w³` on a
marigold ground. Louder in a crowded feed. Pick one and use it everywhere;
switching between them across platforms just looks careless.

`profile/avatar-circle-1024.png` is already round, for the rare place that
won't crop for you.

### Wordmark (the horizontal logo)

All transparent PNGs — no white box around them.

| File | Use on |
|---|---|
| `logo/wordmark-light-bg-*.png` | white or light backgrounds |
| `logo/wordmark-dark-bg-*.png` | dark backgrounds |
| `logo/wordmark-mono-black-2400.png` | one-colour printing, stamps, forms |
| `logo/wordmark-mono-white-2400.png` | on photos, or on a marigold panel |

`-2400` is the high-resolution version; use it for print or anything large.
`-1200` is plenty for web.

**Do not** put the light-background version on marigold — the grey "tech" nearly
disappears. Use `mono-white` there.

### Covers / banners

| Platform | File |
|---|---|
| Google Business Profile → Cover | `cover/google-business-profile-1024x576.png` |
| LinkedIn company banner | `cover/linkedin-1128x191.png` |
| Facebook page cover | `cover/facebook-820x312.png` |
| X / Twitter header | `cover/x-twitter-1500x500.png` |

Everything important is centred, because every platform crops these differently
and some crop hard — especially on phones.

### Favicons

`favicon/` holds the browser-tab icons. **These are already installed on the
website** — you only need them if you are setting up another site or a tool that
asks for an icon upload.

The 16px and 32px versions deliberately drop the superscript ³ — at that size it
turns into a smudge, so they show a clean `w` instead.

### SVG

`svg/` has the vector originals. **Prefer these wherever a platform accepts
them** — they stay sharp at any size. Give `svg/wordmark.svg` to a printer or a
signage company rather than a PNG.

---

## Brand colours, for any form that asks

| | Hex | Where |
|---|---|---|
| Ink | `#131614` | "wwweb", headlines, dark backgrounds |
| Grey | `#686D69` | "tech", captions |
| Marigold | `#E07000` | the dot, buttons, links — the only accent |
| Paper | `#FAF8F4` | page background (warm, not pure white) |

Fonts: **Fraunces** for headlines, **Archivo** for body text, **IBM Plex Mono**
for small labels. All three are free on Google Fonts.
