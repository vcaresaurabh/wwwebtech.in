#!/usr/bin/env python3
"""Subset + self-host the three brand faces, and emit assets/css/fonts.css.

Run from anywhere:  python3 tools/fonts.py
Requires: pip install fonttools brotli
Sources are the Google Fonts latin / latin-ext woff2 slices listed in SOURCES.
"""
import os, sys, subprocess
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools import subset

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT  = os.path.join(ROOT, "site", "assets", "fonts")
CSS  = os.path.join(ROOT, "site", "assets", "css", "fonts.css")

# Curated charset: ASCII + Latin-1 + Latin Ext-A (names) + the punctuation/symbols the site uses.
EXTRA = "₹—–‘’“”…→↓←↑·©®™°×³²¹½•✓ −«»‹›‐‑"
TEXT  = "".join(chr(c) for c in range(0x20,0x7F)) + "".join(chr(c) for c in range(0xA0,0x180)) + EXTRA
FEATURES = ['kern','liga','calt','ccmp','locl','mark','mkmk','rlig','tnum','case','ss01','frac','sups']

SOURCES = [
  # (out name, url, axis limits, css family, style, weight)
  ("fraunces",            "https://fonts.gstatic.com/s/fraunces/v38/6NU78FyLNQOQZAnv9bYEvDiIdE9Ea92uemAk_WBq8U_9v0c2Wa0KxC9TeA.woff2",              {"wght":(400,600),"opsz":(14,144)}, "Fraunces","normal","400 600"),
  ("fraunces-ext",        "https://fonts.gstatic.com/s/fraunces/v38/6NU78FyLNQOQZAnv9bYEvDiIdE9Ea92uemAk_WBq8U_9v0c2Wa0KxCFTeO-U.woff2",          {"wght":(400,600),"opsz":(14,144)}, "Fraunces","normal","400 600"),
  ("fraunces-italic",     "https://fonts.gstatic.com/s/fraunces/v38/6NU58FyLNQOQZAnv9ZwNjucMHVn85Ni7emAe9lKqZTnbB-gzTK0K1ChjeveQ.woff2",           {"wght":(400,600),"opsz":(14,144)}, "Fraunces","italic","400 600"),
  ("fraunces-italic-ext", "https://fonts.gstatic.com/s/fraunces/v38/6NU58FyLNQOQZAnv9ZwNjucMHVn85Ni7emAe9lKqZTnbB-gzTK0K1ChjdPeQ_5Y.woff2",       {"wght":(400,600),"opsz":(14,144)}, "Fraunces","italic","400 600"),
  ("archivo",             "https://fonts.gstatic.com/s/archivo/v25/k3kPo8UDI-1M0wlSV9XAw6lQkqWY8Q82sLydOxI.woff2",                                 {"wght":(400,600)},                 "Archivo","normal","400 600"),
  ("archivo-ext",         "https://fonts.gstatic.com/s/archivo/v25/k3kPo8UDI-1M0wlSV9XAw6lQkqWY8Q82sLyTOxK-vA.woff2",                             {"wght":(400,600)},                 "Archivo","normal","400 600"),
  ("plexmono-400",        "https://fonts.gstatic.com/s/ibmplexmono/v20/-F63fjptAgt5VM-kVkqdyU8n1i8q1w.woff2",                                      None,                               "IBM Plex Mono","normal","400"),
  ("plexmono-400-ext",    "https://fonts.gstatic.com/s/ibmplexmono/v20/-F63fjptAgt5VM-kVkqdyU8n1iEq129k.woff2",                                    None,                               "IBM Plex Mono","normal","400"),
  ("plexmono-500",        "https://fonts.gstatic.com/s/ibmplexmono/v20/-F6qfjptAgt5VM-kVkqdyU8n3twJwlBFgg.woff2",                                  None,                               "IBM Plex Mono","normal","500"),
  ("plexmono-500-ext",    "https://fonts.gstatic.com/s/ibmplexmono/v20/-F6qfjptAgt5VM-kVkqdyU8n3twJwl5FgtIU.woff2",                                None,                               "IBM Plex Mono","normal","500"),
]

