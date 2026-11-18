# PendingMath Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One-call math output: `PendingMath::tex($tex)->svg()` and friends, wrapping the existing readers, writers and renderers.

**Architecture:** A `final readonly` value class `Quoin\Math\PendingMath` holds a `Node`, a display flag and an optional `Font`. Static constructors read input; modifiers return new instances; output methods call the existing `Render\*` classes. `png()` returns a `final readonly` `Quoin\Math\MathPng`. No existing class changes.

**Tech Stack:** PHP 8.4, PHPUnit, PHPStan; Imagick optional (PNG).

**Spec:** `docs/superpowers/specs/2026-11-17-pending-math-design.md`

## Global Constraints

- PHP floor `^8.4`; run the math suite on php@8.4 as well as the local 8.5 before pushing. Xdebug off.
- American spelling. Docs follow the beautiful-prose rules: no em dashes, no filler. Comments and docs name the actor, not the project.
- Defaults: inline, `size` 16.0 (CSS px), SVG color `'currentColor'`, PNG color `'#000000'`, `scale` 2.0, `pad` 0.0, OMML `$align` `'center'`.
- Low-level classes (`Math`, `SvgRenderer`, `PngRenderer`, writers) do not change.
- Both new classes are public API: no `@internal`, so `tests/ArchitectureTest.php` needs no change.
- Commands run from `packages/math`: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse --memory-limit=1G`.

## Review Focus

- A label with `<`, `&`, `"` or non-ASCII (`\text{a<b & "c"}`, `\alpha`): attribute must stay well-formed XML; assert the SVG loads with `DOMDocument::loadXML`.
- MathML input passed as a `\Dom\Element` from a parsed HTML document, not a string: `display="block"` on that element must still start display mode.
- Hostile TeX (`\frac{`, unknown commands, 20 KB input): `PendingMath::tex()` must not throw, and `svg()` must still return loadable XML with notes available.
- `png()` with `pad > 0`: `verticalAlign` must include the pad, matching `svg()`'s rule.
- Reusing one instance after `display()`: the original stays inline (immutability), so a template that renders both forms from one object gets both.

---

### Task 1: `PendingMath` construction, modifiers and text outputs

**Files:**
- Create: `packages/math/src/PendingMath.php`
- Test: `packages/math/tests/PendingMathTest.php`

**Interfaces:**
- Produces:
  - `final readonly class PendingMath` with private constructor `(Node $node, bool $display, ?Font $font = null)`
  - `static tex(string $tex): self` (uses `TexReader::read()`, display from `TexResult::$display`)
  - `static mathml(string|\Dom\Element $input): self` (uses `MathmlReader::read()`, display from `MathmlResult::$display`)
  - `static of(Node $node): self` (inline)
  - `display(): self`, `inline(): self`, `font(Font $font): self`
  - `isDisplay(): bool` (used by tests and later tasks)
  - `node(): Node`, `notes(): list<MathNote>`, `toMathml(): string`, `omml(string $align = 'center', ?RunFormat $format = null): string`, `toTex(): string`, `text(): string`
  - private `layoutFont(): Font` returning `$this->font ?? Math::stixTwoMath()` (Tasks 2 and 3 use it)

Note: the spec names both a static constructor and an output method `tex()`, and likewise `mathml()`. PHP allows one method per name, static or not. The constructors keep the spec's names; the outputs are **`toTex()`** and **`toMathml()`**. The other outputs keep their spec names (`svg`, `png`, `omml`, `text`, `notes`, `node`). Update the spec's API table in this task's commit.

- [ ] **Step 1: Write the failing tests** in `PendingMathTest` (namespace `Quoin\Math\Tests`):
  - `test_tex_dollars_and_brackets_start_in_display`: `PendingMath::tex('$$x$$')->isDisplay()` and `tex('\[x\]')` true; `tex('x')`, `tex('$x$')` false.
  - `test_mathml_block_starts_in_display`: string `<math display="block"><mi>x</mi></math>` true; without the attribute false; a `\Dom\Element` from `\Dom\HTMLDocument::createFromString('<p><math display="block"><mi>x</mi></math></p>', LIBXML_NOERROR)` → `querySelector('math')` true.
  - `test_of_starts_inline`.
  - `test_modifiers_return_new_instances`: `$a = tex('x'); $b = $a->display();` → `$a->isDisplay()` false, `$b` true, `$b->inline()->isDisplay()` false, `$a !== $b`.
  - `test_outputs_match_the_writers`: for `$f = '\frac{a}{b}'`: `toTex()` === `TexWriter::write(Math::tex($f))`; `text()` === `TextWriter::write(...)`; `toMathml()` === `MathmlWriter::write(..., display: false)` and after `display()` `display: true`; `omml()` === `OmmlWriter::oMath(...)`, `display()->omml('left')` === `OmmlWriter::oMathPara(..., 'left')`; `omml(format: new RunFormat(colorHex: 'C00000'))` passes the format through in both modes.
  - `test_notes_report_what_the_reader_could_not_draw`: `tex('\nosuchcommand x')->notes()` non-empty and equal to `Math::notes(Math::tex(...))`.
  - `test_hostile_tex_does_not_throw`: `tex('\frac{')`, `tex(str_repeat('{', 20000))` construct and `text()` returns a string.

