# Alt Text and Inline SVG Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alt text on images and SVGs (PHP, Blade, pasted `<img alt>`) used in HTML and Word, and an opt-in HTML export option that writes SVGs inline so their text uses the same fonts as the PDF.

**Architecture:** `Image` and `Svg` get an immutable `alt()` setter and an `altText(): string` getter ('' = decorative) that applies the precedence rule once, so HTML and Word read one value. Inline SVG is a new `@internal` class `Papercut\Html\InlineSvg` that takes the sanitized SVG XML and returns HTML with IDs prefixed, `<style>` scoped and text families rewritten; `HtmlWriter::svg()` calls it when `HtmlOptions::inlineSvgs()` is true.

**Tech Stack:** PHP 8.4, ext-dom (`DOMDocument` as `SvgDocument` already uses), PHPUnit 13, PHPStan level 8, Laravel package.

**Spec:** `docs/superpowers/specs/2026-11-16-alt-text-and-inline-svg-design.md`

## Global Constraints

- Branch `fix-0.14.1` in the repository root; ships as 0.14.1. Do not push.
- PHP floor 8.4: run every suite with `php` (8.5) and `$(brew --prefix php@8.4)/bin/php` before the final commit.
- Run tests with `php -d xdebug.mode=off vendor/bin/phpunit` from the package directory; PHPStan `vendor/bin/phpstan analyse --memory-limit=1G` must be clean in each touched package.
- Tests first: write the failing test, watch it fail, then implement.
- Never use `git stash`.
- New engine classes are marked `@internal Part of Papercut's engine, not its public API. It may change in any release.`; `Image::alt()`, `Svg::alt()`, `altText()` and `HtmlOptions` additions are public API.
- `inlineSvgs` defaults to `false`; with it off, HTML output is byte-identical to today except for alt text that is now set.
- No PDF output changes. No warning for missing alt text.
- Pasted HTML never produces `Svg` nodes; do not add that.
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`; no Claude-Session line.
- Docs and notes: plain, concrete English; no marketing words.

## Review Focus

- Alt text containing `"`, `&`, `<` or line breaks: escaped in HTML attributes and Word XML, whitespace collapsed to single spaces and trimmed. (Task 1, Task 2)
- The same SVG file placed twice on one page with `inlineSvgs` on: each copy gets its own ID prefix, so gradients and `<use>` still resolve to their own copy. (Task 3)
- An SVG whose `<style>` is wrapped in CDATA or uses `url(#id)` inside a `style` attribute: references rewritten, selectors scoped. (Task 3)
- An SVG text family list naming an unregistered font first and a registered one second (`Arial, Inter`): resolves to Inter like the PDF does. (Task 4)
- Strict mode with `inlineSvgs` on and an SVG with only scopable CSS: no note is reported, so strict export does not fail. (Task 3)

---

### Task 1: Alt text on `Image` and `Svg`, used in HTML; pasted `<img alt>`

**Files:**
- Modify: `packages/core/src/Image.php`, `packages/core/src/Svg.php`
- Modify: `packages/core/src/Html/HtmlWriter.php` (`image()` ~line 740, `svg()` ~line 754)
- Modify: `packages/core/src/Markup/HtmlConverter.php` (`image()` ~line 1077)
- Test: `packages/core/tests/Html/HtmlTablesImagesTest.php`, `packages/core/tests/Html/HtmlSvgTest.php`, the HTML converter's image tests (find with `grep -rln "missing; shown as its alt" packages/core/tests`)

**Interfaces:**
- Produces: `Image::alt(string $text): self` and `Svg::alt(string $text): self` (immutable: clone, like `Image::fit()`); whitespace runs collapsed to one space and trimmed on set.
- Produces: `Image::altText(): string` — the set alt, or `''`.
- Produces: `Svg::altText(): string` — the set alt if `alt()` was called (even with `''`), else `$this->document()->title`. `''` means decorative. Track "was set" with a `?string $alt = null` property.

- [ ] **Step 1: Write the failing tests**

In `HtmlTablesImagesTest`: `test_image_alt_is_written`: `Image::bytes(TestImages::png())->alt("A  \"red\" & <blue>\n dot")` → the `<img>` has `alt="A &quot;red&quot; &amp; &lt;blue&gt; dot"`; `test_image_without_alt_is_decorative`: `alt=""`.
In `HtmlSvgTest`: `test_svg_alt_overrides_title` (SVG with root `<title>T</title>` and `->alt('Number line')` → `alt="Number line"`), `test_svg_falls_back_to_title` (→ `alt="T"`), `test_empty_alt_marks_svg_decorative` (`->alt('')` on the titled SVG → `alt=""`).
In the converter tests: `test_img_alt_is_carried_to_the_image`: `Markup::html('<img src="data:image/png;base64,…" alt="Chart">')` yields an `Image` whose `altText()` is `'Chart'`; the existing missing-image stand-in test still passes unchanged.

