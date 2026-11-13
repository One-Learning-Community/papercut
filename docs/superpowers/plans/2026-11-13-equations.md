# Equations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Block and inline equations (fractions, roots, scripts, stretchy fences) in Papercut's PDF, Word and HTML
output, built on a new standalone `papercut/math` package hardened from the `spike/math` port of ziamath.

**Architecture:** `packages/math` lays out a formula tree with the OpenType `MATH` table of a TrueType math font
(STIX Two Math bundled) into a `Box`: metrics plus a display list of `Glyph`s and `Rule`s. It also writes the same tree
as SVG, MathML and OMML. Papercut core adds an `Equation` node and `Span::math()`. They paint the `Box` as text runs,
rects and SVG paths in the PDF. HTML export writes MathML, and the Word export patches OMML in through `DocxPatcher`
marker runs.

**Tech Stack:** PHP 8.4, PHPUnit 13, PHPStan, dskripchenko/php-pdf (via core's backend), dskripchenko/php-docx (via
`papercut/docx`), Laravel components, VitePress docs in `site/`.

**Spec:** `docs/superpowers/specs/2026-11-13-equations-design.md`

## Global Constraints

- Branch `math` (already created from `spike/math`) in worktree `~/work/olc/papercut-spike-math`. Never touch
  `~/work/olc/php-pdf`, the user's main checkout.
