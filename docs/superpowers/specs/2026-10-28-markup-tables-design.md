# Papercut: Tables and Rules in Markup, Rowspan in Core — Design

**Status:** agreed with the maintainer, 2026-10-28. Items marked *(my call)* were decided by the designer, not the
maintainer, and are open to review.

## 1. Intent

A consuming app moved its curriculum exports onto Papercut 0.3.0, and the tables in its editor content come apart.
Markup (`Markup::html()` / `<x-doc::markup>`) drops `<table>` and keeps every cell as its own paragraph, so a rubric
or a schedule turns into a list of loose words. Evidence from that app's dogfooding report:

- Several hundred content modules hold a `<table>`, used for rubrics, schedules and worksheets.
- A large pasted statistics table (many tables, hundreds of rows, `colspan` used) takes more than twice as many
  pages as the app's old PhpWord + LibreOffice route, because every cell becomes a paragraph.
- `<hr>` appears in a few hundred modules and is dropped today.

The core `Table` already splits across pages, repeats its header and has `colspan`. It has no `rowspan`. The
maintainer chose to add rowspan to the core table now, in all three outputs, rather than drop it from markup: "It'll
come up and it's a nice feature."

This design adds three things, shipped as one PR:
1. **Rowspan in the core table**: PHP, Blade, PDF layout, DOCX and HTML.
2. **Tables in markup**: `table`, `caption`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td` become a core `Table`.
3. **`<hr>` in markup** becomes a horizontal rule.

## 2. Goals, non-goals, anti-goals

**Goals**
- A table in markup comes out as a table in PDF, Word and HTML, with spans kept and the header repeated
  across pages.
- A long statistics table takes about as many pages as with content-sized columns in a browser. Checked by hand (§8).
- `TableCell::rowspan()` and `<x-doc::td rowspan="…">` work for hand-built tables too, not only for markup.
- Messy editor HTML degrades with notes, never an exception (outside strict mode, as today).

**Non-goals**
- Real nested tables. The consuming app's content has none.
- `<pre>` and its monospace font. That is the next item on the list and needs a font decision.
- Border collapsing, `border-spacing`, `cellpadding`, `cellspacing`, `bgcolor`, `align`/`valign` attributes.
- `<colgroup>` / `<col>` widths.

**Anti-goals** (failures even if it works)
- Any change to PDF golden bytes or HTML/DOCX goldens for documents that don't use rowspan, markup tables or `<hr>`.
- Content lost at a page break inside a rowspan.
- One malformed table failing the whole render outside strict mode.
- A flood of notes for ordinary editor noise (`border="1"`, `cellpadding`, `class`, `style` in theme mode).

## 3. Constraints

- PHP `^8.4`, strict types, PHPStan level 8 in all packages, no new runtime dependencies.
- The converter stays exporter-agnostic: it produces nodes, and exporters never see HTML.
- php-docx (`^1.18`) already models vertical merges: `CellStyle` has `rowSpan` and `vMergeContinue`. No upstream change
  is needed.
- Untrusted input: the converter's existing rules for links and images apply inside cells unchanged.

## 4. Rowspan in the core table

### 4.1 API

- `TableCell::rowspan(int $n): self`, at least 1, mirroring `colspan()` (throws `\InvalidArgumentException` below 1).
  `TableCell::rowsSpanned(): int` reads it. *(my call: keep `span()` as the column span for compatibility; add
  `colSpan()` as an alias only if it reads better next to `rowsSpanned()` in the code.)*
- Blade: `<x-doc::td rowspan="2">` and `<x-doc::th rowspan="2">`, validated like `colspan` (`Td::ownAttributes()`).
- Docs: the tables guide gains a rowspan example next to colspan.

### 4.2 The cell grid

Rows no longer map cells to columns by counting along the row. Each table section is resolved into a grid using the
HTML table model: walking a row, a cell takes the first column not already covered by a rowspan from a row above.
Header rows and body rows are separate sections, as in HTML: a rowspan never crosses from the header into the body.

- A rowspan that runs past the end of its section is clamped to the section, with a warning on the layout context
  (`Table cell rowspan runs past the last row; clamped`). *(my call)*
- The column count is the widest grid row, counting covered slots. This replaces the "widest row's total span" rule in
  `TableLayouter::trackList()`. Exporters that compute columns themselves (DOCX `Frame`, `HtmlWriter`) use the same
  resolved grid, so the three outputs agree. One shared resolver in core (for example `Papercut\Layout\TableGrid`) is
  the builder's to shape.
- Rows shorter than the grid leave empty slots, as today.

### 4.3 PDF layout

- **Heights.** Each row's height comes from its single-row cells. A spanning cell's height covers its rows, the row
  edges between them included. If it needs more than those rows give, the shortfall is spread evenly over the rows it
  spans. *(my call; browsers spread it in proportion, and even spreading is simpler and close enough.)*
- **Painting.** A spanning cell's box runs across row boundaries. Row backgrounds of later rows must not paint over it:
  paint the spanning cells after the row boxes they cross.
- **Column sizing.** A rowspan cell counts toward its column's min/max-content like a single-row cell (for its own
  colspan, as today).
- **Rowspan groups.** Rows tied together by rowspans form a group: the smallest run of rows that no span crosses out
  of. A row with no rowspans into or out of it is a group of one, so tables without rowspan lay out exactly as today.
- **Page breaks.**
  - A group that fits in the space left is placed whole.
  - A group that doesn't fit, with rows already on the page, moves to the next page, like a row today.
  - A group taller than a fresh page splits. Its rows are placed in order; a spanning cell is laid out to the height
    of the rows of its span placed on this page, and continues on the next page with a break token, alongside the
    rest of its rows. A single row too tall for a page still splits in parallel as today, with any spanning cell that
    covers it taking part in that split. The repeated header opens each continuation as today.
  - No content is lost or drawn twice at any break. The builder chooses the token shape (today's `BreakToken` holds a
    row index and per-cell parallel tokens; a group split also needs the spanning cells' tokens).
- `keepTogether` on the table keeps its current meaning.

### 4.4 DOCX

- A spanning cell is written with `CellStyle(rowSpan: n)` (`w:vMerge w:val="restart"`), and each row it covers gets a
  continuation cell at that grid column (`vMergeContinue: true`, `<w:vMerge/>`) with the same width and `gridSpan`.
- `TableMapper::cell()` and the walk's `Frame->column` bookkeeping use the resolved grid, so continuation cells land
  in the right column.
- Rows stay `cantSplit: true`. Word paginates the table itself; a merge group may break between rows in Word. That is
  acceptable.

### 4.5 HTML

- `rowspan="n"` on the `td`/`th`, next to `colspan`. The colgroup count uses the resolved grid.

## 5. Tables in markup

### 5.1 Structure

| HTML | Becomes |
|---|---|
| `table` | a `Table`, carrying the named style `markup.table` |
| `thead` rows | header rows, repeated on every page |
| `tbody` rows, and `tr` directly in `table` | body rows, in document order |
| `tfoot` rows | body rows at the end of the table, not repeated *(my call)* |
| `tr` | a `TableRow` |
| `td` | a `TableCell` with the named style `markup.td` |
| `th` | `TableCell::th()` with the named style `markup.th` |
| `caption` | a paragraph above the table *(my call)* |
| `colgroup`, `col` | ignored, without a note |

- **Header without `<thead>`.** When a table has no `thead`, every leading row that holds only `th` cells is part of
  the repeated header, up to the first row with a `td`. If every row is all-`th`, none repeat. Editors (TinyMCE,
  CKEditor) often write headers this way, and pasted statistics tables often open with two or three such rows. *(my call)*
- **Content.** A cell's content converts in its own frame, like a list item's (`collect()`), so paragraphs, lists,
  images, links and inline formatting inside cells work. A lone `p` in a cell is made bare, as in list items.
- **Spans.** `colspan` and `rowspan` are read with the HTML integer rules (`integer()` already exists). Values below 1
  become 1, `rowspan="0"` means "to the end of the section" as in browsers, and values above 1000 are clamped to 1000
  as browsers do. Clamping a span adds a note. *(my call)*
- **Empty tables.** A table with no cells is nothing, as an empty list is today. A row with no cells is dropped.
- **Stray parts.** `tr`, `td` or `th` outside a `table` keep today's behaviour (content as paragraphs, one note).
  Text directly in `table`, `tbody` or `tr` (outside a cell) is moved before the table, as browsers do.
- **Nested tables.** A table inside a cell is flattened into paragraphs in that cell (today's unknown-block
  behaviour), with a note `markup nested <table> (flattened)`. *(my call)*
- **Inside a text tag.** `Markup::flatten()` needs a branch for `Table` (and the `<hr>` node): a table's cell text is
  kept, cells joined by spaces and rows by line breaks, and reported as Approximated; an `<hr>` becomes a line break.
  Today `flatten()` drops unrecognised nodes at Dropped level, which would throw in strict mode.

### 5.2 Column widths

Columns are sized by their content, like the old PhpWord route's `table-layout: auto` and like browsers. The maintainer
asked for this over equal columns, and asked to keep it simple.

- **Theme mode** (the default): every column is an `auto` track. The core already sizes `auto` tracks the browser way:
  max-content when everything fits, min-content when even that overflows, and in between in proportion to
  (max - min). A narrow number column stays narrow and a long label column takes the rest. No new sizing algorithm.
- **Leftover width.** Today, `auto` columns that fit leave an empty strip inside the table's box, because the box
  is always full width. Markup tables must not show that strip: the leftover width is shared among the `auto` columns
  in proportion to their max-content, as browsers do for a full-width table. *(my call: full width rather than
  shrinking the table, since editor tables are usually full width.)* Hand-built tables must not change, so this is
  opt-in on the `Table`; the builder picks the API (a method or a style flag) and must keep DOCX and HTML matching.
- **Original mode:** a cell's `width` attribute (`120`, `120px`, `25%`) or `style="width:…"` sets its column's track
  (a `Length` or `Percent`), the first one met in the column winning. Cells spanning several columns don't set widths.
  Columns without a width stay `auto`. A `width` on the `table` itself, attribute or style, sets the table's width.
- HTML and DOCX already handle `auto` tracks (`table-layout: auto`; `TableMapper::settle()`), so nothing new is
  needed there beyond the rowspan work.
- Page counts are checked by hand, not as a pass/fail test.

### 5.3 Look

- The theme decides. Three named styles are added to `DefaultStyles`, so apps can restyle them through
  `papercut.styles` like any decorator style:
  - `markup.table`: a thin grid: 0.75pt `slate-300` border on the table's top and left. *(my call on values)*
  - `markup.td`: 0.75pt `slate-300` border on the right and bottom; padding left to the theme's `cellPadding`.
  - `markup.th`: as `markup.td`, plus a `slate-50` background. Bold already comes from `th`.
  Giving each cell only its right and bottom edge keeps lines one border thick without border collapsing.
- **Original mode:** a cell's or table's inline `style` applies on top, through the existing `InlineCss` block
  properties (background, alignment, padding, sizes). `text-align` on a cell aligns its text. Other declarations are
  dropped and noted as today. `border="0"` or `border` styles are not read; the grid stays. *(my call)*
- Space around the table follows the surrounding blocks' gap, like a list.

### 5.4 Notes

The converter's existing note rules apply: `markup <table> (…)` notes at the element's path, once per feature where a
table would repeat them per row. Ordinary attributes (`border`, `cellpadding`, `cellspacing`, `class`, `id`,
`summary`) produce no notes.

## 6. `<hr>` in markup

- `<hr>` becomes a horizontal rule: a full-width block with no content, a bottom border and some space above and
  below, carrying the named style `markup.hr` (0.75pt `slate-300`, a few points of vertical margin). *(my call on
  values)*
- It ends the paragraph around it, as any block does.
- Original mode: `style` on the `hr` applies on top (for example a colour).
- DOCX writes it as an empty paragraph with a bottom border, not as a one-cell table. HTML writes `<hr>` or an
  equivalent bordered block. The builder decides whether this is a new tiny node or a styled `View` that the DOCX
  mapper recognises.

## 7. Docs

- `site/src/guide/rich-text.md` "What gets converted": add rows for tables and `hr`. Fix the stale row that still
  says `blockquote` is dropped; it has converted since 0.3.0 (and `mark` too). The remaining "other block tags" row
  names `pre`.
- The tables guide: `rowspan`, in PHP and Blade.
- CHANGELOG entries for all three parts.

## 8. Testing

- Core: rowspan grid resolution (clamping, `rowspan="0"`, rows shorter than the grid, header/body separation); PDF
  layout of rowspan groups, including a group taller than a page and a spanning cell in a row that splits; painting
  order (a later row's background doesn't cover the spanning cell).
- Converter: every row of §5.1, widths in both style modes, nested-table flattening, stray parts, `flatten()`, notes.
  Fixtures are generic tables written for the tests, shaped like real editor content: a statistics table with long
  labels beside short numbers, several leading `th` rows, colspan and a single-cell footnote row; and a rubric-style
  grid with paragraphs and lists in its cells. No content from consuming apps goes in the repo. *(maintainer's call)*
- DOCX: `vMerge` restart/continue cells in the right grid columns, with widths, for spans in the first, middle and
  last column.
- HTML: `rowspan` attribute and column count.
- Goldens: existing goldens unchanged. New goldens for one rowspan table and one markup table, in each output.
- Regression check by hand, outside the repo: render a consuming app's real tables before and after and compare
  page counts and look.

## 9. Left to the builder

- The shape of the shared grid resolver, the break token for group splits, and how spanning cells are painted.
- Whether `<hr>` is a new node or a styled `View`.
- Exact style values for `markup.*` within the look described in §5.3.
- How to split the PR's commits.
