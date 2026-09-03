#!/usr/bin/env python3
"""Generate the logo lockup, the w3 glyph mark, and the favicon.

The wordmark outlines are the *existing* Wwwebtech logo, re-cut into two
colour groups per §4 rather than redrawn — so the brand keeps continuity.
The superscript 3 is lifted from Archivo, the site's own text face.
"""
import re, os
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen
from svgelements import Path as SvgPath

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMG  = os.path.join(ROOT, "site/assets/img"); os.makedirs(IMG, exist_ok=True)

d = re.search(r'\sd="([^"]+)"', open(os.path.join(ROOT, "public/assets/logos/wwwebtech.svg")).read()).group(1)
subs = ['M' + p for p in d.split('M') if p.strip()]
WORD, TAIL, W1 = " ".join(subs[0:7]), " ".join(subs[7:12]), subs[0]

# --- the 'w' box, measured off the real outline -------------------
w_x0, w_y0, w_x1, w_y1 = SvgPath(W1).bbox()

# --- superscript three, from Archivo ------------------------------
f  = TTFont(os.path.join(ROOT, "site/assets/fonts/archivo.woff2"))
gs = f.getGlyphSet(); name = f.getBestCmap()[0x00B3]
bp = BoundsPen(gs); gs[name].draw(bp)
gx0, gy0, gx1, gy1 = bp.bounds

W_H   = w_y1 - w_y0                      # height of the 'w'
S     = (W_H * 0.42) / (gy1 - gy0)       # numeral reads at 42% of the w
GAP   = 4.0
DX    = w_x1 + GAP - gx0 * S
DY    = w_y0 + (W_H * 0.42) + gy0 * S    # flipped: top of 3 aligns to top of w

pen = SVGPathPen(gs)
gs[name].draw(TransformPen(pen, (S, 0, 0, -S, DX, DY)))
THREE = pen.getCommands()

m_x0, m_y0 = w_x0, w_y0
m_x1, m_y1 = DX + gx1 * S, w_y1
PAD = 3.0
MVB = f"{m_x0-PAD:.1f} {m_y0-PAD:.1f} {m_x1-m_x0+2*PAD:.1f} {m_y1-m_y0+2*PAD:.1f}"
MARK_INNER = f'<path d="{W1}"/><path d="{THREE}"/>'

# --- 1. Full lockup ------------------------------------------------
open(os.path.join(IMG, "logo.svg"), "w").write(
f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 28 568 78" role="img" aria-label="Wwwebtech">
<path class="logo__word" d="{WORD}"/>
<path class="logo__tail" d="{TAIL}"/>
<circle class="logo__dot" cx="554" cy="87" r="10"/>
</svg>''')

# --- 2. w3 glyph mark ----------------------------------------------
open(os.path.join(IMG, "mark.svg"), "w").write(
  f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{MVB}" role="img" aria-label="Wwwebtech">'
  f'<g fill="currentColor">{MARK_INNER}</g></svg>')

# --- 3. favicon.svg — marigold on ink, mark optically centred -------
mw, mh = m_x1 - m_x0, m_y1 - m_y0
sc = 44.0 / max(mw, mh * (mw / mh))       # fit the wider axis into a 44px well
sc = 44.0 / mw
tx, ty = (64 - mw * sc) / 2 - m_x0 * sc, (64 - mh * sc) / 2 - m_y0 * sc
open(os.path.join(ROOT, "site/favicon.svg"), "w").write(
  f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
  f'<rect width="64" height="64" rx="13" fill="#131614"/>'
  f'<g transform="translate({tx:.2f} {ty:.2f}) scale({sc:.4f})" fill="#E07000">{MARK_INNER}</g></svg>')

# --- 4. tiny favicon: just the w, for 16px where the 3 is mud -------
w_sc = 46.0 / (w_x1 - w_x0)
open(os.path.join(IMG, "mark-tiny.svg"), "w").write(
  f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
  f'<rect width="64" height="64" rx="13" fill="#131614"/>'
  f'<g transform="translate({(64-(w_x1-w_x0)*w_sc)/2 - w_x0*w_sc:.2f} {(64-(w_y1-w_y0)*w_sc)/2 - w_y0*w_sc:.2f}) scale({w_sc:.4f})" fill="#E07000">'
  f'<path d="{W1}"/></g></svg>')

print(f"w box  x {w_x0:.1f}..{w_x1:.1f}  y {w_y0:.1f}..{w_y1:.1f}")
print(f"mark viewBox {MVB}")
print("wrote logo.svg, mark.svg, mark-tiny.svg, favicon.svg")