- `papercut/math` depends on nothing in Papercut and nothing outside PHP itself (`php: ^8.4`). Namespace
  `Papercut\Math\`. Versioned in lockstep: `branch-alias dev-main: 0.10.x-dev`, and core requires it as `^0.10` through
  a path repository, like `docx` requires core.
- The first release reads TrueType (`glyf`) math fonts only. A CFF font, or a font without a `MATH` table, throws
  `Papercut\Math\Exception\UnsupportedFont` with the file path and the reason.
- Word equations always use Cambria Math. The configured math font affects PDF and HTML only.
- Glyphs that carry text are text in the PDF. Only glyphs without a usable code point are drawn as paths.
- Block OMML: `<w:p>` holding only `<m:oMathPara><m:oMathParaPr><m:jc m:val="left|center|right"/></m:oMathParaPr><m:oMath>…`.
  Always write `m:jc`. Inline OMML: `<m:oMath>` between runs. Square roots: `m:rad` with `<m:radPr><m:degHide m:val="1"/></m:radPr>` and an empty `<m:deg/>`.
- Output for documents without equations must not change. Existing goldens and snapshots stay byte-identical.
- No consumer-specific names in code, tests, docs or examples.
- Commits end with `Co-Authored-By: Claude <model> <noreply@anthropic.com>` and **no** `Claude-Session` line or session URL.
- Goldens may change before 1.0 only where an example gains an equation; report each changed golden.

## Review Focus

1. **Characters the math font lacks.** E.g. `Math::text('密度')` or an emoji. They must render through Papercut's
   font fallback in the PDF, or get a fidelity note. No crash, no silent drop. Test in Task 10.
2. **Empty slots.** `Math::frac('', '2')`, `Math::sqrt('')`, `Math::sup('x', '')` must lay out (the bar or radical is
   still drawn) and write valid MathML and OMML with empty containers. Tests in Tasks 2, 6 and 7.
3. **XML specials in parts.** `Math::text('a < b & c')` and the operators `<` and `&` must be escaped in MathML, OMML
   and HTML export. Tests in Tasks 6 and 7.
4. **An equation wider than its box**, e.g. a long formula in a narrow table cell. It is laid out at its natural width
   and overflows like an overlong word: no exception, no line break inside. Tests in Tasks 10 (block) and 13 (inline).
5. **Colour and size from context.** An equation inside a heading, or in coloured or dark-theme text, takes the
   surrounding font size and colour in every output (PDF text runs and rects, MathML by CSS inheritance, OMML runs
   with `w:color` and `w:sz`). Tests in Tasks 10, 11 and 12.

---

## Part A: `papercut/math`

### Task 1: Production package shell, font validation, CI and split

**Files:**
- Modify: `packages/math/composer.json`, `packages/math/phpunit.xml.dist`
- Create: `packages/math/phpstan.neon`, `packages/math/LICENSE`, `packages/math/README.md` (stub; finished in Task 8), `packages/math/src/Exception/UnsupportedFont.php`, `.github/workflows/math.yml`
- Modify: `packages/math/src/Font/TrueTypeFont.php`, `.github/workflows/split-packages.yml` (matrix entry `{ dir: math, repo: papercut-math }`), `NOTICE.md`, `packages/math/resources/fonts/OFL.txt`
- Move: `packages/math/spike/fixtures.php` → `packages/math/tests/Fixtures.php` (class `Fixtures` with `public static function all(): array<string, Node>`). Delete the rest of `packages/math/spike/`.
- Test: `packages/math/tests/Font/TrueTypeFontTest.php`

**Interfaces:**
- Produces: `TrueTypeFont::fromFile(string $path): TrueTypeFont` throws `UnsupportedFont`. `UnsupportedFont extends \InvalidArgumentException` with `public readonly string $path` and messages `"<path> is a CFF (OpenType/PostScript) font; papercut/math reads TrueType math fonts only for now"` and `"<path> has no MATH table, so it is not a math font"`. Also `Math::stixTwoMathPath(): string` and `Fixtures::all()`.

- [ ] **Step 1:** Make `composer.json` match `packages/docx/composer.json` in shape: real description ("Math layout for PHP: fractions, roots, scripts and stretchy delimiters from an OpenType MATH font, with SVG, MathML and Word OMML writers."), homepage, support, authors, keywords, `require-dev` phpunit `^13.4` and phpstan `^2.3`, scripts `test`/`analyse`, `branch-alias`.
- [ ] **Step 2:** Write the failing tests: `test_rejects_cff_font`, using any `.otf` with a `CFF ` table you generate by writing a minimal sfnt with tag `OTTO` to a temp file; assert the `UnsupportedFont` message contains "CFF". `test_rejects_font_without_math_table`, using `packages/core/resources/fonts/Inter/Inter-Regular.ttf` copied into `tests/fixtures/`; the message contains "no MATH table". `test_stix_two_math_opens`.
- [ ] **Step 3:** Run `vendor/bin/phpunit --filter TrueTypeFontTest`. Expected: the two rejection tests FAIL.
- [ ] **Step 4:** Implement the checks in `TrueTypeFont::fromFile`: sfnt tag `OTTO` or a `CFF `/`CFF2` table means CFF; a missing `MATH` table means not a math font.
- [ ] **Step 5:** Run the tests. Expected: PASS.
- [ ] **Step 6:** Replace `OFL.txt` with the exact OFL 1.1 text and the copyright line from the upstream STIX Two release (github.com/stipub/stixfonts). Add a "STIX Two Math" section to `NOTICE.md` in the style of the Inter and Cousine sections.
- [ ] **Step 7:** Add `.github/workflows/math.yml`, modelled on `docx.yml` without the bench steps, and the split matrix entry. Run `composer analyse` in `packages/math` until it is clean at the level other packages use.
- [ ] **Step 8:** Commit: `feat(math): production package shell, font validation, CI and split`.

### Task 2: Immutable operator forms, function names, empty slots, mixed numbers

**Files:**
- Modify: `packages/math/src/Node/Operator.php`, `src/Node/Row.php`, `src/Tokenizer.php`, `src/Math.php`
- Create: `packages/math/src/Node/Forms.php`
- Test: `packages/math/tests/TokenizerTest.php`, `packages/math/tests/Layout/RowTest.php`

**Interfaces:**
- Produces:
  - `Operator` becomes fully readonly. Its public `?string $form` property is removed.
  - `Forms::of(list<Node> $children): list<?string>` gives each child's operator form (`prefix`, `infix` or `postfix`), or null for non-operators.
  - `Row::forms(): list<?string>`. Layout and both writers use it.
  - `Identifier` gains `public readonly bool $upright`. Function names are upright identifiers.

- [ ] **Step 1:** Write the failing tests:
  - `test_leading_minus_is_prefix`: `Row::forms()` of `Math::row('−b ± c')` is `['prefix', null, 'infix', null]`.
  - `test_minus_after_operator_is_prefix`: in `a = −b`, `−` is prefix.
  - `test_minus_after_open_paren_is_prefix`.
  - `test_binary_minus_is_infix`.
  - `test_function_names_upright`: `sin`, `cos`, `tan`, `log`, `ln`, `exp`, `lim`, `max` and `min` each tokenize to one upright identifier, and `sinx` tokenizes to `sin` + `x`.
  - `test_unicode_scripts_stay_attached`: `b²` gives one identifier whose text keeps `²`.
  - `test_whitespace_only_is_empty_row`: `Math::row('  ')` lays out to width 0 without errors.
  - `test_empty_fraction_slots_lay_out`: `Math::frac('', '2')` has width > 0 and draws one `Rule`.
  - `test_empty_sqrt_lays_out`.
  - `test_mixed_number_gap`: in `Math::row('3', Math::frac('1', '2'))` the fraction starts at least `3/18 em` after the `3`. The rule: a thin space (3/18 em) between a number or identifier and a following or preceding fraction, unless an operator sits between them.
- [ ] **Step 2:** Run them. Expected: they FAIL where the spike's mutable form or its heuristics differ.
- [ ] **Step 3:** Implement `Forms::of`. An operator is prefix when it is first, or follows an operator or an opening fence. It is postfix when it is last and follows a non-operator. Otherwise it is infix. Tokenize function names greedily from the list above, longest first. Remove `Operator::$form`.
- [ ] **Step 4:** Run the whole math suite. Expected: PASS.
- [ ] **Step 5:** Commit: `refactor(math): immutable operator forms, function names, empty slots`.

### Task 3: The full operator dictionary

**Files:**
- Modify: `packages/math/src/OperatorDictionary.php`
- Create: `packages/math/resources/operators.php` (generated data: `array<string, array{form: string, lspace: int, rspace: int, stretchy: bool, symmetric: bool, largeop: bool, movablelimits: bool}>` keyed by `char|form`), `packages/math/bin/gen-operators.php`
- Test: `packages/math/tests/OperatorDictionaryTest.php`

**Interfaces:**
- Produces: `OperatorDictionary::lookup(string $char, string $form): array{lspace: int, rspace: int, stretchy: bool, symmetric: bool}`. Spaces are in 1/18 em. When the char has no entry for that form, use MathML Core's fallback order (infix, then postfix, then prefix), then the default `5/18` each side. The existing `spacing()` and `isFence()` callers move to `lookup()`.

- [ ] **Step 1:** Write the failing tests:
  - `=` infix is `lspace 5, rspace 5`.
  - `+` infix is `4, 4`.
  - `,` infix is `0, 3`.
  - `(` prefix is `0, 0` and stretchy.
  - `±` prefix is `0, 0`.
  - `∑` prefix is `largeop`.
  - An unknown char (`☃`) gives `5, 5`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Write `bin/gen-operators.php` to transcribe the MathML Core operator dictionary (W3C MathML Core, Appendix B "Operator Dictionary") into `resources/operators.php`. If transcribing from ziamath's `operators.py` is easier, use that and say so in its header comment (MIT). Cache the loaded array in a static.
- [ ] **Step 4:** Run the whole math suite, then `php tests/render-fixtures.php` (Task 8 creates it; until then, the old spike demo command). Check the quadratic's spacing is unchanged by eye.
- [ ] **Step 5:** Commit: `feat(math): MathML Core operator dictionary`.

### Task 4: Script alternates (`ssty`), MathKern and cramped style

**Files:**
- Create: `packages/math/src/Font/Gsub.php`, `packages/math/src/Font/MathKern.php`
- Modify: `src/Font/TrueTypeFont.php`, `src/Font/MathTable.php`, `src/Layout/Context.php` (add `bool $cramped`), `src/Node/Scripts.php`, `src/Node/Fraction.php`, `src/Node/Root.php`, `src/Node/Identifier.php`, `src/Node/Number.php`
- Test: `packages/math/tests/Font/GsubTest.php`, `packages/math/tests/Layout/ScriptsTest.php`

**Interfaces:**
- Produces:
  - `TrueTypeFont::scriptVariant(int $gid, int $level): int`, the `ssty` alternate for script level 1 or 2, else `$gid`.
  - `MathTable::kern(int $gid, string $corner, float $height): float` for the corners `topRight`, `topLeft`, `bottomRight` and `bottomLeft`, in font units.
  - `Context::cramped(): self`.

- [ ] **Step 1:** Write the failing tests.
  - `test_ssty_alternate_for_prime`: `scriptVariant(gid('′'), 1)` differs from `gid('′')` in STIX Two Math. First confirm with a quick dump of STIX's `ssty` coverage; if prime is not covered, use a glyph that is, and name it in the test.
  - `test_math_kern_read`: some glyph in STIX has a non-zero `topRight` kern. Find one, e.g. math italic `f` (U+1D453), and assert the value.
  - `test_cramped_superscript_lower`: a superscript inside a fraction's denominator sits lower than the same superscript at top level (TeX `superscriptShiftUpCramped`).
  - `test_script_glyphs_use_ssty`: the layout of `Math::sup('x', '′')` contains the alternate gid.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement a `GSUB` reader for feature `ssty`, lookup types 1 (single) and 3 (alternate, index `level − 1`, clamped). Implement `MathKernInfo` reading and the TeX/MathML Core cut-in rule (the kern at the corner at the relevant heights, summed for base and script). Denominators, radicands and subscripts get cramped style.
- [ ] **Step 4:** Run the whole math suite. Expected: PASS. Make sure `Glyph::$text` stays the source character when a glyph is an `ssty` alternate; Task 10 relies on it.
- [ ] **Step 5:** Commit: `feat(math): ssty script alternates, MathKern, cramped style`.

### Task 5: Root index placement

**Files:**
- Modify: `packages/math/src/Node/Root.php`
- Test: `packages/math/tests/Layout/RootTest.php`

**Interfaces:**
- Consumes: `MathTable` constants `radicalKernBeforeDegree`, `radicalKernAfterDegree` and `radicalDegreeBottomRaisePercent` (a plain percent, not font units: the spike's bug).

- [ ] **Step 1:** Write the failing tests for `Math::root('27', '3')` at 12pt. Let `surdX` be the first surd glyph's x.
  - The index's left edge is `kernBeforeDegree` from the box start.
  - The surd starts at `index width + kernBefore + kernAfter` (kernAfter is negative).
  - The index's baseline is raised by `raisePercent/100 ×` (radical ascent + descent) above the radical's bottom.
  - The index's ink doesn't overlap the surd's ink box above the tick, by `bbox` comparison.
- [ ] **Step 2:** Run it. Expected: FAIL (the spike's index sits low and tight).
- [ ] **Step 3:** Fix `Root::layout` to place the index per MathML Core §3.3.3 (mroot).
- [ ] **Step 4:** Run the tests, render the fixtures, and view `∛27` and the nested radical as PNGs with the Read tool.
- [ ] **Step 5:** Commit: `fix(math): root index placement`.

### Task 6: `MathmlWriter`

**Files:**
- Create: `packages/math/src/Render/MathmlWriter.php`
- Test: `packages/math/tests/Render/MathmlWriterTest.php`, `packages/math/tests/snapshots/mathml/<fixture>.xml`

**Interfaces:**
- Produces: `MathmlWriter::write(Node $root, bool $display = true): string`. It returns `<math xmlns="http://www.w3.org/1998/Math/MathML" display="block|inline">…</math>`.
  - Identifiers: `<mi>`. Upright ones and multi-letter function names get `mathvariant="normal"`. Math italic code points are written as the plain letter, because the browser italicises single-letter `mi`.
  - Numbers: `<mn>`.
  - Operators: `<mo>`, with `form` only when `Forms` and the default disagree, and `stretchy="true"` on fences.
  - The rest:
    - `Text` → `<mtext>`
    - `Fraction` → `<mfrac>`
    - `Root` without an index → `<msqrt>`, with one → `<mroot>` (radicand, then index)
    - `Scripts` → `<msub>`, `<msup>` or `<msubsup>`
    - `Fenced` → `<mrow>` of open `mo`, content and close `mo`
    - `Row` → `<mrow>` when it has more than one child

- [ ] **Step 1:** Write failing snapshot tests, one per `Fixtures::all()` entry, plus:
  - `test_escapes_xml_specials`: `Math::text('a < b & c')` and the operators `<` and `&` come out as `&lt;` and `&amp;`.
  - `test_empty_slots`: `Math::frac('', '2')` gives `<mfrac><mrow></mrow><mn>2</mn></mfrac>`.
  - `test_output_parses`: every snapshot loads with `\Dom\XMLDocument::createFromString`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement. Write the snapshots on the first run (`UPDATE_SNAPSHOTS=1`) and review them by eye.
- [ ] **Step 4:** Run them. Expected: PASS.
- [ ] **Step 5:** Commit: `feat(math): MathML writer`.

### Task 7: `OmmlWriter`

**Files:**
- Create: `packages/math/src/Render/OmmlWriter.php`
- Test: `packages/math/tests/Render/OmmlWriterTest.php`, `packages/math/tests/snapshots/omml/<fixture>.xml`

**Interfaces:**
- Produces:
  - `OmmlWriter::oMath(Node $root, ?RunFormat $format = null): string` returns `<m:oMath>…</m:oMath>`, with no namespace declarations; the caller declares `xmlns:m` and `xmlns:w`.
  - `OmmlWriter::oMathPara(Node $root, string $jc = 'left', ?RunFormat $format = null): string` returns the `m:oMathPara` form from Global Constraints.
  - `final readonly class RunFormat(?string $colorHex = null, ?int $sizeHalfPoints = null)`, in `src/Render/RunFormat.php`.
  - Every `m:r` has `<w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/>` plus `w:color` and `w:sz`/`w:szCs` from `RunFormat`, then `<m:t xml:space="preserve">`.
  - Upright identifiers and `Text` runs get `<m:rPr><m:sty m:val="p"/></m:rPr>` before `w:rPr`.
  - Math italic code points are written as plain letters; Word italicises them itself.
  - `Scripts` → `m:sSub`/`m:sSup`/`m:sSubSup` with `m:e`, `m:sub`, `m:sup`.
  - `Fenced` → `m:d` with `<m:dPr><m:begChr m:val="("/><m:endChr m:val=")"/></m:dPr><m:e>…</m:e>`.
  - `Fraction` → `m:f` with `m:num`/`m:den`.
  - `Root` → `m:rad` as in Global Constraints. With an index, `<m:radPr><m:degHide m:val="0"/></m:radPr><m:deg>…</m:deg><m:e>…</m:e>`.

- [ ] **Step 1:** Write failing snapshot tests per fixture, plus:
  - `test_square_root_hides_degree`: asserts `<m:degHide m:val="1"/>` and `<m:deg/>`.
  - `test_para_always_writes_jc`, for `left`, `center` and `right`.
  - `test_escapes_xml_specials`.
  - `test_empty_slots`: `<m:num></m:num>` is present and valid.
  - `test_run_format`: colour and size reach every `w:rPr`.
  - `test_output_parses`: each snapshot, wrapped in an element that declares `m` and `w`, parses.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement and write the snapshots.
- [ ] **Step 4:** Make a throwaway docx check script, `packages/math/bin/omml-check.php`, that writes a minimal docx with every fixture (one `<w:p>` per `oMathPara`). Convert it with `soffice --headless --convert-to pdf`, then `pdftoppm -png -r 110`, and view the PNG with the Read tool. Every formula must render as math, not as raw text.
- [ ] **Step 5:** Commit: `feat(math): OMML writer`.

### Task 8: SVG renderer polish, fixture renderer and README

**Files:**
- Modify: `packages/math/src/Render/SvgRenderer.php`
- Create: `packages/math/tests/render-fixtures.php` (writes `tests/out/<fixture>.svg` plus `index.html`, gitignored), `packages/math/README.md`
- Test: `packages/math/tests/Render/SvgRendererTest.php`

**Interfaces:**
- Produces: `SvgRenderer::render(Box $box, float $pad = 0.0): string`, unchanged, and `SvgRenderer::glyphPaths(list<Glyph> $glyphs): string`. The second returns an `<svg>` fragment containing only those glyphs, as `<path>`s, in the box's coordinates (y down, baseline 0) with `viewBox` set to the box. Task 10 uses it for glyphs without text.

- [ ] **Step 1:** Write failing tests:
  - `test_glyph_paths_only_given_glyphs`: two glyphs give two `<path>` elements.
  - `test_render_parses`: the output parses as XML.
- [ ] **Step 2:** Implement, then run the tests. Expected: PASS.
- [ ] **Step 3:** Write the README for people using the package on its own:
  - install
  - builder
  - `Math::layout`
  - the display list (coordinates, units, `Glyph::$text` meaning)
  - the three writers
  - fonts (STIX Two Math default, TrueType-only limit, `UnsupportedFont`)
  - the operator dictionary source
  - credits (ziamath MIT, STIX OFL)
- [ ] **Step 4:** Commit: `feat(math): glyph path rendering, fixture renderer, README`.

## Part B: Papercut core, block equations

### Task 9: Math font setup and the core dependency

**Files:**
- Modify: `packages/core/composer.json`: a path repository `../math` (versions `papercut/math: 0.10.x-dev`) and `require "papercut/math": "^0.10"`. Do the same in `packages/docx/composer.json` and `packages/laravel/composer.json`, which already list `../core`.
- Modify: `packages/core/src/Text/FontSet.php`, `packages/core/src/Text/FontRegistry.php`
- Test: `packages/core/tests/Text/MathFontTest.php`

**Interfaces:**
- Produces:
  - `FontSet::withMathFont(string $path): self` and `FontSet::mathFontPath(): string`. The default is `Math::stixTwoMathPath()`.
  - `FontSet::MATH_FAMILY = 'Papercut Math'`, the family the math face is registered under for PDF text runs.
  - `FontRegistry::mathFont(): array{handle: CachedFont, font: \Papercut\Math\Font\TrueTypeFont}`, opened once per registry. An `UnsupportedFont` for a bad path surfaces when the document is built, not at first draw.

- [ ] **Step 1:** Write failing tests:
  - `test_default_math_font_is_stix`.
  - `test_custom_math_font_path_used`: copy STIX to a temp path, set it with `withMathFont`, and check that `mathFont()['font']` opens it.
  - `test_non_math_font_rejected_early`: Inter as the math font throws `UnsupportedFont` when the registry is built from the set.
  - `test_documents_without_equations_do_not_open_math_font`: a lazy check, by counting opens through a static or by not registering the face until first use.
- [ ] **Step 2:** Run `composer update papercut/math` in core, docx and laravel, then the tests. Expected: FAIL.
- [ ] **Step 3:** Implement. Register the face lazily, so the PDFs of documents without equations stay byte-identical.
- [ ] **Step 4:** Run the full core suite. Expected: PASS, with goldens and snapshots unchanged.
- [ ] **Step 5:** Commit: `feat(core): configurable math font, papercut/math dependency`.

### Task 10: `Equation` node and PDF layout

**Files:**
- Create: `packages/core/src/Equation.php`, `packages/core/src/Layout/EquationLayouter.php`, `packages/core/src/Layout/MathPainter.php`
- Modify: `packages/core/src/Layout/LayoutEngine.php` (register the layouter), `packages/core/src/Decorator/DefaultStyles.php` (add style `equation`: `margin` bottom like a paragraph, nothing else), and wherever node names are listed for debugging and fidelity.
- Test: `packages/core/tests/Node/EquationTest.php`, `packages/core/tests/Layout/EquationLayouterTest.php`, `packages/core/tests/Feature/EquationPdfTest.php`

**Interfaces:**
- Consumes: `Math::layout()`, `Box`, `Glyph`, `Rule`, `SvgRenderer::glyphPaths()`, `FontRegistry::mathFont()`.
- Produces:
  - `final class Equation implements Styled` with `HasStyle`, named style `equation`, and `public static function of(string|\Papercut\Math\Node\Node ...$parts): self`. A single non-row node is used as the root; otherwise `Math::row(...$parts)`. Also `public function math(): \Papercut\Math\Node\Node` and `debugName(): 'equation'`.
  - `MathPainter::paint(Fragment $f, Box $box, float $x, float $baseline, Color $color, CachedFont $handle, TrueTypeFont $font): void`. A glyph becomes a `TextRun` when `$glyph->text` is one code point and `$font->gid(ord) === $glyph->gid`. Every other glyph goes, batched, into one `DrawSvg` built from `glyphPaths()`, tinted with `$color`. A `Rule` becomes a `Rect` filled with `$color`.

- [ ] **Step 1:** Write failing tests:
  - `test_quadratic_ops`: the fragment for the quadratic fixture has one fraction-bar `Rect`, an overbar `Rect`, and `TextRun`s whose concatenated text contains `𝑥` and `4`.
  - `test_height_is_box_height`: the fragment height is ascent + descent + vertical margins.
  - `test_alignment`: with `textAlign: center`, the ops are offset by `(width − box width) / 2`.
  - `test_does_not_split`: when the space left is too short and the page isn't fresh, the result is `doesNotFit`.
  - `test_wider_than_box_overflows` (Review Focus 4): in a 40pt-wide constraint the layout completes, and the fragment's ops extend beyond 40pt.
  - `test_takes_text_colour_and_size` (Review Focus 5): with color red and font size 20, every `TextRun` and `Rect` is red, and the box is laid out at 20pt.
  - `test_missing_glyph_falls_back` (Review Focus 1): `Equation::of(Math::text('密度'))` completes. Its run uses a fallback family when one is configured; otherwise a fidelity note names the missing characters.
  - Feature `test_pdf_text_is_selectable`: render a document with the quadratic, and `PdfInspector::text()` contains `𝑥` and `𝑏`.
  - Feature `test_stretched_fence_drawn_as_svg`: `(a/b)²` produces a `DrawSvg` op.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement. Lay out at the computed font size, display style, using `FontRegistry::mathFont()`. Cache the `Box` per node and size.
- [ ] **Step 4:** Run the core suite. Expected: PASS, with existing goldens and snapshots unchanged.
- [ ] **Step 5:** Commit: `feat(core): Equation node and PDF layout`.

### Task 11: HTML export of block equations

**Files:**
- Modify: `packages/core/src/Html/HtmlWriter.php` (a `match` arm next to `QrCode`), `packages/core/src/Html/Css.php` or `Stylesheet.php` (the `math` font rule)
- Test: `packages/core/tests/Html/EquationHtmlTest.php`

**Interfaces:**
- Consumes: `MathmlWriter::write($node->math(), true)`.

- [ ] **Step 1:** Write failing tests:
  - `test_block_equation_is_mathml`: the output contains `<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"`, wrapped in a `div` carrying the equation style's margins and `text-align`.
  - `test_math_font_rule`: with fonts embedded or linked, a `@font-face` for the math font exists and `math { font-family: "Papercut Math", math; }`. With fonts `none`, there's no `@font-face` but the rule stays.
  - `test_colour_inherits` (Review Focus 5).
  - `test_escapes` (Review Focus 3, through the writer).
- [ ] **Step 2:** Run them, implement, run again. Expected: PASS.
- [ ] **Step 3:** Commit: `feat(core): equations in HTML export as MathML`.

### Task 12: Word export of block equations

**Files:**
- Modify: `packages/docx/src/Walk/DocxVisitor.php` (an `Equation` arm), `packages/docx/src/Package/DocxPatcher.php`, `packages/docx/src/Package/PatchSpec.php`
- Create: `packages/docx/src/Map/EquationMapper.php`
- Test: `packages/docx/tests/Map/EquationMapperTest.php`, `packages/docx/tests/Package/DocxPatcherMathTest.php`, plus a new golden fixture via `GoldenDocxTest`

**Interfaces:**
- Consumes: `OmmlWriter::oMathPara()`, `OmmlWriter::oMath()`, `RunFormat`.
- Produces:
  - `DocxPatcher::MATH = 'papercut-math-'`, and `PatchSpec::withMath(int $id, string $omml): self`.
  - A mapper writes a paragraph whose only run has text `papercut-math-<id>`.
  - `patch()` replaces that whole `<w:r>…</w:r>`, and for block equations also strips nothing else from the paragraph. It throws if a marker is left unreplaced, and it adds `xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"` to the root element of every part where it replaced a marker.
  - Inline (Task 14) uses the same marker with `oMath`.

- [ ] **Step 1:** Write failing tests:
  - `test_block_equation_paragraph`: the patched `document.xml` contains `<w:p>` (with the paragraph style's spacing) whose only child after `w:pPr` is `m:oMathPara` with `m:jc m:val="left"`.
  - `test_centered_equation_jc`.
  - `test_namespace_declared`.
  - `test_in_table_cell_and_list_item`: same form inside `w:tc` and a numbered list paragraph.
  - `test_colour_and_size` (Review Focus 5): `RunFormat` comes from the computed style.
  - `test_unreplaced_marker_throws`.
  - A golden for a small fixture document holding all `Fixtures::all()` formulas.
- [ ] **Step 2:** Run them, implement, run again. Expected: PASS. The other docx goldens are unchanged.
- [ ] **Step 3:** Convert the fixture docx with `soffice --headless --convert-to pdf` and view it as a PNG. Every formula must be live math, left-aligned, and look like the Papercut PDF.
- [ ] **Step 4:** Commit: `feat(docx): equations as native OMML`.
- [ ] **Step 5: CHECKPOINT, user Word check.** Write the fixture docx to `~/work/olc/papercut-equation-samples/papercut-equations-block.docx`. Stop and ask the user to open it in Word and confirm, for each formula:
  1. it opens as a live equation (click in, edit a character)
  2. it is left-aligned
  3. it looks like the PDF next to it (write the PDF to the same folder)
  4. Word showed no repair prompt

  Do not start Task 13 until the user answers. Fix what they report first.

## Part C: Inline equations

### Task 13: `Span::math` and inline PDF layout

**Files:**
- Modify: `packages/core/src/Span.php` (`public ?\Papercut\Math\Node\Node $math = null` and `public static function math(string|\Papercut\Math\Node\Node ...$parts): self`), `packages/core/src/Text/InlineItem.php` (`public ?Box $math = null`), `packages/core/src/Text/Itemizer.php`, `packages/core/src/Layout/TextLayouter.php` (`lineBox`, `paintLine`), `packages/core/src/Text/LineBreaker.php` (only if needed)
- Test: `packages/core/tests/Text/InlineMathTest.php`

**Interfaces:**
- Consumes: `MathPainter::paint`, `Math::layout(..., display: false)`.
- Produces: an inline-math `InlineItem` with `kind 'word'`, `box: false`, `math: Box`, and `width` = box width. Its line metrics are `[0.0, box ascent, box descent]` (no half-leading). It is never split, and it is never merged into a neighbouring run.

- [ ] **Step 1:** Write failing tests:
  - `test_inline_fraction_grows_line`: the line holding `Span::math(Math::frac('1', '2'))` is taller than a plain line, and the next line is not.
  - `test_baseline_shared`: the equation's baseline equals the text's baseline on that line.
  - `test_never_breaks_inside`: in a narrow width, the formula moves whole to the next line.
  - `test_overlong_inline_overflows` (Review Focus 4): an inline formula wider than the line sits alone and overflows; no exception.
  - `test_justified_line_with_math`.
  - `test_inline_uses_compact_style`: the fraction's numerator is at script size.
  - `test_link_wraps_math`: a math span inside a link keeps its href area.
- [ ] **Step 2:** Run them, implement, run again. Expected: PASS. All existing goldens are unchanged.
- [ ] **Step 3:** Commit: `feat(core): inline equations in PDF`.

### Task 14: Inline equations in HTML and Word

**Files:**
- Modify: `packages/core/src/Html/HtmlWriter.php` (span rendering), `packages/docx/src/Map/TextMapper.php` (the inline math marker run)
- Test: `packages/core/tests/Html/InlineMathHtmlTest.php`, `packages/docx/tests/Map/InlineMathDocxTest.php`, golden fixture update

**Interfaces:**
- Consumes: `MathmlWriter::write(..., false)`, `OmmlWriter::oMath()`, `DocxPatcher::MATH`, `PatchSpec::withMath`.

- [ ] **Step 1:** Write failing tests:
  - `test_inline_math_element`: `<math display="inline">` sits inside the paragraph, between the text.
  - `test_inline_omml_between_runs`: the patched paragraph holds `w:r`, `m:oMath`, `w:r` in order, and no `m:oMathPara`.
  - `test_inline_in_table_cell`.
- [ ] **Step 2:** Run them, implement, run again. Expected: PASS.
- [ ] **Step 3:** Add inline formulas to the fixture docx, convert with LibreOffice, and view the PNG.
- [ ] **Step 4:** Commit: `feat: inline equations in HTML and Word`.

## Part D: Laravel, docs, examples, release prep

### Task 15: Blade components and the Laravel font key

**Files:**
- Create: `packages/laravel/src/Components/Equation.php`, `packages/laravel/src/Components/Math.php`
- Modify: the component registration in `PapercutServiceProvider.php`, `packages/laravel/src/Rendering/Segments.php` (treat `<x-doc::math>` inside text like an inline badge; see the comment at line 98), `packages/laravel/src/FontConfig.php` and `packages/laravel/config/papercut.php` (key `math_font`: a path, default `null` = bundled), `PapercutManager.php` (applies `withMathFont`)
- Test: `packages/laravel/tests/Feature/EquationComponentTest.php`

**Interfaces:**
- Produces: `<x-doc::equation :math="$node" />` and `<x-doc::equation math="x = 1" />` (string, tokenized), with style attributes like other components. `<x-doc::math :math="$node" />` inside `<x-doc::p>` becomes `Span::math`. Config: `'math_font' => null`.

- [ ] **Step 1:** Write failing tests:
  - `test_block_component_renders_equation`, which checks the node tree.
  - `test_inline_component_in_paragraph_is_span_math`.
  - `test_math_font_config_applied`.
  - `test_bad_math_font_config_message`: the message names `papercut.math_font` and the reason from `UnsupportedFont`.
- [ ] **Step 2:** Run them, implement, run again. Expected: PASS.
- [ ] **Step 3:** Commit: `feat(laravel): equation components and math_font config`.

### Task 16: Docs, example, changelog

**Files:**
- Create: `site/src/guide/equations.md` (add it to the guide sidebar next to rich text)
- Modify: `site/src/guide/fonts.md` (math font section), `site/src/guide/word-export.md` (Cambria Math note), `site/src/guide/html-export.md` (MathML note), `site/src/laravel/` components page (the two components and `math_font`), `CHANGELOG.md` under `[Unreleased]`, `README.md` (package list), `CONTRIBUTING.md` (if it lists packages for release)
- Modify: one example in `examples/core/`. `paper.php` fits: add a short display formula and an inline one in running text. Keep it generic; it is A4 or Letter as it already is.
- Test: the example goldens: PDF snapshots and `packages/docx/tests/golden/examples-paper`

- [ ] **Step 1:** Write `equations.md`:
  - the builder vocabulary with a rendered image of the quadratic
  - block and inline usage in PHP and Blade
  - the tokenizer rules (italic letters, upright function names, prefix minus, Unicode `²` still fine)
  - the math font: STIX default, `withMathFont`, TrueType only, the CFF error
  - Word: live equations in Cambria Math
  - HTML: MathML
  - copy and paste gives math italic characters
  - what's not supported yet: big operators with limits, accents, matrices, MathML input (planned early post-1.0)
- [ ] **Step 2:** Update the example and regenerate its goldens. List every changed golden in the commit message.
- [ ] **Step 3:** Run the docs build (`npm run build` in `site/`). Expected: no dead links.
- [ ] **Step 4:** Commit: `docs: equations guide, example and changelog`.

### Task 17: Whole-branch verification

- [ ] **Step 1:** In each of `packages/math`, `core`, `docx` and `laravel`, run `composer test` and `composer analyse`. Expected: all green. Paste the summary lines.
- [ ] **Step 2:** Run `git diff main --stat -- '*golden*' '*snapshots*'`. Only the paper example's goldens and the new equation fixtures may change.
- [ ] **Step 3:** Google Docs round trip, as for inline badges: upload the fixture docx through the Drive connector, export it as PDF, and check every formula survives as math.
- [ ] **Step 4:** Render the fixture docx with LibreOffice once more, and view the final PDF fixture pages with the Read tool.
- [ ] **Step 5: CHECKPOINT, user Word check 2.** Write `papercut-equations-final.docx` and its PDF to `~/work/olc/papercut-equation-samples/`. Ask the user to repeat the Word check, now including the inline formulas.
- [ ] **Step 6:** Report what's left for the user. The split repo `One-Learning-Community/papercut-math` already exists, seeded with a README on 2026-11-13. The user must still: add `papercut-math` to the `SPLIT_TOKEN` fine-grained token's repositories; submit the repo to Packagist after the first split (manual); then release in lockstep. Update `CONTRIBUTING.md` § Releasing to list `papercut-math` with the other split repos (Task 16). The php-pdf "show glyph by ID" PR is separate follow-up work.
