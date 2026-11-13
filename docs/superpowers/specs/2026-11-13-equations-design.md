# Equations: `papercut/math`, block and inline, in every output

## Why

Papercut has no way to write a formula with stacked parts: a fraction, a radical, a superscript. Worksheets, lesson
material and academic documents need them, and a document that falls back to `(−b ± √(b² − 4ac)) / 2a` in a line of
text reads badly. A real request now exists: the last thing keeping a downstream app on PhpWord is equations in
material sheets, exported as an editable Word file and as a PDF.

No PHP library lays out math for PDF. The usual routes shell out to TeX or to MathJax on Node, or draw a picture.
A spike (branch `spike/math`) ported the approach of ziamath (Python, MIT) to PHP: layout driven by the OpenType
`MATH` table of a math font, with STIX Two Math bundled. All test formulas rendered well, including the quadratic
formula with a real superscript, stretchy parentheses, nested radicals and inline style. This spec turns that spike
into a product.

## Goals

- A new package, **`papercut/math`**: math layout as a library of its own, usable without the rest of Papercut.
  It holds the formula tree and its builder, the font reader, the layout engine and writers for SVG, MathML and
  Word's OMML. It depends on nothing in Papercut. It is versioned in lockstep with the other packages.
- Formulas in Papercut documents, **block** (a formula on its own line) and **inline** (inside running text, like
  inline badges), in PDF, Word and HTML export.
- Day-1 vocabulary: rows of identifiers, numbers, operators and upright text; **fractions**; **square roots** and
  **roots with an index**; **superscripts, subscripts and both together**; **delimiters that stretch** to their
  contents. Every part nests inside every other.
- Native output in each format:
  - **Word**: live OMML equations that open in Word's equation editor. Never a picture.
  - **PDF**: Papercut's own layout, with letters and digits as real, selectable text.
  - **HTML export**: MathML Core, which current browsers render without a script.
- The PDF looks like what Word shows for the same formula: math italic letters, upright digits and operators, Word's
  spacing around operators.
- A configurable math font for PDF and HTML. STIX Two Math is the bundled default.

## Non-goals (for now)

- **CFF math fonts** (Latin Modern Math, Libertinus Math, Fira Math). The first release reads TrueType (`glyf`)
  math fonts only. A CFF or non-math font passed as the math font fails with a clear exception naming the file and the
  reason; it never lays out badly. CFF support is the planned follow-up.
- **MathML input** (HTML converter, Blade, inline markup). Planned for an early post-1.0 release. latex2mathml-php
  may later give TeX input through it. Neither is in this release.
- Big operators with limits (∑, ∫, ∏ with under/over scripts), accents and over/under bars, matrices and tables,
  horizontal stretching (long arrows, braces), `menclose`, equation numbering, line breaking inside a formula.
- The Word math font. Word equations stay in Cambria Math, which is the only font Word's equation editor handles
  well. The configured math font affects PDF and HTML only, and the docs say so.

## Anti-goals

- Glyphs drawn as paths where a real character would do. Letters, digits and operators are text in the PDF.
- A picture of a formula in Word, or an equation Word "repairs" away when it opens the file.
- PDF, HTML or Word output that changes for documents without equations.
- Consumer-specific names or scenarios in tests, docs or examples.

## Decisions

### `papercut/math`

Lives at `packages/math`, namespace `Papercut\Math`, released in lockstep. Built from the spike, then hardened:

- **Tree.** `Row`, `Identifier`, `Number`, `Operator` (prefix, infix or postfix form; stretchy or not), `Text`
  (upright), `Fraction`, `Root` (square root when it has no index), `Scripts` (sub, sup or both), `Fenced`. Immutable
  value objects.
- **Builder.** `Math::row()`, `frac()`, `sqrt()`, `root()`, `sup()`, `sub()`, `subSup()`, `fenced()`, `text()`. A
  plain string wherever a node is expected is tokenized.
