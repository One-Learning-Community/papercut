# Word font names, preformatted text, per-tag image resolvers and decorator variants

Date: 2026-10-29. Status: draft for approval.

Four independent features in one spec, built as one branch in four parts. Each part ships on its own; the order below is the build order.

## Intent

Papercut renders one document to PDF, Word and HTML. Real use turned up four gaps:

1. **Word shows the wrong font.** The PDF embeds its fonts; a docx only names them. A family most readers lack (the bundled Inter, or any custom face) opens in Word, LibreOffice or Google Docs as a serif fallback, which changes the document's whole look.
2. **`<pre>` in markup loses its layout.** The tag is dropped, the text drops into the body font, and runs of spaces collapse, so code samples, ASCII tables and poetry lose their alignment. Papercut has no monospace font, so even a style can't fix it.
3. **One image resolver for every markup tag.** `papercut.markup.images` is app-wide. Apps with several content sources need a stricter or broader resolver per tag. Apps also don't know how long a resolver lives, so they don't know where to cache. And the resolver is called once per `<img>`, even when a page repeats the same image hundreds of times.
4. **Decorator parts can only be restyled app-wide.** `style="name"` on a decorator reaches only its outer box. A larger section title for one document type means redefining `section.title` for every document.

## Non-goals

- Embedding fonts in the docx (Word-only support; LibreOffice and Google Docs ignore it).
- Syntax highlighting in `<pre>`.
- Line numbers, or `white-space` values other than the two below.
- Cross-request image caching inside papercut (the app's cache is the right place; we document it).
- Per-instance styling of arbitrary decorator parts by path (e.g. `title-style=""`). Variants are named and themed.

## Anti-goals

- Changing what an existing document looks like in PDF. Every part is byte-neutral for PDFs that don't use it, except where noted.
- Word output that isn't native Word: no fake fallbacks via text tricks.

---

## Part 1: Word font names (item 3)

### 1.1 A Word name per family

A font family can carry the name Word should use for it. Core: `FontSet::withWordName(string $family, string $wordName): self` (case-insensitive family match, last call wins) and `FontSet::wordName(string $family): string` (the family itself when none is set). Laravel: a new top-level config key `font_word_names` (family => Word name), validated like `fonts` (non-empty strings; a family not registered in `fonts` and not bundled is an error at first use, with the config path in the message).

The docx exporter writes the Word name everywhere it writes a font: run `w:rFonts`, the patched `rPrDefault`, and list label rPr. PDF and HTML are unaffected: the PDF still embeds the real face, and HTML keeps the real family.

Line metrics in Word stay computed from the real face (as today). The Word name is meant for metric-compatible pairs; the docs say so.

### 1.2 Font table hints

For every family the docx uses, write `word/fontTable.xml` (via `DocxPatcher::addPart`, with its relationship from `document.xml.rels` and its content type) with one `w:font` per written name:

- `w:panose1` from the face's OS/2 table (10 bytes, hex);
- `w:family`, derived from the face:
  - `post.isFixedPitch` set → `modern` (monospace);
  - else by PANOSE family kind: 2 (Latin text) → by serif style: 11-13 (normal/obtuse/perpendicular sans) or 0/1 (any/no fit) → `swiss`, any other → `roman`; 3 (hand-written) → `script`; 4 (decorative) → `decorative`; 5 (symbol) → `auto`; 0/1 (any/no fit) → `swiss`;
- `w:pitch`: `fixed` or `variable`;
- `w:charset w:val="00"`.

Word picks a substitute for a missing font by these hints, so a missing Inter opens as a sans font and a missing monospace font as a monospace one. When the face has a Word name (1.1), the entry uses the Word name with the real face's hints.

The hints come from the family's regular face (weight 400, normal style), or its first registered face when it has none.

Reading the OS/2 and post tables: the PDF backend exposes `isFixedPitch()` but not PANOSE, and the docx package already has a raw table reader (`Map/LineMetrics.php`). Extend that reader, or add a small one; builder's choice. The classification (sans / serif / mono / script / decorative) is also used by HTML (2.4), so it lives where both the docx and HTML writers can reach it.

The font table names only the written name (the Word name when set); no `w:altName`.

If php-docx ever writes its own `fontTable.xml`, the patcher fills it instead of adding a second part (the same guard as `w:mirrorMargins`).

### 1.3 Defaults

- Inter keeps the name `Inter` in Word (decided by the maintainer). With 1.2 it falls back to a sans font, not a serif one.
- The bundled Cousine (Part 2) has the Word name `Courier New` by default: the two are metric-compatible, so line breaks match the PDF. An app can override it. *(My call.)*

### 1.4 Docs

`site/src/guide/word-export.md`: a "Fonts in Word" section covering naming, the fallback hints, the Word-name option, and a table of metric-compatible open fonts (Arimo ↔ Arial, Tinos ↔ Times New Roman, Cousine ↔ Courier New, Carlito ↔ Calibri, Caladea ↔ Cambria). `site/src/guide/fonts.md` and `site/src/laravel/configuration.md`: the new option.

---

## Part 2: Preformatted text and a monospace font (item 2, `<pre>` half)

### 2.1 Bundled Cousine

Bundle Cousine Regular, Bold, Italic and BoldItalic (SIL OFL, from Google Fonts) under `packages/core/resources/fonts/Cousine/` with its licence file, registered in `FontSet::bundled()` like Inter. Add it to `NOTICE.md`. `Theme` gains a `monoFamily` (default `Cousine`), overridable through `papercut.theme.mono_family`. A style's font family may be the keyword `monospace`, which resolves to the theme's `monoFamily` at style-resolution time; the `pre` and `code` styles use it, so changing `mono_family` changes them without redefining the styles.

### 2.2 Whitespace style

A new style property `whiteSpace`: `normal` (default, today's behaviour) or `pre-wrap`. Attribute name `white-space`. Under `pre-wrap`:

- spaces are kept, not collapsed, and a line keeps its leading spaces;
- `\n` (and `\r\n`, `\r`) is a forced line break;
- a tab advances to the next multiple of 4 space widths from the line start *(my call: 4, as browsers default to 8 but code samples read better at 4)*;
- a line longer than the box still wraps, at spaces where possible, otherwise anywhere. `pre` (no wrapping) is not offered: in a paged PDF a line that runs off the page is lost.

It inherits, as in CSS, and is read per span at text layout (the Itemizer), so a `pre-wrap` span inside `normal` text keeps its spaces while its neighbours collapse. Tabs are expanded at layout time, measured from the start of the line. The markup converter's existing newline → break handling inside `<pre>` stays.

### 2.3 Named styles and nodes

- `DefaultStyles` gains `pre` (mono family, `white-space: pre-wrap`, 0.9em, a light tint background, small padding, the paragraph spacing) and `code` (mono family, 0.9em, a light tint background).
- Blade: `<x-doc::pre>` (a block) and `<x-doc::code>` (inline). Both accept the usual style attributes.
  - `<x-doc::pre>`'s slot is literal text: tags inside it are not parsed, HTML entities are decoded (so Blade's `{{ }}` escaping round-trips and `&lt;` gives `<`), a leading and trailing blank line are dropped, and the indentation common to all non-blank lines is removed (so the tag can sit indented in a template). Other components can't be nested in it.
  - `<x-doc::code>` is an inline component like the existing inline tags; inline markup inside slots also accepts `<code>`. Mechanism is the builder's choice.
- Markup: `<pre>` becomes a `pre` block. `<code>` becomes a `code` span, and `<pre><code>` collapses to one `pre` block. `<kbd>` and `<samp>` map to `code`; `<tt>` too. The "tag removed" approximation note for `pre` goes away.

### 2.4 Output per format

- **PDF:** as laid out.
- **Word:** each line a run with `xml:space="preserve"`, `w:br` between lines, the mono Word name (Courier New by default), tabs written as `w:tab` (Word's default stops; may drift from the PDF, which is acceptable per the docx-native rule).
- **HTML:** `<pre>` / `<code>` elements with `white-space: pre-wrap`; the mono family follows the HTML font settings like any other family. The generic family that ends every CSS font stack (today always `system-ui, sans-serif`) follows the face's class from 1.2: `monospace`, `serif`, or `system-ui, sans-serif`. This changes HTML output for serif and mono families only.

### 2.5 Docs

`site/src/guide/rich-text.md` (the tag table), `site/src/guide/text.md` (white-space, `<x-doc::pre>`, `<x-doc::code>`), `site/src/reference/blade-tags.md`, `site/src/reference/decorator-styles.md` or the style reference (the new names), CHANGELOG.

---

## Part 3: Image resolver per markup tag (item 7)

### 3.1 The `images` attribute

`<x-doc::markup images="App\Content\LegacyImages">` uses that class for this tag's `<img>` tags instead of `papercut.markup.images`. The class must implement `Papercut\Laravel\Markup\ImageResolver` (same check and message as the config). It is built through the container once per manager scope and class, and reused by every tag that names it. `images="none"` turns images off for that tag (each image shows its alt text, as for a missing image).

### 3.2 One call per distinct source

Within one markup conversion, the resolver is called once per distinct `src` (trimmed string); later `<img>` tags with the same `src` reuse the result, including a null. (PDF image decoding is already cached by content.) *(My call: per conversion rather than per manager scope, so a resolver whose answer may change between documents is never second-guessed.)*

### 3.3 Docs

`site/src/laravel/configuration.md`, "Markup images": the attribute; the lifetime in plain words: "papercut builds a resolver once per request, queue job or Octane request, and every markup tag in it shares that instance. A cache in a property lasts that long; for anything longer use Laravel's cache." `site/src/reference/blade-tags.md`: the attribute.

---

## Part 4: Decorator variants (item 9a)

### 4.1 The `variant` option

Every decorator gains `variant(string $name)` (Blade: `variant="large"`; one word; several space-separated variants allowed, applied in order). For each part a decorator styles with a built-in name `X` (the outer box `section`, `section.title`, `section.heading`, `section.body`, …), it also applies `X.<variant>` when the theme defines it, right after `X`. The user's `style=""` and explicit modifiers still win on the outer box.

"Parts" are every built-in name the decorator applies, modifiers included (`section.number`, `section.rule`).

Mechanism: decorators don't see the theme, so variant names are applied as *optional* names: a name a decorator marks optional (internal syntax, e.g. a trailing `?`) is skipped silently by `Theme::named()` when the theme lacks it. Users can't write optional names in `style=""`.

Validation: in Blade, the component checks, as tones already do (`requireThemeStyles`), that at least one `<part>.<variant>` exists for that decorator, and otherwise throws listing the names it looked for. In PHP, a variant with no matching style is silently a no-op (documented), since the decorator has no theme to check against. No variant styles ship by default; apps define them in `papercut.styles` or `Theme::withStyle()` (e.g. `section.title.large`).

### 4.2 Relation to tones

Callout and badge tones keep their API. Internally, tones can use the same part-lookup helper; a tone and a variant can be combined (`callout.title.warning` then `callout.title.large`). Order: built-in, tone, variant, user style, modifiers.

### 4.3 Docs

`site/src/guide/decorators.md` "Restyling": variants, with a section-title example. `site/src/reference/decorator-styles.md`: the `.<variant>` suffix rule.

---

## Testing

- Each part: unit tests at the layer that owns it (core style/layout, docx mapper/patcher, laravel component), plus one golden or snapshot fixture per format for Parts 1 and 2.
- Part 1: the docx for a document using Inter has `fontTable.xml` with Inter as `swiss`/variable; with a Word name set, runs name the Word name; LibreOffice still opens the file (the existing soffice check).
- Part 2: spaces, tabs and blank lines survive in all three formats; a long line wraps; `<pre><code>` gives one block; Cousine renders in PDF without any font registration.
- Part 3: two tags with different resolvers in one document; `images="none"`; 50 identical `<img>` call the resolver once.
- Part 4: a variant reaching the title only; an unknown variant throws in Blade and is a no-op in PHP; tone + variant order.

## Golden changes

Expected to change (pre-v1, allowed): every docx golden (new `fontTable.xml`), fixtures with `pre`/`code` in markup, anything else whose documents contain `<pre>`, and HTML goldens using a serif or mono family (the generic fallback). Any PDF golden changing outside fixtures with `pre`/`code` is a bug.

## Left to the builder

- How OS/2/post data are read (1.2).
- The tint and padding values of the `pre`/`code` styles, matching the existing decorator palette.
- Internal names of helpers.
