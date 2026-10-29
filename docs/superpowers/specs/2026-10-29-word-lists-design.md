# Papercut: Real Word Lists and Space After Lists — Design

**Status:** agreed with the maintainer, 2026-10-29. Items marked *(my call)* were decided by the designer, not the
maintainer, and are open to review.

## 1. Intent

Papercut writes every list in a Word export as plain paragraphs: a typed marker (`•`, `◦`, `1.`), a tab and the
text, with a hanging indent. The package has no `word/numbering.xml` and no `w:numPr`. `ListMapper.php` says this
was deliberate, so the markers match the PDF exactly in every reader.

It isn't a real list to the person editing the file. Enter doesn't continue it, renumbering and bullet styles do
nothing, and Apple Pages lays it out wrong because it doesn't treat the hanging indent as a tab stop. The
maintainer's rule (2026-10-29): **in Word export, native Word behaviour wins over exact parity with the PDF.**
"The live doc vs intentionally rendered PDF has to have its differences."

Separately, in both PDF and Word, a list has no space after its last item, so a following paragraph or list sits
right under it.

This design:
1. Writes lists in Word export as **real Word numbering**.
2. Gives a list **space after it** in PDF, Word and HTML.

Dogfooding also reported that PDF number markers aren't right-aligned. That doesn't reproduce: in core and markup
lists, every marker's right edge sits at the same x and item text starts at the same x (checked with
`pdftotext -bbox` on a 12-item ordered list). A regression test pins this (§6). No change. *(my call)*

## 2. Goals, non-goals, anti-goals

**Goals**
- In Word, LibreOffice, Google Docs and Pages, every list is a real list. Enter continues it, renumbering
  works, and the bullet or number style can be changed from the toolbar.
- Each papercut list numbers on its own: it starts at its start number and doesn't continue a previous list.
- Nested lists sit one level deeper and keep papercut's marker per depth (•, ◦, ▪; 1., a., i.).
- A list item holding more than one block (a second paragraph, a table, an image) keeps those blocks under the
  item, indented to its text, and the numbering carries on after them.
- A top-level list has space after it, the same as a paragraph, in PDF, Word and HTML.

**Non-goals**
- Multi-level numbering labels such as `1.1.`, or outline numbering for headings.
- Word list styles from a template.
- Exact PDF parity in the cases listed under §4.4.
- Changes to HTML lists other than the spacing.

**Anti-goals** (failures even if it works)
- Two lists in a row numbering on as one list in Word (the second starting at 4).
- Extra blocks inside an item breaking the list into separate lists in Word.
- A golden or snapshot without lists changing (§7).
- Examples, tests, fixtures or docs framed around one consuming app's documents.

## 3. Constraints

- PHP `^8.4`, strict types, PHPStan level 8 in all packages, no new runtime dependencies.
- php-docx `^1.18` can write lists (`Element\ListNode` / `ListItem`, `NumberingXmlBuilder`), but a php-docx list
  item holds inline content plus one nested list only, and `ParagraphStyle` has no numbering properties. Papercut
  items can hold any blocks, so papercut can't use php-docx's lists as they are.
  `NumberingXmlBuilder::instanceFor()` also gives two lists with the same format and start the same `numId`, which
  makes Word continue the numbering from one to the other.
- **So, as with mirrored margins:** papercut writes numbering through `DocxPatcher` now, and a pull request to
  php-docx through the maintainer's fork (remote `personal`) adds paragraph-level numbering. When a release
  includes it, papercut switches over and drops the patch. Papercut doesn't wait for that release. *(my call,
  following the maintainer's choice for mirroring)*
- `DocxPatcher` edits only markup it can find exactly, and throws when php-docx's output changes.
- This work builds on the `page-variants` branch: it reuses the part-creation code (part, relationship, content
  type) that branch's Task 5 adds to `DocxPatcher` for `settings.xml`. A `word-lists` branch starts from
  `page-variants` once that branch's tasks are done, and merges after it. *(my call)*

## 4. Word export

### 4.1 Numbering definitions

`word/numbering.xml` holds one abstract numbering per marker style that the document uses (`ListMarker` cases
except `None`). Each has nine levels (`w:ilvl` 0–8):
- **Format:** `decimal`, `lowerLetter`, `upperLetter`, `lowerRoman`, `upperRoman` for the numbered styles, each
  with level text `%N.` (N = level + 1); `bullet` for Disc (`•`), Circle (`◦`), Square (`▪`), with that glyph as
  the level text.
- **Indent:** level L has left indent `360 × (L + 1)` twips and a hanging indent of 360 twips, so text lines up
  where it does today (`ListMapper::INDENT`). Label followed by a tab, left-justified (`w:lvlJc w:val="left"`),
  as Word's own lists do. *(my call: native look over the PDF's right-aligned numbers)*
