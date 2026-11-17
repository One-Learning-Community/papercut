#!/usr/bin/env python3
"""Writes tests/fixtures/fira-math-bounds.json: exact glyph bounds from fontTools, the oracle for the CFF reader.

Run once, and again only if the fixture font changes:

    python3 -I bin/cff-bounds-fixture.py

Needs fontTools. Bounds come from BoundsPen (exact curve extrema), rounded outward to whole font units, keyed by
glyph id. The sample is every 11th glyph plus the glyphs of a few common math characters.
"""
import json
import math
import os

from fontTools.pens.boundsPen import BoundsPen
from fontTools.ttLib import TTFont

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "tests", "fixtures")
font = TTFont(os.path.join(root, "FiraMath-Regular.otf"))
order = font.getGlyphOrder()
glyphs = font.getGlyphSet()
cmap = font.getBestCmap()

sample = set(range(0, len(order), 11))
for ch in "x2(){}∑∫√∞":
    sample.add(order.index(cmap[ord(ch)]))

out = {}
for gid in sorted(sample):
    pen = BoundsPen(glyphs)
    glyphs[order[gid]].draw(pen)
    b = pen.bounds
    out[str(gid)] = [0, 0, 0, 0] if b is None else [math.floor(b[0]), math.floor(b[1]), math.ceil(b[2]), math.ceil(b[3])]

with open(os.path.join(root, "fira-math-bounds.json"), "w") as f:
    json.dump(out, f, separators=(",", ":"))
    f.write("\n")
print(len(out), "glyphs")
