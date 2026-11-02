# ColumnFlow: Word-style columns

Date: 2026-11-01. Status: design, awaiting approval. A 1.0 item.

## Intent

Some documents set their text in columns: newsletters, glossaries, academic papers. Papercut can't do that today. Its
"Current limitations" list in the guide and the root README names multi-column text flow.

The model is Word's columns, not CSS multi-column. Columns are a setting on a stretch of content. Content runs down
one column, continues at the top of the next, and moves to the next page after the last. Nothing spans columns. To put
full-width content above or below columns, you end one stretch and start another, like Word's continuous section
break. Making content fit a column is the author's job. Papercut doesn't shrink, reflow or second-guess what goes in.

## Goals

- A `ColumnFlow` container, usable from the PHP builder, Blade and HTML markup.
- Several flows on one page, with ordinary full-width content between them. The column count can change partway down
  a page.
- The columns end level at the bottom of every flow.
- Column breaks.
- The same result in PDF, HTML and Word export. Word gets real section columns that stay editable.
- An academic paper example that exercises all of the above.

## Non-goals

- Content that spans columns inside a flow (CSS `column-span`). Use two flows with the full-width content between them.
- Working out the column count from a minimum width (CSS `column-width`). The author gives the count or the widths.
- A `ColumnFlow` inside a table cell or one side of a flex row. Those split across pages side by side with their
  neighbours, which makes columns inside them much harder. They may come later if someone needs them.
- Column breaks from markup. Markup doesn't read break CSS today, and this doesn't add it.
- Footnotes. Papercut has none.

## Anti-goals

- Don't make it a CSS multi-column engine with every CSS option. The flow's settings stay the ones Word has.
- Don't add fit-to-column magic: auto-shrinking images or tables, or hyphenation added just for columns.
- No surprises from Word's own quirk: Word only levels columns before a continuous section break, but Papercut always
  levels them.

## Naming

`ColumnFlow`. In Blade, `<x-doc::column-flow>`. It must not be confused with the existing `Column`, which is a
vertical flex `View`. The user and I both landed on the name independently. "Columns" was rejected because it's one
letter away from `Column`. "Auto columns" was rejected because it sounds like a computed column count.

## Behaviour

### Settings

These match what a Word section can hold:

- **Count:** the number of equal-width columns, default 2.
- **Gap:** the space between columns. Default: my call, the builder picks a theme-consistent value near Word's 0.5 in
  (12.7 mm).
- **Widths** (optional): explicit unequal column widths instead of a count. The count follows from the number of
  widths. As in Word, each gap between columns can differ. The builder decides the exact signature, but it must
  express what Word's `w:col w:w w:space` can.
- **Rule** (optional): a vertical line in each gap. Word's separator is a plain on/off line. Papercut may let the PDF
  line take a colour and width, and Word then shows its own plain line. That drift is accepted under the "Word export
  uses real Word features" rule. It's my call whether the PDF line is styleable at all. Plain on/off is fine.

A flow's own style (text styles, spacing above and below) cascades into its content as on any container. The flow
itself takes no border, background or padding. To draw a box around columns, put the flow inside a `View` with that
style (my call: it keeps "box" and "columns" as separate ideas, and a box around a flow is then just nesting).

### Flow and pagination

- Content fills column 1 top to bottom, then column 2, and so on. After the last column it continues in column 1 of
  the next page, under that page's header and above its footer, as a page body does now.
- Text splits between lines across columns exactly as it does across pages. Tables split by rows, lists by items,
  following the same rules as page splits.
- `keepTogether` and `keepWithNext` apply at column breaks exactly as at page breaks. That lets a glossary term stay
  with its definition, and a heading with its paragraph.
- A `PageBreak` (or a `breakBefore`/`breakAfter` style break) inside a flow ends the page. What follows starts in column 1 of the next page, still in the flow.
- A new `ColumnBreak` node sends what follows to the top of the next column. In the last column it acts as a page
  break. Outside a `ColumnFlow` it's an error that names it. Word treats it as a page break there, but writing one
  outside a flow is a mistake, and Papercut says so.