- [ ] **Step 2: Run** `vendor/bin/phpunit tests/PendingMathTest.php`. Expected: FAIL, class not found.

- [ ] **Step 3: Implement** `PendingMath` per the Interfaces block. Docblock on the class: what it is, that defaults differ from `Math::layout()` (inline, 16 px), and that construction never throws on bad input.

- [ ] **Step 4: Run** the test file, then the whole suite and phpstan. Expected: PASS, no phpstan errors.

- [ ] **Step 5: Commit**

```bash
git add packages/math/src/PendingMath.php packages/math/tests/PendingMathTest.php docs/superpowers/specs/2026-11-17-pending-math-design.md
git commit -m "feat(math): PendingMath for one-call output"
```

### Task 2: `svg()`

**Files:**
- Modify: `packages/math/src/PendingMath.php`
- Test: `packages/math/tests/PendingMathTest.php`

**Interfaces:**
- Consumes: Task 1's `PendingMath`, `layoutFont()`, `text()`.
- Produces: `svg(float $size = 16.0, string $color = 'currentColor', float $pad = 0.0, ?string $label = null): string`

Approach: `$box = Math::layout($node, $size, $this->display, $this->layoutFont())`, render with `new SvgRenderer($font, $color)`, then insert the attributes right after `<svg` in the returned string (the renderer always starts with `<svg xmlns=`). Escape the label with `htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8')`. Offset: `round($box->descent + $pad, 3)`, formatted without trailing zeros (reuse the shape of `SvgRenderer::n()`; it is private, so write a small private formatter).

- [ ] **Step 1: Write the failing tests:**
  - `test_svg_matches_the_renderer_apart_from_the_root_attributes`: strip `role`, `aria-label`/`aria-hidden` and `style` attributes from the root with DOM, compare to `(new SvgRenderer(Math::stixTwoMath(), 'currentColor'))->render(Math::layout(Math::tex('x^2'), 16.0, false))` after the same DOM round trip. Repeat for `display()->svg(size: 18, color: '#123456', pad: 2)` against `display: true`, `pad: 2.0`.
  - `test_inline_svg_carries_role_label_and_vertical_align`: for `tex('x_1')->svg(pad: 1.5)`: `role="img"`, `aria-label` === `text()`, `style` === `'vertical-align:-' . <round(descent + 1.5, 3) formatted> . 'px'` using `Math::layout(...)->descent`.
  - `test_display_svg_has_no_vertical_align`: no `style` attribute.
  - `test_label_is_escaped`: `tex('\text{a<b & "c"}')->svg()` loads with `DOMDocument::loadXML`; `getAttribute('aria-label')` === `text()`. Same for `svg(label: 'é <x> "y"')`.
  - `test_empty_label_hides_the_svg`: `svg(label: '')` has `aria-hidden="true"`, no `aria-label`, no `role`.
  - `test_font_reaches_layout_and_renderer`: `font(Font::fromFile(<fixtures>/FiraMath-Regular.otf))->svg()` differs from the default `svg()` and equals the renderer output built with that font (DOM-stripped as above).
  - `test_hostile_tex_svg_is_well_formed`: `tex('\frac{')->svg()` and a 20 KB input load with `loadXML`.

- [ ] **Step 2: Run** the file. Expected: FAIL, method `svg` not found.

- [ ] **Step 3: Implement** `svg()` per the approach.

- [ ] **Step 4: Run** the file, full suite, phpstan. Expected: PASS.

- [ ] **Step 5: Commit** `feat(math): PendingMath::svg() with baseline and label`

### Task 3: `png()` and `MathPng`

