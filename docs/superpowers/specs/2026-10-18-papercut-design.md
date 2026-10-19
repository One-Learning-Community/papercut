# Papercut: Design Spec

**Date:** 2026-10-18
**Status:** Draft for review
**Packages:** `papercut/core`, `papercut/laravel`

## 1. Summary

Papercut is an open-source PDF generation library for modern PHP. Documents are built as a tree of typed, immutable components with flexbox layout and engine-native pagination. The flagship way to use it is Laravel Blade: `<x-pdf::*>` tags compile into the component tree, not into HTML. A framework-agnostic core engine does all layout and PDF writing in pure PHP. HarfBuzz text shaping is an optional FFI accelerator.

It is not HTML-to-PDF. There is no CSS cascade, no browser and no HTML parser. The tag vocabulary is small, strict and ours.

## 2. Why

- The established PHP PDF libraries (TCPDF, FPDF, mPDF) use imperative, coordinate-driven APIs from the PHP 5 era. Dompdf is HTML-based, slow and supports only part of CSS. Chromium-based tools are heavy to run.
- Prior-art survey (2026-10-18): about eight recent attempts at a modern fluent or declarative PHP PDF builder exist (dskripchenko/php-pdf, mattsplat/declarative-pdf, folio, famoser/pdf-generator, papier, arabel/pdf, tc-lib-pdf, PHPPdf). None has meaningful adoption. The only component-tree attempt (folio) is an abandoned one-week prototype with core-14 fonts only.
- No PHP equivalent exists of react-pdf (JS) or QuestPDF (.NET), both of which are popular.
- Every Laravel PDF package found renders Blade to HTML and sends it to a browser engine. None compiles Blade component tags into a native layout tree.

## 3. Goals and success criteria

**Audience:** PHP developers, primarily Laravel developers, and especially ones comfortable with frontend component thinking.

**v0.1 succeeds when:**
1. A Laravel developer can `composer require papercut/laravel`, write a Blade view with `<x-pdf::*>` tags, and return `Pdf::view(...)->download()` in under 10 minutes using the README.
2. Three polished example documents ship and render correctly: an invoice, a transcript/certificate and a multi-page report with a long table.
3. Pagination is correct: tables split by row with repeated headers, keep-together and keep-with-next are honored, widows and orphans are controlled, and `{page} of {total}` works.
4. Output passes `qpdf --check` and opens correctly in Acrobat, Chrome, Firefox (pdf.js) and macOS Preview.
5. A 10-page table document renders in under 150 ms on PHP 8.4 without FFI. This is tracked in CI.

**Non-goals for v0.1:** see §10.

## 4. Requirements

- PHP 8.4+.
- Required extensions: `ext-zlib` and `ext-mbstring`.
- Optional: `ext-ffi` plus system `libharfbuzz`, enabling `HarfBuzzShaper`.
- `papercut/core` has zero Composer runtime dependencies.
- `papercut/laravel` requires `papercut/core` and supports Laravel 12+.

## 5. Repository layout

A monorepo, split into read-only package repos on release.

```
packages/
  core/      Papercut\           papercut/core
  laravel/   Papercut\Laravel\   papercut/laravel
examples/    invoice, transcript, report (Blade + plain-PHP versions)
docs/
```

## 6. Public API

### 6.1 Blade (flagship)

```blade
<x-pdf::document title="Transcript" author="OLC">
  <x-pdf::page size="a4" margin="20mm">
    <x-slot:header>
      <x-pdf::row align="center">
        <x-pdf::image src="images/logo.png" height="12mm" />
        <x-pdf::spacer />
        <x-pdf::text size="18pt" bold>Official Transcript</x-pdf::text>
      </x-pdf::row>
    </x-slot:header>

    <x-pdf::column gap="8pt">
      <x-student-card :student="$student" />

      <x-pdf::table columns="3fr 25mm 20mm">
        <x-pdf::thead>
          <x-pdf::th>Course</x-pdf::th><x-pdf::th>Credits</x-pdf::th><x-pdf::th>Grade</x-pdf::th>
        </x-pdf::thead>
        @foreach ($courses as $course)
          <x-pdf::tr>
            <x-pdf::td>{{ $course->title }}</x-pdf::td>
            <x-pdf::td>{{ $course->credits }}</x-pdf::td>
            <x-pdf::td align="right">{{ $course->grade }}</x-pdf::td>
          </x-pdf::tr>
        @endforeach
      </x-pdf::table>
    </x-pdf::column>

    <x-slot:footer>
      <x-pdf::page-number format="Page {page} of {total}" align="center" />
    </x-slot:footer>
  </x-pdf::page>
</x-pdf::document>
```

