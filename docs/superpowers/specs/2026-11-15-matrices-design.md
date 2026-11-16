# Matrices and cases (papercut/math)

Status: design, 2026-11-15. Chosen for completeness, not for a document that needs it. Targets the release after 0.13.
Background: `2026-11-13-equations-design.md` (non-goals) and `2026-11-14-mathml-input-design.md` (§ "mtable ... come next").

## 1. Intent

Math without matrices feels unfinished. Today a MathML `mtable` is drawn as a flat row of cells with an "approximated"
note (`MathmlReader::table()`), and the builder API has no way to make one. This adds a real grid: a matrix with
stretching brackets, and `cases` (a left brace with aligned rows). It lays out in PDF and HTML-as-SVG, writes native
MathML and Word OMML, and reads `mtable`.

## 2. Goals, non-goals, anti-goals

**Goals**
- A grid node that lays out like TeX's `matrix` and `cases`, centred on the math axis so it lines up with a neighbouring `=`.
- Builders `Math::matrix()` and `Math::cases()`; MathML `mtable`/`mtr`/`mtd` read into the same node.
- A native Word equation matrix (`m:m`), per the project rule that Word export uses real Word features.
- The "tables are drawn as rows of cells" note goes away for plain tables.

**Non-goals (this round)**
- Aligned multi-line equations (`align`-style alignment points, `groupalign`, `alignmentscope`) and equation numbering
  (`mlabeledtr` labels stay dropped, with a note).
- Spanning cells (`rowspan`, `columnspan`), table lines and frames (`columnlines`, `rowlines`, `frame`), fixed
  `columnwidth`, `equalrows`/`equalcolumns`, and spacing attributes (`columnspacing`, `rowspacing`). Each present
  attribute gets an `approximated` note naming it (format in §3.3).
- New Blade tags or markup syntax. Markup and Blade get matrices through MathML `mtable`, as they get every other formula.
- Per-cell style changes beyond what a cell's own content carries.

**Anti-goals**
- Output changes for formulas without a table (goldens for every existing fixture stay byte-identical).
- A matrix drawn as glyph paths or a picture in Word; Word must get an editable `m:m`.
- A grid that is not a drop-in for existing nodes: it nests inside `Row`, `Fenced`, `Fraction` and `Scripts` like any node.

## 3. Behaviour

### 3.1 The grid node