- Overflow: content too wide or too tall for a column gets the same handling it gets anywhere else, the existing
  overflow modes and `LayoutOverflow` errors with element paths. Paths include the flow, for example
  `page > body > column-flow > image[1]`.

### Ending level (balancing)

A flow's last page is balanced: its columns end as close to level as the content allows, and whatever follows the flow
starts below the tallest column. Pages the flow fills completely aren't balanced. They're simply full, as in Word. A page cut short by a forced break
(a `PageBreak`, or a `ColumnBreak` in the last column) isn't balanced either: its columns fill in order, as in Word,
because the flow hasn't ended there (my call, from the builder check). Keep
rules and unsplittable content can make the columns uneven. Getting them as level as the rules allow is enough. The
algorithm is the builder's choice. A search for the shortest column height that holds the remaining content is the
obvious one.

### Placement

A `ColumnFlow` can sit directly on the page or inside any box: a `View` with a border, background, padding or set
width, a decorator such as a card, a list item, or another box nested in those. It takes the width its container gives
it, and splits across pages along with that container, the way boxes already split. The user's ruling: placement isn't
restricted just to match Word.

It can't sit inside a **table cell** or inside a **flex row** (a `Row`, or a `View` with row direction). Those split
across pages in parallel with their neighbours, and columns inside them are out of scope for now.

- In the **builder and Blade**, a flow inside a cell or a row throws a clear error that names the flow's path and the
  offending ancestor.
- A flow made by **markup** never throws for its placement. Markup content is often stored rich text, and a render
  must not start failing after an upgrade. If it ends up inside a cell or row, whether from the HTML itself or because
  the caller put the markup there, it lays out as ordinary single-column content and records a fidelity note. The flow
  carries a marker saying it came from markup, so layout can tell the two apart.
- A `ColumnFlow` inside another `ColumnFlow` follows the same rule as a cell (my call): error from the builder, one
  column with a note from markup.

Headers, footers, backgrounds and overlays can't contain a flow (my call). They're laid out once per template and
don't flow across pages.

For Word, "directly on the page" still matters: see Word export. A flow is **on the page** when only plain wrappers lie
between it and the page body. A **plain wrapper** is a `View` that adds nothing visible or size-changing: no border,
background, padding, horizontal margin, width/min/max width, height/min/max height, `keepTogether`, flex row direction,
or alignment other than stretch. Margins above and below don't count against it. Markup's outer `View` is plain.

### Interaction with existing features

- Links, bookmarks, headings in the table of contents, and page numbers work inside flows unchanged.
- The `Toc` element inside a flow isn't a goal. The builder can let it work or forbid it, whichever is simpler, and
  document it.
- A `PdfPage` template page with a flow behaves like any other page.

## Input

### PHP builder

`ColumnFlow::make(...$children)` with fluent setters for the settings above, in the style of the existing elements
(`View`, `Table`). `ColumnBreak::make()` like `PageBreak::make()`.

### Blade

`<x-doc::column-flow>` with attributes for the settings, and `<x-doc::column-break />`, following the existing Blade
components' conventions.

### Markup (HTML input)