```php
return Pdf::view('pdf.transcript', compact('student', 'courses'))->download('transcript.pdf');
// also ->inline(), ->save($path), ->toString(); PdfResponse implements Responsable
```

App components such as `<x-student-card>` are ordinary Blade components that contain `<x-pdf::*>` tags.

**Inline text markup:** inside `<x-pdf::text>` (and cells and headings), a fixed set of inline tags is allowed: `<b>`, `<i>`, `<u>`, `<s>`, `<a href>`, `<br>`, `<span …style attributes…>`. Tags are recognized in the template source. Interpolated `{{ }}` data is HTML-escaped by Blade, so user data cannot inject tags. Entities are decoded only after the inline tags have been parsed.

### 6.2 Plain PHP (core)

The same vocabulary as immutable nodes, for non-Laravel users, tests and programmatic building:

```php
use Papercut\{Document, Page, Column, Row, Text, Image, Table, Spacer, PageNumber};
use function Papercut\{mm, pt, fr};

$pdf = Document::make(
    Page::a4()->margin(mm(20))
        ->header(Row::make(Image::file('logo.png')->height(mm(12)), Spacer::flex(),
                           Text::make('Official Transcript')->size(pt(18))->bold()))
        ->body(Column::make(
            Table::make()->columns(fr(3), mm(25), mm(20))
                ->head('Course', 'Credits', 'Grade')
                ->rows($courses, fn (Course $c) => [$c->title, $c->credits, Text::make($c->grade)->right()]),
        )->gap(pt(8)))
        ->footer(PageNumber::make('Page {page} of {total}')->center()),
)->title('Transcript');

$pdf->save('transcript.pdf');
```

Custom PHP components implement `Papercut\Component` (`render(): Node`) and are expanded lazily.

**Rule:** the tag vocabulary and the node vocabulary map one to one. Every tag attribute corresponds to a node modifier, and every modifier value has a canonical string form.

### 6.3 Element vocabulary (v0.1)

| Tag / Node | Purpose |
|---|---|
| `document` | Root. Metadata: title, author, subject, keywords, lang. |
| `page` | Page template: size (a3–a6, letter, legal, custom), orientation, margin; `header`, `footer` and `background` slots; body content flows across as many physical pages as needed. |
| `view`, `row`, `column` | Flex containers. `row`/`column` are `view` with a preset direction. |
| `text` (+ `h1`–`h4`, `p` presets) | Paragraph with inline spans. |
| `image` | JPEG/PNG; width, height, fit (`contain`, `cover`, `fill`). |
| `table`, `thead`, `tr`, `th`, `td` | Table with column widths, repeated headers and `colspan`. |
| `spacer` | Fixed size or flexible (`grow`). |
| `page-number` | Text with `{page}`/`{total}` placeholders. |
| `page-break` | Forced break. |
| `link` | Wraps content as an external URL link. |

### 6.4 Styling

- Style properties are available as tag attributes and as node modifiers.
- **Box (not inherited):** width, height, min/max, padding, margin, border (width, color, style solid/dashed), radius, background, opacity.
- **Flex (containers):** direction, gap, justify, align, align-self, grow, shrink, basis, wrap.
- **Text (inherited):** font family, size, weight, italic, color, line-height, letter-spacing, align (left, right, center, justify), decoration.
- **Reusable styles:** `Style::make(padding: pt(12), fontWeight: 600)` in PHP. In Blade, named styles are registered in config/theme and applied with `style="card"`. A `Theme` provides default fonts, a type scale and a color palette (Tailwind-like named colors such as `slate-500`).
- **Pagination:** `keep-together`, `keep-with-next`, `break-before`, `break-after`, `min-lines` (widows and orphans, default 2). Table `repeat-header` defaults to on.

### 6.5 Values and string grammar