New node `Papercut\Math\Node\Grid` (my call on the name; `Table` would clash with Papercut core's `Table`). It holds
rows of cells (`list<list<Node>>`), **one alignment per column** (`left|center|right`), **one row alignment for the whole
grid** (`baseline|top|center|bottom|axis`), and whether cells are set in display style. Nothing finer is stored: Word's
`m:mcJc` is per column only, and a node that holds only what every writer can express keeps "write then read gives an
equal grid" true *(my call, from the builder check)*.

Layout (TeX `matrix`, with the numbers below being my reading of plain TeX and amsmath, all in the grid's own style level):
- **Cells** are set in text style (script level unchanged) unless the grid is `displaystyle`. This is TeX's behaviour: a
  fraction inside a matrix cell is the small one.
- **Column width** is the widest cell in the column. **Column gap** is 1 em (TeX `\quad`).
- **Row spacing (no struts):** a row's ascent and descent are those of its tallest cells, measured from their real
  extents. The baseline-to-baseline distance between a row and the next is
  `max(1.2 em, descent(row) + ascent(next) + 0.1 em)`: rows sit 1.2 em apart (TeX's `\normalbaselines`) unless tall
  content would leave less than 0.1 em between them (TeX's `\lineskip`), and then they are pushed apart to 0.1 em.
  The grid's height runs from the top of the first row to the bottom of the last. *(My call, from the builder check:
  the first draft had struts and a gap on top of them, which double counted.)*
- **Row alignment** within a row: `baseline` (default, cells share the row baseline), `axis`, `center`, `top`, `bottom`.
- **Column alignment** per column: `center` by default for `matrix`, `left` for `cases`.
- **Ragged rows** are padded on the right with empty cells (my call; MathML allows ragged rows).
- **Vertical position:** the whole grid is centred on the font's math axis (`axisHeight`): ascent is half the height plus
  the axis, descent half the height minus it.
- An empty grid (no rows, or only empty rows) lays out as an empty box and writes nothing; builders throw instead (§3.2).

### 3.2 Builders

```php
Math::matrix(array $rows, string $fence = 'paren', string|array $align = 'center'): Node
Math::cases(array $rows): Node
```

- `$rows` is `list<list<string|Node>>`, strings tokenized as everywhere else in `Math`.
- `$fence`: `paren` `( )`, `bracket` `[ ]`, `brace` `{ }`, `bar` `| |`, `doublebar` `‖ ‖`, `none`. The fence is a `Fenced`
  around the `Grid`, so it stretches with the existing logic. Anything else throws `InvalidArgumentException`.
- `$align`: one value for every column, or a list with one value per column (a short list repeats its last value).
- `Math::cases($rows)` is a `Fenced('{', '', Grid)` with every column left-aligned. A thin space (3 mu) separates the
  brace from the grid, as in TeX. It is a layout rule inside `Fenced::layout` (a `Fenced` with a `{` opener, no closer
  and a `Grid` as content), not a spacer node: a spacer node would break the MathML round trip, because the reader
  fences a lone opener only when the table follows it directly. Rows hold a value and optionally a condition;
  the condition is not made upright text automatically, so use `Math::text()` for words.
- No rows, or a row with no cells, throws `InvalidArgumentException`.

### 3.3 MathML in

`MathmlReader` builds a `Grid` from `mtable`, replacing `table()`/`tableRows()`.
- `mtr` and `mlabeledtr` rows, `mtd` cells; a cell holding several children is a `Row`, as `children()` does today.
- **Honoured:** `columnalign` and `rowalign` **on the `mtable` only** (`columnalign` is a space-separated list that
  cycles across columns; `rowalign` takes its first value: `baseline`, `axis`, `center`, `top`, `bottom`), and
  `displaystyle` on the table. `columnalign`/`rowalign` on an `mtr` or `mtd` cannot be stored (§3.1) and are ignored with a
  note *(my call)*.
- **Ignored with a note:** the non-goal attributes above, and `columnalign`/`rowalign` on `mtr` or `mtd`. A note is
  `MathNote(Approximated, <element carrying the attribute>, '<attribute> is not drawn', <where>)`: the element is
  `mtable`, `mtr` or `mtd` as appropriate, the reason is `<attribute> is not drawn`, and `where` is the reader's indexed
  path (`math > mrow[0] > mtable[1]`). Core prints it as `math <mtable> (rowlines is not drawn)`. The reader's existing
  dedupe applies unchanged: one note per element name and reason per formula, so a table with fifty rows each carrying
  `rowspan` notes once *(my call: it matches how every other note is reported and asserted in core, docx and laravel)*.
  Update the core, docx and laravel tests that assert today's `tables are drawn as rows of cells` note.
- **`mlabeledtr`:** the first child is the label; it is not drawn and carries the existing "row label not drawn" note.
- A plain `mtable` (none of the ignored attributes, no labels) produces no note at all.
- **Fences around a table.** `mfenced` is read as today. In an `mrow`, `pair()` fences only matched pairs, so a
  matrix written `<mo>(</mo><mtable>…</mtable><mo>)</mo>` already becomes a `Fenced`. A lone opener before an `mtable`
  does not: the usual MathML for `cases` is `<mrow><mo>{</mo><mtable>…</mtable></mrow>`, sometimes with an empty closing
  `<mo>` the reader drops, and today that draws a small plain `{`. **Change `pair()`:** a stretchy opening fence whose
  next sibling is an `mtable` and which has no matching closer becomes `Fenced(opener, '', Grid)`. This keeps
  `Math::cases()` surviving a MathML write and read, and makes `cases` work through markup and Blade *(my call: it was a
  stated goal, and our own writer's output hits this)*. Only a fence directly before a table is affected.
- Depth accounting: the table keeps counting two levels toward the nesting limit (`$this->depth += 2` today) and the
  256-deep hardening limit and node-count limits stay.

### 3.4 Writers

- **MathML:** `<mtable>` with `<mtr><mtd>` rows. Write `columnalign` only when a column is not centred, `rowalign` only
  when not `baseline`, `displaystyle="true"` only when set. A `Fenced` around a grid is written as it is today.
- **OMML:** `<m:m>` with `<m:mPr>` holding `<m:mcs>` (one `<m:mc><m:mcPr><m:count m:val="1"/><m:mcJc m:val="center|left|right"/>
  </m:mcPr></m:mc>` per column) and rows as `<m:mr>` with one `<m:e>` per cell. A `Fenced` around it is `m:d` as today.
  An empty cell is written as a run holding one space (`<m:r><m:t xml:space="preserve"> </m:t></m:r>`), because LibreOffice
  shows a red "¿" and collapses the rows for an empty `m:e`. `<m:plcHide m:val="1"/>` stays in `m:mPr` for Word; **the
  Word check of an empty cell is still outstanding**. Word's own `cases` is an equation array, but a left-aligned matrix inside
  `m:d` with a `{` delimiter is the same on screen and edits as a matrix; use that.
- **TextWriter:** single line only (this writer feeds table-of-contents entries, PDF outline titles and slugs, not display).
  A grid is its rows joined by `; `, each row's cells joined by a space, inside the fence characters: `[a b; c d]`,
  `(a b; c d)`, `|a b; c d|`. `cases` has no closing character: `{a, b; c, d`. *(My call; the builder may choose a more
  readable form for `cases` as long as the one hard rule holds: a grid never produces a newline.)*
- **SVG** (HTML export): drawn by the same layout boxes as everything else; no new renderer code.

### 3.5 Where it shows up outside `papercut/math`

Core and Laravel need no new API. Equations enter core as `Equation::of($mathNode)`, as `Span::math()`, and as MathML
in markup and Blade. All of them go through `papercut/math`, so a matrix works wherever a formula does, including the
Word export through `OmmlWriter`. Check core's tests that assert the `mtable` approximation note
(`MathNotes`, `HtmlConverter` paths) and update them: a plain table no longer notes.

## 4. Constraints

- PHP ^8.4 floor; run suites on 8.4 as well as the local 8.5.
- Output for formulas without tables is byte-identical (every existing golden, SVG snapshot and OMML fixture unchanged).
- `Grid` is a public node class like `Fenced`, `Row` and `Fraction`; only its `layout()` and `children()` methods carry
  the `@internal` tag the rest do, and `ArchitectureTest`'s `INTERNAL` list is unchanged. Layout helpers it introduces
  (if any) are `@internal` and join that list *(my call, from the builder check)*.
- Hardening: the only limit in the reader is `MAX_DEPTH` (256), and the table keeps counting two levels toward it. No new
  node or cell cap is added: layout of a grid is linear in its cell count (one measuring pass, then placing), and
  absurd documents are not ours to chase (the project's standing rule on fuzzer-only layouts). A test lays out a grid of a few
  thousand cells and asserts it stays fast and bounded in memory, as a regression check for accidental quadratic work
  *(my call: the first draft cited limits that do not exist)*.
- Invalid UTF-8 in cells goes through the same scrubbing as other text.

## 5. Tests and docs

- Layout: a 2x2 matrix's column widths, row distance and axis centring against numbers worked out from the font
  constants; a tall cell (a fraction) pushes the row apart; ragged rows pad; each column alignment.
- Fences: all six, stretching to the grid height; `cases` shape.
- MathML: reading each honoured attribute and each ignored one with its note; a plain table gives no note; `mlabeledtr`.
- Writers: MathML round trip (write then read gives an equal grid), OMML structure for matrix and cases, TextWriter
  single line with no newline for any grid.
- Snapshots: the fixture-driven MathML and OMML ones (a matrix, a determinant, `cases`, and a matrix beside an `=`),
  plus layout-number tests for axis alignment. SVG snapshots exist only for the editor fixtures, so they change only
  where those fixtures hold a table.
- Docs: `packages/math/README.md` builder list and the `Approximated` paragraph (it names `mtable` today),
  `site/src/guide/equations.md`, and the equations gallery source `site/gallery/sources/equations.php`.
- Changelog entry; no version bump in the work itself.

## 6. Left to the builder

Exact `cases` brace spacing; the single-line `cases` text form; the wording of the new notes (must keep the existing
format); the row-strut numbers if the goldens look cramped (they are TeX's, adjust only with a stated reason);
whether `Grid` needs a `Row`-like fast path for nesting limits.