- [ ] **Step 2: Run them to verify they fail**

Run: `cd packages/core && php -d xdebug.mode=off vendor/bin/phpunit --filter 'alt|decorative|title'`
Expected: FAIL with `Call to undefined method Papercut\Image::alt()`.

- [ ] **Step 3: Implement**

Add the API above. In `HtmlWriter::image()` write `alt="' . self::esc($n->altText()) . '"'`; in `svg()` use `$n->altText()` in place of `$svg->title`. In `HtmlConverter::image()`, when bytes resolve, apply `->alt($el->getAttribute('alt') ?? '')` only if the attribute is present (absent stays unset).

- [ ] **Step 4: Run them to verify they pass, then the core suite and PHPStan**

Run: `cd packages/core && php -d xdebug.mode=off vendor/bin/phpunit && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: all pass (HTML goldens unchanged: no golden sets alt).

- [ ] **Step 5: Commit** — `feat(core): alt text on images and SVGs, used in HTML; pasted img alt carries through`

---

### Task 2: Alt text in Word and Blade

**Files:**
- Modify: `packages/docx/src/Map/ImageMapper.php` (raster `DocxImage` at ~line 72; `svg()` ~line 124)
- Modify: `packages/laravel/src/Components/Image.php`, `packages/laravel/src/Components/Svg.php` (`ownAttributes()` and `build()`)
- Test: the docx image tests (`grep -rln "ImageMapper\|docPr" packages/docx/tests`), `packages/laravel/tests/Feature/` image/SVG component tests

**Interfaces:**
- Consumes: `Image::altText()`, `Svg::altText()`, `Image::alt()`, `Svg::alt()` from Task 1.

- [ ] **Step 1: Write the failing tests**

Docx: `test_raster_image_alt_is_the_picture_description` — export a document with `Image::bytes(png)->alt('Chart & "key"')` and assert `word/document.xml` has a `wp:docPr` with `descr="Chart &amp; &quot;key&quot;"`; `test_svg_alt_overrides_title_in_word`; `test_decorative_image_has_no_description` (no `descr` attribute, or an empty one, whichever php-docx writes for `null`).
Laravel: `<x-doc::image src="…" alt="Chart" />` and `<x-doc::svg alt="Line">…</x-doc::svg>` build nodes whose `altText()` is `'Chart'` / `'Line'`; a non-string `alt` throws `InvalidStructure` like `src` does.

- [ ] **Step 2: Run them to verify they fail**

Run: `cd packages/docx && php -d xdebug.mode=off vendor/bin/phpunit --filter alt`, same in `packages/laravel`.
Expected: FAIL (no `descr`; `alt` treated as a style attribute or rejected).

- [ ] **Step 3: Implement**

Pass `$n->altText() === '' ? null : $n->altText()` as `DocxImage`'s alt for raster images and SVGs (replacing the `$doc->title` expression). Add `'alt'` to both components' `ownAttributes()` and apply `->alt($alt)` when given.

- [ ] **Step 4: Run docx and laravel suites and PHPStan**

Expected: all pass; the docx goldens are unchanged unless a golden fixture has a titled SVG (then the value is the same title — no change).

- [ ] **Step 5: Commit** — `feat(docx,laravel): image and SVG alt text in Word and Blade`

---

### Task 3: `inlineSvgs` option, inline markup with prefixed IDs and scoped styles

**Files:**
- Create: `packages/core/src/Html/InlineSvg.php`
- Modify: `packages/core/src/Export/HtmlOptions.php`, `packages/core/src/Html/HtmlWriter.php` (`svg()`)
- Modify: `packages/laravel/src/PapercutManager.php` (`buildHtmlOptions()`), `packages/laravel/config/papercut.php` (`html` section)
- Test: Create `packages/core/tests/Html/InlineSvgTest.php`; extend `packages/core/tests/Html/HtmlSvgTest.php`; laravel config test next to the existing `html.fonts` tests

**Interfaces:**
- Produces: `HtmlOptions` constructor parameter `bool $inlineSvgs = false` (added last), `inlineSvgs(): bool`, `withInlineSvgs(bool $on): self`; `withFullPage()` and `from()` carry it through.
- Produces: `InlineSvg::__construct(?\Closure $fontFamily = null)` where `$fontFamily` is `\Closure(?string $list, int $weight, bool $italic): string` (Task 4 supplies it; `null` leaves families alone).
- Produces: `InlineSvg::write(string $xml, string $prefix, string $alt, array<string,string> $style): array{html: string, approximated: list<string>}` — `$xml` is `SvgDocument::$xml`; `$prefix` like `pc-s3-`; `$style` the box declarations `HtmlWriter` already builds; `approximated` lists CSS that could not be scoped (one entry per kind).
- Laravel config key: `papercut.html.inline_svgs` (bool, default `false`).

- [ ] **Step 1: Write the failing tests**

`InlineSvgTest`:
- `test_ids_are_prefixed_and_references_rewritten`: input with `<linearGradient id="g">`, `fill="url(#g)"`, `style="stroke:url(#g)"`, `<use href="#g"/>`, `<use xlink:href="#g"/>` → every `id`/reference becomes `pc-s1-g`; no bare `#g` remains.
- `test_style_selectors_are_scoped`: `<style>.a{fill:red} text{font-size:9px}</style>` → selectors prefixed with the root's scope (root gets `class="pc-s1"` or an id; assert `.pc-s1 .a` and `.pc-s1 text`); `url(#g)` inside the `<style>` is rewritten; a CDATA-wrapped `<style>` works the same.
- `test_unscopable_at_rule_is_dropped_and_reported`: `@import url(x.css);` is removed and `approximated` is non-empty; `@media print{.a{…}}` keeps its inner selectors scoped and reports nothing.
- `test_alt_and_decorative`: alt `'Line'` → root has `role="img"` and `aria-label="Line"`; alt `''` → `aria-hidden="true"` and no `role`.
- `test_box_style_is_on_the_root`: `$style = ['width' => '100pt', 'display' => 'block']` appears in the root's `style`.

