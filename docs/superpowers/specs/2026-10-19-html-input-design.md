# Papercut: HTML and Plain-Text Input — Design

**Status:** decisions taken with the maintainer, 2026-10-19. Supersedes the "Raw HTML input" sketch in §12 of
`2026-10-19-multi-format-export-design.md` (that sketch used php-pdf's `HtmlParser`; this design does not).
Items marked *(my call)* were decided by the designer, not the maintainer, and are open to review.

## 1. Intent

Apps store rich text written in a WYSIWYG editor and plain text with stray entities, and want both in their
documents. Today Papercut has no way to take either:
- `Text` shows `&nbsp;` literally and joins lines, so plain text from a database renders wrong.
- Text tags throw on `<p>`, so an option label stored as `<p>Agree</p>` fails the render.
- There is no list node, so `<ol>` has nothing to become.

The first consumer is a question-set PDF export that today uses TCPDF's `writeHTML()` (see the samples in §11).
It needs:
- **Prompts:** plain text with meaningful newlines and `&nbsp;`.
- **Option labels:** plain text, or a single `<p>` around plain text, rendered on one line, sometimes bold with a `×`
  mark before it.
- **Informational slides:** editor HTML with paragraphs, bold/italic, `<br>`, nested `<ol>` three levels deep,
  `<figure>` + `<img>` (sized, linked, or with alt text), an `<iframe>` embed, and runs of empty paragraphs.

This design adds three things, built in this order:
1. **A list node** (core + Blade), useful on its own and needed by the converter.
2. **A public way to put a PHP-built node into a Blade view** (`<x-doc::node>`). Small and independent.
3. **A markup converter** that turns HTML or plain text into Papercut nodes once, when the tree is built.

The converter is Papercut's own, written over `ext-dom` (already required), and produces ordinary nodes. Whatever it
produces therefore renders in PDF and HTML today and in DOCX when that exporter ships, with no per-exporter HTML
handling. That reuse is the main reason for writing our own instead of translating php-pdf's element model.

## 2. Goals, non-goals, anti-goals

**Goals**
- Render every sample in §11 without an exception, close to how a browser shows it.
- Best effort with warnings: anything the converter cannot represent degrades to something sensible and is reported.
  Strict mode fails the export where content or styling is lost (§5.8).
- No network access, ever. Images come from an app-supplied resolver.
- A list node that stands on its own in core and Blade.

**Non-goals (this version)**
- Floats and text wrapping around figures.
- Class names. They are ignored. A class → inline-style map is follow-up work (§10).
- `<style>` blocks, external stylesheets, or any CSS beyond inline `style="…"` declarations.
- Tables in markup (none in the data). `<table>` follows the unknown-tag rule (§5.6) for now.
- Configurable list numbering (§4.2 fixes outline style for now).

**Anti-goals** (failures even if it works)
- A network request triggered by content.
- One bad element failing the whole render outside strict mode.
- A flood of warnings for harmless editor noise (classes, `id`, `data-*`, `target`, empty paragraphs).
- Any change to existing PDF bytes or HTML goldens for documents that don't use the new features.

## 3. Constraints

- PHP `^8.4`, strict types, PHPStan level 8 in both packages, no new runtime dependencies.
- PDF golden bytes and existing HTML goldens unchanged.
- The converter must not depend on an exporter or backend. It produces nodes; exporters never see HTML.
- Untrusted input: content comes from end users through an editor. Treat it as hostile for links (same scheme rules
  as `InlineMarkup`: http, https, mailto, tel, `#fragment`) and never resolve `src` to a path or URL by itself.

## 4. Part 1 — List node

### 4.1 Core API

`ListNode::ordered(...)` and `ListNode::unordered(...)` take items. Each item (`ListItem`) holds any nodes: text,
images, columns, or another list. Strings become `Text` as elsewhere. The final class names are the builder's choice.

Options:
- `start(int)` for ordered lists (default 1).
- `marker(ListMarker)`: decimal, lower-alpha, upper-alpha, lower-roman, upper-roman, disc, circle, square, none.
  When unset, the marker comes from the nesting depth (§4.2).
- Normal style support (spacing, font, colour) like other block nodes; the marker uses the item's first-line text style.

### 4.2 Markers by depth (maintainer's decision: outline style)

- Ordered: depth 1 decimal (`1.`), depth 2 lower-alpha (`a.`), depth 3 lower-roman (`i.`), then repeat.
- Unordered: disc `•`, circle `◦`, square `▪` by depth, then repeat *(my call; matches browsers)*.
- Depth counts list nesting of the same kind *(my call; the builder may count all lists if that is simpler and
  documented)*.
- Making the depth scheme configurable is follow-up work.

### 4.3 Layout

- Markers sit in a gutter to the left of the item content and are right-aligned in it, so `9.` and `10.` line up.
  The gutter width is computed per list from its widest marker *(my call)*.
- Nested lists indent by their own gutter inside the item.
- Items can split across a page break. The marker stays with the item's first line; an item whose first line moves
  to the next page takes its marker with it.
- Each item's marker aligns with the baseline of the item's first line of text.

### 4.4 Other formats

- HTML export writes real `<ol>`/`<ul>`/`<li>` with explicit `list-style-type` and `start`, so a browser shows the
  same markers.
- DOCX (later) maps to Word's native numbering. Nothing in the node should prevent that: markers are data, not
  pre-rendered text.

### 4.5 Blade

`<x-doc::list type="ol|ul" start="3" marker="lower-roman">` containing `<x-doc::item>` children. An item takes any
block content. Style attributes as on other block tags.

## 5. Part 3 — Markup converter

(Part 2 is in §6; it is small and independent, so it is described after the converter.)

### 5.1 Entry points

Core *(my call on names)*:
- `Markup::html(?string $html, MarkupOptions $options = new MarkupOptions()): Node`
- `Markup::text(?string $text, MarkupOptions $options = new MarkupOptions()): Node`
- `Markup::inlineHtml(...)` / `Markup::inlineText(...)` returning `list<Span>`, for joining a line inside an existing
  `Text` (the `× <label>` case). Block content passed here is flattened into the line with a warning.

Blade *(my call on names)*: `<x-doc::markup :html="$slide" />` or `<x-doc::markup :text="$prompt" />`, exactly one of
the two, mirroring `<x-doc::image src|data>`. Used inside a text tag (`<x-doc::text>× <x-doc::markup :html="$label"
/></x-doc::text>`) it contributes inline content and inherits the surrounding text style (bold, size, colour).

Both entry points accept `null` and `''` and produce nothing (an empty node that takes no space), with no warning.

### 5.2 Modes

**Plain text (`text`)** — for prompts.
- Decode HTML entities (`&nbsp;` becomes U+00A0, `&amp;` becomes `&`, numeric entities too).
- Newlines become line breaks. A blank line (two newlines) becomes a paragraph break *(my call)*.
- `<` is an ordinary character; nothing is parsed as a tag.
- Runs of spaces collapse as in normal text, except non-breaking spaces, which are kept.

**HTML (`html`)** — for labels and slides. Parsed with `ext-dom` as an HTML fragment (UTF-8, no network, no external
entities). Mapping in §5.3–§5.7.

**Single-paragraph unwrap.** HTML that is exactly one paragraph of inline content (`<p>Agree</p>`) converts to a single
`Text` (or, from the inline entry points, to spans), so it can join a line and inherit its style.

### 5.3 Element mapping

| HTML | Becomes |
|---|---|
| `p`, `div` | A paragraph (`Text`), block-level |
| `h1`–`h4` | The theme's heading presets; `h5`/`h6` map to `h4` |
| `b`, `strong` | Bold span |
| `i`, `em` | Italic span |
| `u`, `s`/`strike`/`del` | Underline / strike-through span, as `InlineMarkup` supports |
| `span` | A span (its inline style applies in `original` mode) |
| `br` | Line break; a `br` that is the last thing in a block is dropped, as browsers do |
| `a href` | Link around its content (text or image); unsafe schemes drop the link and keep the content, with a warning; `target` and `rel` are ignored |
| `ol`, `ul`, `li` | The list node; `start` and `type` (`1 a A i I`) are honored in both style modes |
| `img` | Image via the resolver (§5.5) |
| `figure` | A block holding its content; `figcaption` becomes a paragraph below it |
| `iframe` | A paragraph with a link to its `src`, using its `title` as the text, else the URL, with a warning (§5.6) |
| `blockquote`, `pre`, `code`, `hr` | Builder's call: map simply (indented block, monospace run, rule) or treat as unknown |
| anything else | Unknown-tag rule (§5.6) |

Attributes other than those named (`id`, `class`, `data-*`, `target`, `frameborder`, `allowfullscreen`…) are ignored
silently.

### 5.4 Whitespace and noise (browser-like, plus the maintainer's blank-paragraph rule)

- Source whitespace collapses as in HTML: indentation and newlines inside `<p>` become single spaces; leading and
  trailing spaces in a block are trimmed.
- Zero-width characters (U+FEFF, U+200B) are stripped.
- `&nbsp;` is a non-breaking space: kept, and it does not collapse with neighbouring spaces.
- **Blank paragraphs are dropped by default** (maintainer's decision): a paragraph with no visible content after
  trimming — `<p></p>`, `<p><i></i></p>`, `<p><i>&nbsp;</i></p>`, `<p><br></p>` — produces nothing.
- Option `blankParagraphs: keep` (Blade `blank-paragraphs="keep"`) keeps paragraphs whose only content is non-breaking
  spaces or `<br>` as one blank line each, as a browser shows them. Truly empty `<p></p>` is dropped in both modes,
  since browsers give it no height.
- Blank space at the very start and end of the content is trimmed in both modes.

### 5.5 Images

- The app supplies a resolver: `callable(string $src): ?string` returning image bytes, or `null` when the image is
  missing. It gets the raw `src` attribute (spaces and all; HTML entities decoded). Papercut never turns `src` into a
  file path or URL by itself, so without a resolver every `img` is treated as missing.
- Missing image (`null`, or the resolver throws):
  - with `alt` text: the alt text in italics as a paragraph, plus a warning;
  - without `alt`: nothing, plus a warning.
- Size: the `width` and `height` attributes are honored in both style modes (they state the image's intended size,
  in CSS pixels at 0.75pt). Existing image rules still apply: shrink to the available width, and shrink to an empty
  page's height.
- In `original` mode, inline `width`, `height`, `max-width` and `max-height` on the `img` or its `figure` also apply.
- An `img` inside `a href` becomes a linked image.
- Bytes the backend later refuses (corrupt data) are the builder's call: either sniff the format at conversion time
  and fall back to alt text, or let `ImageUnreadable` surface at export as it does for any image. Whichever is chosen
  must be documented and tested.

### 5.6 Things that cannot be drawn

Each produces a fidelity report at the level in §5.8; strict mode throws only on `dropped`, as it does today:
- **Unknown tags:** the tag is dropped and its content kept, converted by the same rules. `script`, `style`,
  `template`, `head`, `noscript`, `object`, `embed` drop their content too.
- **`iframe`:** becomes a link paragraph as in §5.3. `video` and `audio` follow the same rule with their `src`.
- **Float** (`float` in inline style, `original` mode): the element renders as a normal block, with a warning. Floats
  set through classes are invisible to the converter and do not warn.
- **Unsupported inline CSS** (`original` mode): the declaration is dropped with a warning naming the property.
- **Unsafe link schemes:** link dropped, content kept, with a warning.

Silently ignored (no warning): classes, `id`, `data-*`, `target`, `rel`, blank paragraphs, zero-width characters.

### 5.7 Styling modes (maintainer's decision: the developer chooses)

Option `styles: theme|original`, Blade attribute `styles="…"`. Default `theme` *(my call)*.
- **`theme`:** the HTML supplies structure only. Paragraphs, headings, lists and links take Papercut's theme and
  presets. `style="…"` attributes are ignored silently. `img width/height`, `ol start/type` and `li value` still apply
  (§5.3, §5.5).
- **`original`:** the same theme base, with each element's inline `style="…"` applied on top. Declarations map onto
  Papercut's existing style names, which are already largely CSS names (`font-size`, `text-align`, `color`,
  `max-width`, `margin`, `padding`, `font-weight`, `font-style`, `text-decoration`, `list-style-type`…). `px` converts
  at 0.75pt. Unknown or unsupported declarations are dropped with a warning (§5.6).

### 5.8 Warnings and strict mode

The converter runs when the tree is built, before any exporter exists, but warnings must reach the export's
`onWarning` and fidelity report, and strict mode must throw. Requirement: conversion notes travel with the converted
nodes and are reported by every exporter when it renders them, with the node path, through the existing `Fidelity`.

Levels (maintainer's decision: keep the existing rule that strict mode throws only on `dropped`):

| Case | Level | Fails a strict export? |
|---|---|---|
| `iframe`/`video`/`audio` shown as a link | approximated | no |
| Missing image shown as its alt text | approximated | no |
| Float rendered as a normal block | approximated | no |
| Unknown tag removed, content kept | approximated | no |
| Missing image with no alt | dropped | yes |
| Unsafe link scheme: link removed, content kept | dropped | yes |
| Unsupported inline CSS declaration | dropped | yes |
| Block content flattened by an inline entry point | approximated | no |

`Fidelity` itself does not change. The mechanism (a wrapper node carrying notes, or similar) is the builder's call. Notes must not be reported
twice within one export, even if layout visits the node more than once (for example when the paginator retries it on
a new page). Each export reports afresh: exporting the same document twice, or to PDF and then HTML, reports the notes
in each export. The note carrier is pure data on immutable nodes *(my call)*.

## 6. Part 2 — Embedding a PHP-built node in Blade

Today the only path is `NodeRegistry::register()`, which is internal.
- Add `<x-doc::node :node="$node" />`, accepting any `Node` or `Component`. It contributes the node where it appears:
  as a block among blocks, and as inline content inside a text tag when the node is a `Text` or `Span` list
  *(my call on the inline rule)*.
- Anything else passed to `:node` throws `InvalidValue` naming the tag, as other typed attributes do.
- Document it in the Laravel README next to typed Blade values.

## 7. Laravel surface

- `<x-doc::list>`, `<x-doc::item>`, `<x-doc::markup>`, `<x-doc::node>` as above.
- Config block `papercut.markup` *(my call on names)*:
  - `images`: an invokable class implementing `Papercut\Laravel\Markup\ImageResolver` (`__invoke(string $src): ?string`),
    built by the container, like `html.image_url`. Default `null` (every `img` is missing).
  - `styles`: `'theme'` (default) or `'original'`.
  - `blank_paragraphs`: `'drop'` (default) or `'keep'`.
- Tag attributes override config per use.
- Invalid config is reported at first use, like the `html.*` keys.

## 8. Testing

- The samples in §11 are copied into the core test fixtures and each is converted and exported to PDF and HTML
  without an exception, with the expected warnings and no others.
- Unit tests per rule in §5.3–§5.7, asserting each case's fidelity level (§5.8): strict mode throws for every `dropped` case and for none of the `approximated` ones.
- List node: layout tests for markers by depth, gutter alignment (`9.`/`10.`), page splits keeping markers with first
  lines, and HTML export of `<ol>`/`<ul>`.
- Security: `javascript:` and other unsafe link schemes; `src` never read without a resolver; no network access
  (assert no stream wrappers or HTTP are touched).
- A visual fixture for a slide-like page (nested lists, figure, linked image) alongside the existing visual tests.

## 9. Left to the builder

- Class names for the list node and markup types, and how notes travel with nodes (§5.8).
- Whether `blockquote`, `pre`, `code` and `hr` get simple mappings or the unknown-tag rule.
- Handling of resolver bytes the backend refuses (§5.5), documented either way.
- Internal structure of the converter (one walker, or a walker plus per-element handlers).

## 10. Follow-up work (not in this version)

- A class → inline-style map (`classes: ['float-right' => 'max-width: 40%; …']`).
- Configurable list numbering per depth.
- Floats with text wrap.
- Tables in markup.
- DOCX mapping of lists to native numbering (with the DOCX exporter).

## 11. Sample content

Real content from the first consumer's dev database (2026-10-19). Prompts are plain text (`\n` is a real newline):

```text
What federal law protects the privacy of student education records?&nbsp;\n
Now, let's perform an object specific search.   An object specific search is started from a search box on one of the landing pages (Resources, Groups, Curriculum).\n\nAnswer the questions that follow
Add a Tiles Block that is set to call in the following objects:\n- Resources owned by the group
Select Submit to continue, then&nbsp;Next on the following screen →
```

Option labels with HTML are all a single paragraph: `<p>Strongly agree</p>`, `<p>Students are developing both the
academic skills and knowledge needed for success in college and workforce.</p>`.

Informational slides (one is `''` and two are `null`):

```html
<p><b>The General Search bar can be found in the header of the platform</b></p>
<figure>
    <img src="/public/images/medium/files/uploaded/editor/VEOYWisPKd36D7B1GCAeWoMncqsrw7QDUWfomL0HlA5NCAb6/Screen Shot 2024-07-19 at 9.33.45 AM.png" id="144433" data-image="144433" class="img-fluid">
</figure>
<p></p>
<p></p>
<p></p>
<p></p>
<p></p>
<p></p>
```

```html
<figure class="float-right" style="max-width: 221px;"><img src="/public/images/medium/files/uploaded/editor/HUTm7R5iBY9cnW0jTshJnN0duocZ2mEtTslYGJciWnvKR2NT/Screen Shot 2024-07-19 at 9.42.58 AM.png" id="144436" data-image="144436" class="img-fluid" width="221" height="266" style="width: 221px; max-width: 221px; height: 266px;"></figure>
<ol><li><i>Select</i> Resources</li><li>Now <i>apply</i> applicable filters to further refine your search by:<ol><li>Resource Category</li><li>Content Types</li><li>Sharing</li><li>Subjects</li><li>Grades</li><li>Audience</li><li>Resource Providers&nbsp;<ol><li><b>note:</b>&nbsp; this is a free text box to search for a resource provider by. name</li></ol></li></ol></li></ol>
```

```html
<p>On each landing page there is a search box located at the top of the page.&nbsp; &nbsp; Starting a search from any of these landing pages will result in a search return of those specific objects within the platform that match your search terms.&nbsp; &nbsp; &nbsp;</p>
<p><b>For example:</b>&nbsp; <i>if you start your search from the Resources landing page, Resources matching your key words will be returned in the search.</i></p>
<figure style="max-width: 647px;">
    <img src="/public/images/medium/files/uploaded/editor/THHA3cid3BXr3lFUU6mKj3JJrujAYjqEIHxD6aZr5rdzQS83/Screen Shot 2024-07-19 at 9.52.59 AM.png" id="144439" data-image="144439" class="img-fluid" width="647" height="104" style="width: 647px; max-width: 647px; height: 104px;" alt="image of the search box on the Resources landing page">&#xFEFF;
</figure>
<p></p>
<p><i>&nbsp;</i></p>
<p><i></i></p>
```

```html
<figure style="max-width: 831px;"><a href="https://docs.google.com/document/d/15D3sSt8JJgRUK_rR22tBA_UvrRisXDDvr2ksua3DUl4/edit?usp=sharing" target="_blank"><img src="/public/images/medium/files/uploaded/editor/GLl7CVzkfBGTqCsb0LsryjWuuEJPOiPsDFKvVqS8YE6tmCge/Screen Shot 2024-07-30 at 12.33.24 PM.png" id="145906" data-image="145906" class="img-fluid" alt="images with table explaining object permissions." width="831" height="658" style="width: 831px; max-width: 831px; height: 658px;"></a></figure>
```

```html
<p>
    Add a Tiles Block to your group (or configure the settings of an existing one so that all resources owned by your group will automatically display within that block.
</p>
<p>
    Refer to the Tiles Block Guide as needed
</p>
<figure><iframe src="https://scribehow.com/embed/Tiles_Block_Overview__c3Ucxk30S9q7MkOpRtXbgw?as=scrollable" width="100%" height="640" allowfullscreen="" frameborder="0"></iframe></figure>
```

```html
<p><b>Welcome to our brand new survey tool!</b><br></p><p>We are excited to show off our new survey tool available to all organizations of California Educators Together.</p>
```

In the app, image `src` values are site paths `/public/images/{size}/files/uploaded/editor/…` that map to
`files/uploaded/editor/…` on the default Storage disk; many are missing in dev.