# Fallback faces we metric-match against, as (css local name, upem, xHeight, ascent, descent).
FALLBACK = {
  "Fraunces":       ("Georgia",     2048,  986, 1878, 449),
  "Archivo":        ("Arial",       2048, 1062, 1854, 434),
  "IBM Plex Mono":  ("Courier New", 2048,  866, 1705, 615),
}

def fix_gvar(f):
    if 'gvar' in f:
        g = f['gvar']
        g.variations = {n: g.variations.get(n, []) for n in f.getGlyphOrder()}
    return f

def ranges(codepoints):
    """Collapse a sorted codepoint set into CSS unicode-range syntax."""
    cps, out, start, prev = sorted(codepoints), [], None, None
    for c in cps:
        if start is None: start = prev = c; continue
        if c == prev + 1: prev = c; continue
        out.append((start, prev)); start = prev = c
    if start is not None: out.append((start, prev))
    return ", ".join(f"U+{a:04X}" if a == b else f"U+{a:04X}-{b:04X}" for a, b in out)

def main():
    os.makedirs(OUT, exist_ok=True); os.makedirs(os.path.dirname(CSS), exist_ok=True)
    tmp = "/tmp/fontsrc"; os.makedirs(tmp, exist_ok=True)
    faces, seen_per_family, total = [], {}, 0

    for name, url, axes, fam, style, weight in SOURCES:
        src = os.path.join(tmp, name + ".src.woff2")
        if not os.path.exists(src):
            subprocess.run(["curl","-sS","-m","40","-o",src,url], check=True)
        f = fix_gvar(TTFont(src, lazy=False))
        if axes and 'fvar' in f:
            f = fix_gvar(instancer.instantiateVariableFont(f, axes, inplace=False, updateFontNames=False))
        o = subset.Options(); o.flavor = "woff2"; o.layout_features = FEATURES
        o.name_IDs = ['*']; o.name_legacy = True; o.name_languages = ['*']
        o.notdef_outline = True; o.recalc_bounds = True; o.drop_tables = ['DSIG']
        s = subset.Subsetter(options=o); s.populate(text=TEXT); s.subset(f)
        dst = os.path.join(OUT, name + ".woff2")
        f.flavor = "woff2"; f.save(dst)

        # Real cmap of the built file, minus anything an earlier slice of the same family already covers,
        # so each unicode-range is exact and no two slices overlap.
        got = set(TTFont(dst).getBestCmap().keys())
        key = (fam, style, weight)
        got -= seen_per_family.get(key, set())
        seen_per_family[key] = seen_per_family.get(key, set()) | got
        size = os.path.getsize(dst); total += size
        print(f"  {name+'.woff2':30s} {size/1024:6.1f}K  {len(got):4d} glyphs")
        if got:
            faces.append((fam, style, weight, name, ranges(got)))

    lines = ["/* Self-hosted brand faces. Generated by tools/fonts.py — do not edit by hand. */\n"]
    for fam, style, weight, name, ur in faces:
        lines.append(
f"""@font-face {{
  font-family: '{fam}';
  font-style: {style};
  font-weight: {weight};
  font-display: swap;
  src: url('../fonts/{name}.woff2') format('woff2');
  unicode-range: {ur};
}}""")

    # Metric-matched fallbacks so swapping the real face in shifts nothing (CLS budget, §11).
    lines.append("\n/* Metric-matched fallbacks: swap-in causes no reflow. */")
    for fam, (local, fu, fx, fa, fd) in FALLBACK.items():
        real = TTFont(os.path.join(OUT, {"Fraunces":"fraunces","Archivo":"archivo","IBM Plex Mono":"plexmono-400"}[fam] + ".woff2"))
        upem = real['head'].unitsPerEm
        x    = real['OS/2'].sxHeight / upem
        asc  = real['hhea'].ascent / upem
        desc = abs(real['hhea'].descent) / upem
        S = x / (fx / fu)
        lines.append(
f"""@font-face {{
  font-family: '{fam} Fallback';
  src: local('{local}');
  size-adjust: {S*100:.2f}%;
  ascent-override: {asc/S*100:.2f}%;
  descent-override: {desc/S*100:.2f}%;
  line-gap-override: 0%;
}}""")

    open(CSS, "w").write("\n".join(lines) + "\n")
    print(f"  {'TOTAL':30s} {total/1024:6.1f}K  ->  {os.path.relpath(CSS, ROOT)}")

if __name__ == "__main__":
    main()
