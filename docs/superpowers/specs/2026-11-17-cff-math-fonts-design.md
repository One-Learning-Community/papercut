# CFF math fonts

Status: approved direction, 2026-11-17. Graduates the CFF spike (branch `spike/cff-math`, now `cff-math` off main).

## Why

The math package now stands on its own as a PHP answer to KaTeX and MathJax. People who come from those tools expect
the LaTeX look, and that look is Latin Modern Math, which ships only as an OpenType font with CFF outlines. Most other
math fonts ship the same way: TeX Gyre, New Computer Modern, Fira Math, Libertinus Math, XITS, Euler, and the macOS copy
of STIX Two Math. Today all of them are refused.

The work needs no upstream change. php-pdf already embeds CFF fonts.

## Survey

Eight real CFF math fonts were run through the spike reader and through fontTools: STIX Two Math (macOS), Latin Modern
Math, TeX Gyre DejaVu Math, Euler, Fira Math, New Computer Modern Math, XITS Math and Libertinus Math.

- Every glyph of every font parses with no errors, 0.03 to 0.36 s per font for all glyphs.
- All are name-keyed. None is CID-keyed.
- None uses `seac` (accented glyphs built from two others) or the Type 2 arithmetic operators.
- All use 1000 units per em and the default font matrix.

## Scope

**In:** OpenType fonts with a `CFF ` table (sfnt tag `OTTO`), name-keyed, with a `MATH` table.

**Refused** with `UnsupportedFont` and a message that names the file and the reason:

- `CFF2` tables (variable fonts);
- CID-keyed CFF (a `ROS` operator in the top dict);
- a `FontMatrix` that disagrees with the `head` table's units per em;
- a font collection, as today.

**Not read:** `seac` and the arithmetic operators. A glyph that uses them draws without the part they would add. No
surveyed font uses them, and adding them later changes nothing else.

## The class is renamed `Font`

`Quoin\Math\Font\TrueTypeFont` becomes `Quoin\Math\Font\Font`, since it now reads both outline formats.
`TrueTypeFont` stays as a deprecated `class_alias` until 1.0, so code written against 0.16 keeps working. Docs, examples
and every internal use switch to `Font`. The alias lives in its own `TrueTypeFont.php`, so the Composer autoloader finds
it.

## Outlines

`CffOutlines` runs each charstring to a list of path segments: move, line, cubic curve, close. From those:

- **Bounds** are exact. Each cubic's extrema are solved, then rounded outward to font units.
- **`outlinePath()`** writes the cubics as SVG `C` commands, with no approximation. The documented contract widens from
  "`M`, `L`, `Q` and `Z`" to "`M`, `L`, `Q`, `C` and `Z`". A TrueType font still never produces `C`. SVG, PNG through
  Imagick and core's PDF path drawing through php-pdf all read `C` already.
- **`contours()`** keeps its quadratic point form for the two internal readers that inspect points
  (`straightAlong()` and the stacked-bars check in `UnderOver`). It approximates each cubic by two quadratics, as the
  spike does. Both readers look only at extremes, and a straight cubic stays straight under the approximation.

`CffOutlines` keeps no path cache of its own. `Font` already caches bounds, contours and path data per glyph, and a
second cache of segment arrays made a full walk of Latin Modern's 4,800 glyphs pass 256 MB.

## Robustness

A font file is chosen by the developer, so it is trusted input. Untrusted text never selects a font file. This work
follows the trusted-input rule: guard what an honest but unusual font can hit, and turn a broken file into a clear
exception. No fuzz campaign and no hostile-input review rounds.

Guards:

- the sfnt tag must be `0x00010000`, `true` or `OTTO`, so an HTML page saved as `.otf` is "not a font" rather than
  "has no MATH table";
- every INDEX and dict must lie inside the `CFF ` table, and the charstring count must equal `maxp`'s glyph count;
  otherwise `UnsupportedFont`;
- the charstring interpreter caps its operand stack at 48 (the Type 2 limit), subroutine nesting at 10 (the Type 2
  limit), and the operators run for one glyph at a fixed budget, so a self-calling subroutine ends instead of spinning;
- dict real numbers are decoded properly (the spike skipped them), since `FontMatrix` is now checked.

## PDF size

php-pdf embeds a CFF font whole instead of subsetting it, so a large font makes a large PDF. With one
equation, Fira Math (175 KB file) gives 135 KB against 71 KB with the bundled TrueType STIX Two Math; Latin Modern Math
(716 KB file) gives 471 KB. SVG, PNG and MathML output carry no font, so they
are unaffected. The fonts guide states this plainly and recommends the bundled font where PDF size matters. Subsetting
CFF in php-pdf would be an upstream change and is not part of this work.

## Bundled font

STIX Two Math stays the only bundled font. The docs say where to get Latin Modern Math and the others, with a recipe for
the LaTeX look.

## Tests

- A CFF fixture: Fira Math Regular (OFL, 175 KB) in `packages/math/tests/fixtures`, which the dist archive already
  excludes.
- Bounds checked against an independent oracle: a fixture file of fontTools `BoundsPen` boxes for a sample of glyphs,
  generated once by a script kept in `bin/`.
- `outlinePath()` for a curved glyph contains `C` and no `Q`; for a TrueType glyph, no `C`.
- An equation laid out in Fira Math renders to SVG and PNG, and in core to PDF and HTML.
- Each refusal above, using small synthetic files built in the test.
- The interpreter guards: a charstring that calls itself, and one that pushes past 48 operands, both finish.
- The alias: `TrueTypeFont::fromFile()` returns a `Font`.
- The two spike failures go: the `@internal` tag on `CffOutlines` matches the architecture test's rule, and the old
  "rejects CFF" test becomes "rejects CFF2".

## Docs

- `site/src/math/backends.md`: fonts section, `outlinePath()` contract, the `Font` name.
- A recipe in `site/src/math/recipes.md`: the LaTeX look with Latin Modern Math.
- `site/src/guide/equations.md` and `site/src/guide/fonts.md`: CFF accepted, the PDF size note.
- Laravel: `config/quoin.php` comment, `FontConfig` messages, `packages/laravel/README.md` and
  `site/src/laravel/configuration.md`.
- Core: `FontSet` docblocks.
- `UnsupportedFont::cff()` now speaks of CFF2 only.
- Changelog entry, including the rename and the `outlinePath()` contract change.

## Release

A minor release, 0.17.0.