- **Tokenizer.** Letters become identifiers, set in math italic through the Mathematical Alphanumeric block, with
  U+210E for italic h. Digit runs, with a decimal point, become numbers. Operator characters become operators.
  Whitespace is dropped, because spacing comes from the operator dictionary. A leading `−` or `±` (start of a row, or
  after an operator or opening delimiter) is prefix, with no binary spacing. Unicode super- and subscript characters
  (`²`, `₂`) stay ordinary characters attached to their base, so content written that way keeps working. Common
  function names (`sin`, `cos`, `tan`, `log`, `ln`, `exp`, `lim`, `max`, `min`) are upright identifiers.
- **Font reader.** TrueType with a `MATH` table: `head`, `hhea`, `maxp`, `hmtx`, `cmap` 4 and 12, `loca`/`glyf`,
  the `MATH` constants, italics correction, `MathKernInfo`, and vertical variants and assemblies. It also reads `GSUB`
  `ssty` for script-size alternates. It rejects CFF and fonts without `MATH`, as above.
- **Layout.** TeX-style boxes: width, ascent, descent and a display list of `Glyph(gid, ?text, x, y, size)` and
  `Rule(x, y, w, h)`. `text` is the source characters. It is null only for variant and assembly pieces, which have no
  code point. Display and inline (compact) styles, script levels with the font's scale-down percentages, and cramped
  style.
- **Hardening beyond the spike.**
  - The full MathML Core operator dictionary in place of the spike's short hand-written one.
  - MathKern cut-ins and `ssty` alternates.
  - Placement of the root index: the spike's `∛` is cramped against the radical sign.
  - A defined rule for a number next to a fraction (mixed numbers such as `3 ½`) in place of the spike's heuristic.
- **Writers.** `SvgRenderer`, from the spike. `MathmlWriter` writes `<math>` with `mi`, `mn`, `mo` (with `form` where
  it matters), `mtext`, `mfrac`, `msqrt`, `mroot`, `msub`, `msup` and `msubsup`, and stretchy `mo` for fences.
  `OmmlWriter` writes `m:oMath` content: `m:r` runs in Cambria Math, `m:f`, and `m:rad` with `m:degHide` and an empty
  `m:deg` for square roots. It writes `m:sSup`, `m:sSub` and `m:sSubSup`, `m:d` with `begChr`/`endChr`, and upright
  text as a normal-style run. Both writers are public, so an app on another Word library can use them.

### Papercut core

- `papercut/core` requires `papercut/math`.
- **Block.** A new node, `Equation`, built from math nodes or strings, e.g. `Equation::of('x = ', Math::frac(...))`.
  It takes the theme style `equation`, which sets margins, font size, colour and alignment. The default is
  left-aligned, at the body text size. An equation never splits across pages; one taller than the page follows the
  existing rule for an over-tall leaf.
- **Inline.** `Span::math(string|Node ...$parts)`, laid out in compact style. Like a badge it is one unbreakable item,
  but with its own ascent and descent: `TextLayouter`'s line box already takes each item's metrics, so a tall fraction
  grows its line. Uneven line spacing around a tall inline formula is expected; Word does the same.