- Label font: the level has no run properties of its own, so the label takes the paragraph's default (the theme
  font, size and colour). *(my call)*

### 4.2 One numbering instance per list

Every papercut `ListNode` gets its **own** `w:num`, pointing at its marker's abstract numbering, with
`w:lvlOverride` / `w:startOverride` at its level set to the list's start number. So every list restarts, and two
lists in a row never continue each other.

- **Level:** a list's `w:ilvl` is its nesting depth among all enclosing lists (0 at the top), whatever their kind.
  So a bulleted list inside a numbered item is one level in. Its marker is still papercut's for its own kind and
  depth (`ListMarker::forDepth()`), or the list's explicit marker.
- **Marker `None`:** no numbering. Items are indented paragraphs, as today without the marker text.

### 4.3 Items

- An item's **first paragraph** carries `w:numPr` (its list's `numId`, its level) and no typed marker or tab.
- **Further blocks** in the item (more paragraphs, tables, images) are indented to the item's text and carry no
  numbering. Paragraphs and images are indented today. Tables aren't: they get `w:tblInd` (php-docx
  `TableStyle::indentTwips`) equal to the item's text indent, and their width shrinks by the same amount so they
  don't run past the right margin. *(my call)* The next item's paragraph carries the same `numId`, so Word keeps counting.
- An item whose first block isn't a paragraph (a table, or a nested list) gets an empty numbered paragraph before
  it. Today the outer label is put in front of a nested list's first paragraph, but a paragraph can't carry two
  `numPr`s.
- **Indent on numbered paragraphs:** a numbered paragraph has no direct `w:ind`. The level supplies it, so Tab,
  Shift+Tab and the indent buttons move the text with the level. The exception is a list inside an indented
  container (a blockquote, a callout): there the paragraph keeps a direct indent of level indent + container
  offset. *(my call: native behaviour wherever papercut adds no indent of its own)*
- **Lists inside a table cell** count their level from the cell, not from lists outside the table. `ListMapper`'s
  stack of open lists is reset for a table cell's content and restored after it, so a list in a cell inside a list
  item is level 0 with a 360-twip indent, as today. *(my call)*
- **Headings as an item's first block:** whatever marker the patcher uses must not cost a heading its `Heading`
  style. php-docx lets `styleId` win over `headingLevel`, so a `styleId` marker has to carry the heading level and
  the patcher has to restore `Heading<N>`.
- Lists inside table cells, headers and footers work the same way.

### 4.4 Where Word and the PDF differ

These follow Word's rules, and each is reported as `approximated` with the item's path:
- **An item with its own number** (`ListItem::withValue()`, `<li value>`): that item starts a new `w:num` with
  that start, and later items continue from it. In Word that's a new list from that item on, so renumbering the
  first part doesn't carry into the second. Note: `list item number`.
- **Lists Word can't number:** a list where any item's number is below 1 (a zero or negative start or value), or
  a roman list with a number above 3999. Word can't number these as papercut does: papercut switches those items
  to decimal, and Word can't show negatives. Such a list keeps **typed markers**, as today, and is reported
  `approximated` (`list numbering`). *(my call: rare, and exact text beats a wrong number)*
- **Letters past z:** papercut counts `y, z, aa, ab`; Word's `lowerLetter` counts `y, z, aa, bb`. Follows Word,
  no note. *(my call)*

There's no note for the label font, indent rounding or left-justified numbers. Those are how Word lists look.
*(my call)*

### 4.5 Writing it

- `ListMapper` stops writing the typed marker and the hanging first-line indent. It marks each numbered paragraph
  so that `DocxPatcher` can find it. The marker carries the numbering instance and level, and the item keeps its
  text indent. The marker's form is the builder's choice: a reserved `styleId`, in the style of the existing
  `papercut-shd-` highlight marker. A marker left unpatched throws.
- `DocxPatcher`:
  - writes `<w:numPr><w:ilvl/><w:numId/></w:numPr>` into each marked paragraph's `w:pPr`, in the position the
    schema requires: after `w:keepNext`, `w:keepLines`, `w:pageBreakBefore` **and `w:widowControl`** (which
    papercut writes on most paragraphs), before `w:pBdr`. That's the order php-docx uses in
    `BodyXmlBuilder::renderParagraphProperties()`. It also removes the marker. This applies in `document.xml`,
    headers and footers.
  - creates `word/numbering.xml`, its relationship and its content-type override, with the part-creation code
    from the page-variants branch. If php-docx ever writes its own `numbering.xml`, throw, since papercut
    doesn't use php-docx lists.
  - `PatchSpec` carries the numbering definitions (abstract numberings used, and the `w:num` list with starts).
- Nothing changes for documents without lists: their output stays byte-identical.

## 5. Space after a list