`HtmlSvgTest`:
- `test_svgs_are_images_by_default`: still `<img src="data:image/svg+xml;base64,`.
- `test_inline_svgs_writes_svg_markup`: with `(new HtmlOptions())->withInlineSvgs(true)` the body has an `<svg` element and no `data:image/svg+xml`.
- `test_same_svg_twice_gets_two_prefixes`: two `Svg::fromString($sameMarkupWithGradient)` on one page → two different prefixes, each gradient reference pointing at its own copy.
- `test_inline_svg_text_note_only_when_not_inline`: an SVG with `<text>` reports `SVG text in the browser's own fonts (set inlineSvgs to use the page's fonts)` with the option off, and nothing with it on; strict mode with the option on does not throw.

Laravel: `papercut.html.inline_svgs = true` → `htmlOptions()->inlineSvgs()` is true; default false.

- [ ] **Step 2: Run them to verify they fail**

Run: `cd packages/core && php -d xdebug.mode=off vendor/bin/phpunit tests/Html/InlineSvgTest.php tests/Html/HtmlSvgTest.php`
Expected: FAIL with `Class "Papercut\Html\InlineSvg" not found`.

- [ ] **Step 3: Implement**

`InlineSvg::write()` loads `$xml` with `DOMDocument` (no network, `LIBXML_NONET`), renames every `id` to `$prefix . $id`, rewrites `#id` in `href`/`xlink:href` and `url(#id)` in any attribute value and in `<style>` text, scopes `<style>` selectors by prefixing each selector in each rule with the root scope (leave `@media` blocks' contents scoped, keep `@font-face`, drop and report other `@`-rules), sets the root's `style`, `role`/`aria-label` or `aria-hidden`, and serializes the root with `saveXML($root)`. The root keeps its `width`/`height` attributes; the CSS box styles win in the browser. `HtmlWriter` keeps a per-export counter for prefixes (`pc-<6 hex of sha1 of the SVG>-1-`, `...-2-`, …), calls `InlineSvg` when `inlineSvgs()` is on, reports each `approximated` entry via `$this->fidelity->approximated($entry, $at->path())`, and otherwise keeps today's `<img>` with the reworded note.

- [ ] **Step 4: Run core and laravel suites and PHPStan**

Expected: all pass; HTML goldens unchanged except the note wording if a golden records notes (regenerate with `UPDATE_HTML_GOLDEN=1` only for that wording and list it).

- [ ] **Step 5: Commit** — `feat(html): inlineSvgs option writes SVG inline with prefixed ids and scoped styles`

---

### Task 4: Inline SVG text uses the PDF's font

**Files:**
- Modify: `packages/core/src/Text/SvgFonts.php` (extract the family-list match)
- Modify: `packages/core/src/Html/InlineSvg.php`, `packages/core/src/Html/HtmlWriter.php`
- Test: `packages/core/tests/Html/InlineSvgTest.php`, `packages/core/tests/Html/HtmlSvgTest.php`

**Interfaces:**
- Consumes: `InlineSvg::__construct(?\Closure $fontFamily)` from Task 3.
- Produces: `SvgFonts::firstRegistered(?string $list, \Closure(string): bool $isRegistered): ?string` (static; the existing private `registered()` calls it, so the PDF keeps the same rule).

- [ ] **Step 1: Write the failing tests**

`InlineSvgTest::test_font_families_are_rewritten`: with a closure that returns `"FAMILY($list|$weight|" . ($italic ? 'i' : 'n') . ")"`, a `<text font-family="Arial" font-weight="bold">`, a `<text style="font-family:'Open Sans'">`, a `<g font-family="X"><text>…` and a `<style>text{font-family:Y}</style>` each have their family value replaced by the closure's result (weight `700` for bold, `400` otherwise).
`HtmlSvgTest`, with a document that registers `Inter` (use the bundled family the core tests already register) and `withInlineSvgs(true)` and a font mode that writes `@font-face` (`FontMode::Embed`):
- `test_registered_svg_family_gets_a_font_face`: body text in a different registered family, SVG `<text font-family="Inter">` → an `@font-face` rule for Inter is written and the text's family is the Inter stack.
- `test_unregistered_svg_family_becomes_the_pdf_family`: `<text font-family="Arial">` inside an `Svg` whose computed family is the document family → the written family is the document family's stack, not Arial.
- `test_first_registered_family_in_the_list_wins`: `font-family="Arial, Inter"` → Inter.
- `test_no_font_faces_mode_uses_page_stack`: `FontMode::None` → the family is the page's stack, no `@font-face`, no note.

- [ ] **Step 2: Run them to verify they fail**

Run: `cd packages/core && php -d xdebug.mode=off vendor/bin/phpunit --filter 'font_families|svg_family|first_registered|page_stack'`
Expected: FAIL (families unchanged).

- [ ] **Step 3: Implement**

`HtmlWriter` passes a closure to `InlineSvg` that resolves `SvgFonts::firstRegistered($list, $isRegistered) ?? $s->fontFamily` (with `$s` the `Svg` node's computed style and `$isRegistered` the same registered-family test the layout's `FontRegistry::has()` uses — find the `FontSet`/registry equivalent available to `HtmlWriter`), records the family and variant the same way `recordFont()` does so `@font-face` rules include it, and returns `Css::fontFamily($family, $this->options->fonts()->fallbacks, …)` as the page does for text. `InlineSvg` replaces `font-family` in attributes, `style` attributes and `<style>` declarations; text with no family anywhere inherits the root, so set the root's `font-family` to the closure's result for `null`.

- [ ] **Step 4: Run the core suite and PHPStan; check in Chrome**

Run the suite. Then render a page with an Inter-labelled SVG and body text in another registered family, `FontMode::Embed`, `inlineSvgs` on; open it in headless Chrome (`"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless --screenshot=… file://…`) and compare the label against the PDF's (`pdftoppm -r 144`). Expected: same typeface.

- [ ] **Step 5: Commit** — `feat(html): inline SVG text resolves fonts like the PDF`

---

### Task 5: Docs, changelog, full verification

**Files:**
- Modify: `site/src/guide/html-export.md`, `site/src/guide/images-and-links.md`, `site/src/guide/word-export.md`, `CHANGELOG.md` ([Unreleased] → Added), `packages/laravel/config/papercut.php` comment for `inline_svgs`

- [ ] **Step 1: Write the docs** — `inlineSvgs` and Laravel `html.inline_svgs`, the trust warning ("only when you trust where every SVG comes from; Papercut removes scripts and event handlers but not links, `foreignObject`, animation or external images"), fonts behaviour; `alt` on images and SVGs, pasted `<img alt>`, `<title>` fallback, `alt('')` as decorative, where alt ends up (HTML `alt`/`aria-label`, Word picture description, not yet PDF); Word picture description line in word-export's table.
- [ ] **Step 2: Run all four suites on PHP 8.5 and 8.4, PHPStan in core, docx, laravel**

Run: in each of `packages/{math,core,docx,laravel}`: `php -d xdebug.mode=off vendor/bin/phpunit` and `$(brew --prefix php@8.4)/bin/php -d xdebug.mode=off vendor/bin/phpunit`.
Expected: no failures.

- [ ] **Step 3: Commit** — `docs: alt text and inline SVG`