**Files:**
- Create: `packages/math/src/MathPng.php`
- Modify: `packages/math/src/PendingMath.php`
- Test: `packages/math/tests/MathPngTest.php`

**Interfaces:**
- Consumes: Task 1's `layoutFont()`, `text()`.
- Produces:
  - `final readonly class MathPng` with public `string $bytes`, `float $width`, `float $height`, `float $verticalAlign`, private `string $alt`; constructor `(string $bytes, float $width, float $height, float $verticalAlign, string $alt)`.
  - `MathPng::dataUri(): string` → `'data:image/png;base64,' . base64_encode($bytes)`.
  - `MathPng::img(string $src, ?string $alt = null): string` → `<img src="…" width="W" height="H" alt="…" style="vertical-align:Vpx">`, every attribute escaped with `htmlspecialchars(…, ENT_QUOTES | ENT_HTML5, 'UTF-8')`; `$alt` null uses the stored alt.
  - `PendingMath::png(float $size = 16.0, float $scale = 2.0, string $color = '#000000', float $pad = 0.0): MathPng`.

Values: `[$pw, $ph] = getimagesizefromstring($bytes)`; `width = $pw / $scale`, `height = $ph / $scale`; `verticalAlign = -round(height - PngRenderer::baselineOffset($box, $pad, $scale) / $scale, 1)`. Width and height use the real pixel size (the renderer rounds), so `img()` never stretches the image.

- [ ] **Step 1: Write the failing tests** (class-level skip when `! PngRenderer::available()`, except `MathPng` tests that construct the value directly):
  - `test_png_matches_the_renderer`: `tex('E = mc^2')->png()->bytes` === `(new PngRenderer(Math::stixTwoMath()))->render(Math::layout(Math::tex('E = mc^2'), 16.0, false), 0.0, 2.0)`.
  - `test_png_size_and_baseline`: for `tex('x_1')->png(scale: 2.0, pad: 1.0)`: `width * 2` and `height * 2` equal `getimagesizefromstring` pixels; `verticalAlign` equals the formula above and is negative.
  - `test_png_throws_what_the_renderer_throws`: `png(color: 'not-a-color')` throws `\InvalidArgumentException`; `png(scale: 0.0)` throws `\InvalidArgumentException`.
  - `test_data_uri` and `test_img_markup`: on `new MathPng("\x89PNG", 10.5, 8.0, -2.5, 'a < b')`: `dataUri()` prefix and payload; `img('cid:f1')` === `'<img src="cid:f1" width="10.5" height="8" alt="a &lt; b" style="vertical-align:-2.5px">'`; `img('x" onload="y', 'q')` escapes the quote.

- [ ] **Step 2: Run** `vendor/bin/phpunit tests/MathPngTest.php`. Expected: FAIL.

- [ ] **Step 3: Implement** `MathPng` and `png()`.

- [ ] **Step 4: Run** the file, full suite (with and without Imagick if php@8.4 lacks it), phpstan. Expected: PASS.

- [ ] **Step 5: Commit** `feat(math): PendingMath::png() and MathPng`

### Task 4: Docs and changelog

**Files:**
- Modify: `site/src/math/README.md`, `site/src/math/output.md`, `site/src/math/recipes.md`, `CHANGELOG.md`

**Interfaces:**
- Consumes: the public API from Tasks 1 to 3, including the `toMathml()`/`toTex()` names.

- [ ] **Step 1: `README.md`**: open the usage example with `PendingMath::tex(...)->svg()`.
- [ ] **Step 2: `output.md`**: new first section "Quick output" with the API table (construction, modifiers, outputs, `MathPng`) and a sentence stating the defaults differ from `Math::layout()` (inline, 16 px, `currentColor`). The low-level sections follow unchanged.
- [ ] **Step 3: `recipes.md`**: rewrite "In a web page" (drop `inlineMath()`), "Accessible formulas" (default label; `label: ''` plus `toMathml()` for the hidden-MathML variant), "In email" (`png()->img('cid:formula-1')`), "Converting between formats" (`toMathml()`, `toTex()`), "The LaTeX look" (`font()`), "Untrusted input" (`notes()`; keep `TexReader` for the level check). Each code sample must run; check them with a scratch script.
- [ ] **Step 4: `CHANGELOG.md`**: an Unreleased "Added" entry for `PendingMath` and `MathPng`, in the file's existing format.
- [ ] **Step 5: Build the site** (`npm run docs:build` or the script in `site/package.json`) and confirm no errors.
- [ ] **Step 6: Commit** `docs(math): one-call output with PendingMath`