- **Lengths:** `12pt`, `20mm`, `2cm`, `1in`, `50%`. Bare numbers are rejected, except for a unitless `line-height`.
- **Fractions:** `3fr`. **Auto:** `auto`.
- **Edges:** 1–4 values in CSS order: `"20mm"`, `"10mm 20mm"`, `"10mm 20mm 5mm 15mm"`.
- **Column lists:** space-separated lengths, fractions or `auto`.
- **Colors:** `#rgb`, `#rrggbb`, `#rrggbbaa`, `rgb(…)`, named palette colors.
- **Booleans:** presence (`bold`) or `bold="false"`.
- **Typed values via Blade binding** (`:src="$model->photo"`, `:columns="[...]"`) are accepted as their PHP types: a length object, an array or an image resource.

## 7. Architecture

### 7.1 Pipeline

```
Blade view ─► component nodes (laravel) ─► Node tree
Node tree ─► Style resolve ─► Layout (page-aware) ─► Page display lists ─► PDF writer ─► bytes
```

### 7.2 Core units (`Papercut\…`)

| Unit | Responsibility | Depends on |
|---|---|---|
| `Value` | Length, Fr, Percent, Color, Edges, plus a strict string parser with "did you mean" suggestions. | — |
| `Node` | Immutable element definitions (data only). | Value |
| `Style` | Computed style per node: inheritance, theme defaults, named styles. | Node, Value |
| `Text` | Font loading (TTF/OTF parse), `FontRegistry` (family/weight/italic matching), the `Shaper` interface (`BasicShaper`: cmap plus GPOS/kern pair kerning; `HarfBuzzShaper`: FFI), Unicode line breaking (UAX #14 subset). | Value |
| `Layout` | Page-aware layout: one layouter per node type, plus flexbox and table algorithms. | Node, Style, Text |
| `Paint` | Converts laid-out fragments into per-page display lists (text runs, rects, paths, images, clips, links); resolves `{page}`/`{total}`. | Layout |
| `Pdf` | Writer: object model, xref, Flate streams, TrueType glyph subsetting, CFF whole-font embedding, ToUnicode CMaps, JPEG passthrough, PNG decode with SMask alpha, metadata, link annotations. | Paint, Text |
| `Document` (facade) | `save`, `toString`, `stream`; renderer options. | all |

### 7.3 Layout contract (page-aware fragmentation)

Every layouter implements:

```php
interface Layouter {
    public function layout(Node $node, ComputedStyle $style, Constraints $space, ?BreakToken $resume): LayoutResult;
}
// LayoutResult = Complete(Fragment) | Partial(Fragment, BreakToken) | DoesNotFit
```

- `Constraints` holds the available width (definite) and the available height on the current page (definite or unbounded).
- A `BreakToken` records where to continue: a line index for text, a row index for tables, a child index plus the child's own token for containers. Tokens are immutable values.
- **Page loop:** the page body is laid out into the content box. `Partial` produces a new physical page and resumes with the token. Header and footer are laid out per physical page into their reserved areas. Header and footer heights are measured once using the widest placeholder values (`{page}` and `{total}` filled with the total page count's digit width).
- **Keep rules:** `keepTogether` turns `Partial` into `DoesNotFit` unless the page is empty. `keepWithNext` treats a node and its next sibling as a unit when deciding a break. Text applies `min-lines` when choosing its split line.
- **Overflow:** `DoesNotFit` on an empty page is unrecoverable. In dev mode it throws `LayoutOverflow` with the node path (for example `page > column > view[2]`). In production mode it clips and reports through a warning callback (the Laravel logger). This is configurable.
- **Flexbox:** a single-line and multi-line (wrap) implementation following the CSS flexbox algorithm: basis, grow/shrink distribution, cross-axis alignment, gap. On the main axis of a column, children fragment in sequence. Rows fragment as a unit: a row is split only if every child can split, each child taking its own token. Otherwise the row moves to the next page.
- **Tables:** column widths are resolved once per table (fixed, then fr shares of the remainder, with `auto` measured from content min/max widths). Rows never split in v0.1, except a single row taller than a page, which splits cell-by-cell. Headers repeat on each continuation page.

### 7.4 Laravel package (`Papercut\Laravel\…`)

- **Components:** one Blade component class per tag, registered under the `pdf` namespace.
- **Blade → nodes without HTML:**
  1. Each component validates its attributes during Blade rendering, so an exception is raised inside the compiled view and Laravel's view-exception mapping reports the template file and line.
  2. Each component builds its node and stores it in a per-render `NodeRegistry` under an integer id. It returns a marker string `"\0pc:{id}\0"`.
  3. A container component's slot arrives as a string of markers interleaved with literal text. The container resolves the markers back to nodes in order. Whitespace-only text between block elements is dropped. Other text becomes an implicit `text` node, and inside text-bearing elements it is parsed for inline markup.
  4. The root `document` component's output is a single marker. `Pdf::view()` renders the view, resolves the root node and passes it to core.
  5. NUL bytes in user data are stripped before marker resolution so they cannot forge markers.
  6. Typed values bound with `:attr` arrive as PHP values directly through the component constructor, so nothing is converted to a string and lost.
  7. Structural validation happens at the container: for example, a `td` outside a `tr` raises `InvalidStructure` naming both tags.
- **`Pdf` facade / `PdfManager`:** `view()`, `make(Node)`, default options from config.
- **`PdfResponse`** (Responsable): `download($name)`, `inline($name)`, with correct headers.
- **Config `papercut.php`:** default page size and margin, font families (paths per weight and style), theme, named styles, overflow mode, the shaper preference (`auto`, `basic` or `harfbuzz`), and the preview route toggle.
- **Preview route (local env only, opt-in):** `/_papercut/{view}` renders a view with sample data from a registered provider and displays the PDF inline. Refreshing reloads it.
- **Artisan:** `papercut:fonts` lists the resolved font families and checks the files.

### 7.5 Fonts

- The 14 standard PDF fonts are not used. Every document embeds real fonts so glyph coverage and metrics are consistent.
- Papercut bundles one open-licensed default family (Inter or a similar sans, 4 styles, OFL) so it works out of the box.
- Users register families by path. Weight and italic matching follows the CSS font-matching algorithm (nearest weight).
- **Fallback:** a glyph missing from the primary family is looked up in configured fallback families. A missing glyph in all of them renders as `.notdef` and records a warning.

## 8. Error handling

- All exceptions extend `Papercut\PapercutException`.
- Types: `InvalidValue`, `UnknownAttribute`, `InvalidStructure`, `FontNotFound`, `ImageUnreadable`, `LayoutOverflow`.
- Messages name the element, the attribute and the bad value, and suggest a fix when the value is close to valid: `Invalid length "20 mm" for margin on <x-pdf::page>. Did you mean "20mm"?`
- Unknown attributes on `<x-pdf::*>` tags are errors, not silently ignored. Typos fail fast.

## 9. Testing

- **Unit tests:** the value parser (grammar fuzzing included), font parsing and subsetting (round-trip subset fonts through the parser), the line breaker and the flex algorithm.
- **Layout tests:** assert fragment geometry and break positions, not bytes. For example: "table splits after row 14; page 2 = header + rows 15–30".
- **Visual regression:** the examples and a fixture suite are rendered to PNG with `pdftoppm` (dev/CI only) and compared with approved snapshots within a pixel tolerance.
- **Validity:** every test PDF goes through `qpdf --check`.
- **Determinism:** with fixed document ID and dates, output is byte-identical across runs.
- **Laravel:** Testbench tests for components, markers, error file:line reporting and responses.
- **Performance benchmark** in CI against the §3 target; a regression over 20% fails the job.
- **CI matrix:** PHP 8.4 and 8.5, with and without FFI/HarfBuzz.

## 10. Out of scope for v0.1 (architecture leaves room)

RTL and bidirectional text, complex-script shaping without HarfBuzz, hyphenation, Knuth–Plass line breaking, multi-column text flow, SVG, AcroForms, digital signatures, PDF/A, encryption, editing or merging existing PDFs, table of contents, and bookmarks (outlines). Bookmarks are first in line for v0.2.

## 11. Open risks

- **Performance of pure-PHP font subsetting and line breaking.** Mitigation: cache parsed fonts per process (OPcache-friendly arrays), measure early, and use HarfBuzz FFI when present.
- **Blade marker approach edge cases** (components rendered outside a document, nested `Pdf::view` calls). Mitigation: registry scoped per render with a stack, plus clear exceptions.
- **Error line numbers depend on Laravel's compiled-view exception mapping.** The node path is always included as a fallback.