- **Font.** The math font is part of the font setup: a path to a TrueType math font, default STIX Two Math. It is
  registered with the PDF backend like any other face. In Laravel it is a `papercut.fonts` entry (the exact key is
  the builder's call).
- **PDF painting.** Glyphs that carry text become ordinary text runs in the math font, so they're selectable and
  subset like any text. Glyphs without text (stretched variants and assembly pieces) are drawn as paths from their
  outlines, through a new path op or the existing SVG op. Rules are rects. This needs no php-pdf change, so the
  release is not blocked on upstream. An upstream PR adding "show glyph by ID" to php-pdf (personal fork, like
  #53–#56) later turns the path pieces into real glyphs, with no API change.
- **Copy and paste.** Text copied from a PDF formula gives the math italic code points (𝑥, not x). That is what the
  glyphs are. Tagged PDF can add `ActualText` later. Note it in the docs.

### Word

- Equations go in through `DocxPatcher`, like column breaks and run borders: the mapper writes a marker run, and the
  patch replaces it with the OMML from `OmmlWriter`. The patch also declares `xmlns:m` on each part it touches.
- **Block:** `<w:p>` containing `<m:oMathPara>` with `<m:oMathParaPr><m:jc m:val="left|center|right"/>` from the
  equation's alignment, then `<m:oMath>`. The paragraph holds nothing else. Always write `m:jc`; never rely on the
  document default, which is `centerGroup`.
- **Inline:** `<m:oMath>` between the ordinary runs of the paragraph.
- Equations inside table cells and list items use the same forms.

### HTML export

- `MathmlWriter` output: `display="block"` (aligned from the equation style) or inline.
- The `math` element uses the configured math font when the export embeds or links fonts. Otherwise it uses the
  `math` generic family. HTML export is a convenience output; no further work there.

### Blade

- `<x-doc::equation :math="...">` for block and `<x-doc::math :math="...">` for inline, each taking a built node or a
  string.
- *(My call, flagged:)* no tag vocabulary such as `<x-doc::frac>` in this release. MathML is the tag syntax for math,
  and MathML input is planned for early post-1.0. A home-made tag set now would be replaced by it.

## Examples and docs

- A docs page, "Equations": the builder, block and inline use, the vocabulary, the math font and its TrueType limit,
  Word's Cambria Math, and copy-paste behaviour.
- The `papercut/math` README, written for people using it on its own: layout plus the SVG, MathML and OMML writers.
- Add equations to an existing example where they read naturally, or add a short generic one. Mind the Letter/A4
  balance of the examples.
- NOTICE.md: STIX Two Math (OFL 1.1). Check the bundled OFL text and copyright line against the upstream STIX Two
  release; the spike wrote them from memory.
- CHANGELOG entry.

## Verification

- `papercut/math` unit tests:
  - `MATH` constants read correctly from STIX Two Math.
  - Tokenizer classes and prefix-minus detection.
  - Layout metrics for each construct.
  - Snapshot tests of the SVG, MathML and OMML writers for the fixture set.
  - Rejection of CFF and non-math fonts.
- Fixture set (generic):
  - the quadratic formula (display and inline)
  - slope with subscripts
  - `∛27 = 3`
  - the mixed number `3 ½`
  - `a/b = c`
  - `density = mass/volume` with upright words
  - `(a/b)²` and `(1 + 1/n)ⁿ`
  - `x₁²` and `e^(−kt)`
  - a nested radical
  - a tall bracket
- Core:
  - goldens for block and inline equations
  - inline line-box growth
  - no page split
  - existing goldens unchanged
- Word:
  - The docx opens in LibreOffice with live formulas that match the PDF.
  - The Google Docs round trip through the Drive connector, as for inline badges.
  - A manual check in Word, by the user: click into each formula and edit it. Word isn't installed in this
    environment.
- Visual review of the fixture PDF pages, as in the spike.

## Left to the builder

- Branch: `math`, starting from `spike/math` (the spike is the base, not a throwaway). Suggested order:
  1. Harden `papercut/math` and its writers.
  2. Block equations in PDF, HTML and Word.
  3. Inline equations.
  4. Laravel, docs and examples.
- The exact core API names (`Equation::of` or another constructor; the `Span::math` signature), the theme `equation`
  style, and the Laravel font key.
- How text-bearing glyphs reach a text run. The math italic code points map through the math font's `cmap`, so
  ordinary text runs should work. Verify that shaping or ligatures don't alter them.
- Missing glyphs in the math font (e.g. CJK in upright text): fall back the way Papercut does for text, or report it
  through fidelity notes. Never crash, never drop silently.
- The php-pdf "show glyph by ID" PR. It can run in parallel and is not a release gate.