- A **top-level list** (one not inside a list item) gets space after it equal to the theme's paragraph spacing
  (`Theme::paragraph()`'s bottom margin, 6pt by default), from a new `Theme::list(): Style`. It applies to every
  `ListNode`, from PHP, Blade and markup. A list with a bottom margin of its own keeps that instead. *(my call:
  all lists; the PHP and Blade APIs have no `p`-like preset for lists)*
- **Top up, don't stack:** the space after a list is the list spacing **minus** whatever bottom margin its last
  item already ends with, never below 0. A list whose last item ends in a `<p>` (6pt) gets nothing more, so the gap
  stays 6pt, not 12pt. In Word this is `spaceAfter = max(own, list spacing)` on the last paragraph. *(my call:
  browsers collapse these margins; papercut doesn't collapse margins in general, so the rule is local to lists)*
- **Top-level, not nested:** `StyleResolver` can't see ancestors, so the "top-level" signal comes from list depth:
  `LayoutContext::enterList()` in PDF, and the list stacks in `DocxVisitor`/`ListMapper` and `HtmlWriter`. A list
  in a table cell counts as top-level within the cell, so a cell ending in a list gets the space too, as a cell
  ending in a `<p>` already does.
- **Nested lists** get no extra space, as in browsers.
- **Word:** the list's bottom margin becomes space after its last paragraph, the way other block margins are
  mapped today.
- **HTML:** `ul`/`ol` at the top level get the same bottom margin; nested ones stay at 0.
- In PDF this changes where content after a list sits, which is the intent.

## 6. Testing

- **DOCX (XML assertions):**
  - two lists in a row get different `numId`s, each with `w:startOverride` = its start;
  - a bulleted list inside a numbered item is at `w:ilvl="1"` with the Disc glyph (it's the first bulleted level;
    `ListMarker::forDepth()` counts depth per kind), and a bulleted list inside a bulleted item uses Circle;
  - an item with a paragraph and a table: one numbered paragraph, an indented table, and the next item continues
    with the same `numId`;
  - `withValue()` starts a new `w:num` and is noted `approximated`;
  - marker `None` gives no `w:numPr`;
  - a list in a header works;
  - `numbering.xml`, its relationship and its content type exist only when the document has a list;
  - no typed marker text (`•`, `1.`) remains in the document;
  - the patcher throws on an unpatched marker, and on a php-docx `numbering.xml` it didn't expect;
  - `w:numPr` comes after `w:widowControl` in a paragraph that has both;
  - numbered paragraphs have no direct `w:ind`, except inside an indented container;
  - a heading as an item's first block keeps its `Heading<N>` style;
  - an item starting with a nested list gets an empty numbered paragraph first;
  - a table inside an item has `w:tblInd` equal to the item's text indent, and its width fits the margins;
  - a list inside a table cell inside a list item is at level 0;
  - a list with a zero or negative number keeps typed markers and is noted `approximated`.
- **Open check:** the output opens in LibreOffice headless (`soffice --headless --convert-to pdf`) if it's
  installed, and the converted PDF's text has the numbers. Skipped with a message when `soffice` is absent.
- **PDF:** a paragraph after a top-level list of plain items sits 6pt lower than today; after a list whose last
  item ends in a `<p>`, the gap stays 6pt (no stacking); a nested list adds nothing; a list with its own margin
  keeps it; a list ending a table cell adds the space inside the cell. A regression test pins right-aligned markers: on a 1–12 ordered list, every marker's
  right edge and every item's text start are at the same x.
- **HTML:** top-level list margin present, nested absent.

## 7. Goldens

Before v1.0, goldens and snapshots may change when a feature means to change output (maintainer, 2026-10-29). This
work is expected to change the goldens of every fixture with a list: the `markup-tables` and `markup-slide` visual
snapshots and HTML goldens, and the `fixtures-markup-tables` and `fixtures-markup-slide` DOCX goldens.
Re-baseline those and list them in the PR. A golden without lists that changes is a bug to investigate.

## 8. Documentation

- `site/src/guide/` page that covers lists: one paragraph on Word export (real Word lists; how items with their
  own numbers behave) and the new spacing.
- CHANGELOG `[Unreleased]`: **Changed**: lists in Word export are real Word lists; lists have space after them.

## 9. Upstream (php-docx)

Open a pull request from the fork adding paragraph-level numbering: `numId`/`ilvl` on `ParagraphStyle`, a public
way to register abstract numberings and instances (with `w:startOverride`), and a fresh instance per list where
lists shouldn't continue. When released, papercut switches to it and drops the `DocxPatcher` step. Push and PR
wait for the maintainer's go-ahead.

## 10. Left to the builder

- The paragraph marker's exact form and the `PatchSpec` shape.
- How `ListMapper` passes the numbering definitions to the exporter.
- Whether `Theme::list()` takes a named-style hook (for example `papercut.styles` key `list`).