A `div` (or any block the converter maps to a container) whose `style` attribute sets `column-count`, `columns`,
`column-gap` or `column-rule` becomes a `ColumnFlow`. Like every other inline style, this is read only in
`StyleMode::Original`. In the default `StyleMode::Theme`, markup ignores author CSS, so the content stays one column
(the user's ruling). Markup reads no stylesheet rules today, and this doesn't add them. Column CSS it can't express,
such as `column-span` or `column-width` alone, gets a note. A box style on the same `div` (border, background,
padding) becomes a `View` around the flow, per Settings.

### Tailwind translator (site page)

Map `columns-N`, `gap-x-*` on a columns element and `divide-x` (as the rule) to `<x-doc::column-flow>`. The translator
isn't a package feature, so this is a small site change in the same release.

## Output

### PDF

Laid out as above by the core layout engine.

### HTML export

CSS multi-column (`column-count`, `column-gap`, `column-rule`, `column-fill: balance`) on the flow's element, and
`break-before: column` for `ColumnBreak`. The browser's flow won't match the PDF line for line, and that's accepted, as
for pages today.

### Word export

Each flow **on the page** (see Placement) becomes its own Word section with a continuous start and `w:cols` (count or per-column widths and spaces,
`w:sep` for the rule). The full-width content after it gets a continuous section too, with one column. `ColumnBreak`
becomes a Word column break (`w:br w:type="column"`). Word levels columns before a continuous section break by itself,
so a flow with nothing after it on its Page template (the last flow in the document, or one followed by the next
template's new-page section) also needs a continuous break after it to stay level.

A flow nested in a box can't be a Word section, since Word has no columns inside boxes. It comes out as one column,
with a fidelity note, the way other Word drift is reported. This drift is accepted under the "Word export uses real
Word features" rule.

`DocxExporter::sections()` assumes today that every section starts a new page (its leading-break handling, the popped
trailing page break, and the grouping of alike pages). It needs reworking to split a template's body into continuous
sections. That's expected builder work.

php-docx can't write `w:cols` or column breaks today (it has continuous section starts, `SectionStart::Continuous`).
The user's ruling: **`DocxPatcher` writes them from the start**, in its usual style of editing the exact markup
php-docx writes and failing loudly when that markup changes. ColumnFlow can then merge and ship whenever it's ready,
without waiting on upstream.

Alongside, send an **upstream php-docx PR** that adds column settings to its section setup, plus a column break run.
Send it from the personal fork (remote `personal`, serpentblade/php-docx) so the maintainer can edit it, with one
clean commit per item. Once php-docx releases it, swap the patch for the real API, as a follow-up.

## Example and docs

- A new example `examples/core/documents/paper.php`: an academic paper on A4, which keeps the Letter/A4 split even.
  Generic content, not tied to any real institution or person. It has:
  - a full-width title, authors and abstract
  - a two-column body with headings (keep-with-next across a column break), a table and a figure sized to one column
  - columns ending level where the body ends
  - a full-width closing note below
- Its golden PDF, HTML and DOCX outputs, like the other examples.
- A guide page or section on `ColumnFlow`: settings, placement rule, balancing, column breaks, the markup CSS it reads,
  and Word behaviour.
- Remove multi-column text flow from "Current limitations" in the guide and README.
- A gallery tile for `ColumnFlow`, with variants (for example the rule, or unequal widths), following the existing
  gallery conventions.
- CHANGELOG entry under Unreleased.

## Testing

- Layout tests: filling and continuing across columns and pages; balancing on the last page; keep rules at column
  breaks; `ColumnBreak` mid-column and in the last column; `PageBreak` inside a flow; several flows on one page; overflow
  paths.
- Placement tests: flows inside boxes laid out at the box's width and split with it; flows in a cell, a row or another
  flow rejected with the right error (builder) or one column plus a note (markup, including markup the caller placed
  in a cell); the plain-wrapper test that decides Word section columns.
- Word tests for a nested flow: one column plus a fidelity note.
- Markup tests for each CSS property it reads.
- HTML and Word export tests, including the Word section sequence for flow / full-width / flow on one page, and the
  trailing continuous break.
- Goldens may change before 1.0. Report any intended changes. Unexpected ones are bugs.

## Left to the builder

- Exact setter names and signatures, within the settings above.
- The balancing algorithm.
- Default gap value.
- Whether the PDF column rule is styleable.
- Whether `Toc` works inside a flow.
- How the layout engine represents a flow internally, for example a new layouter alongside `BlockLayouter`, and how
  break tokens carry column position.
