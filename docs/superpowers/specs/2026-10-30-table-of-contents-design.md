# Table of contents

Status: design, for 0.6.0. Date: 2026-10-30.

## Intent

A table of contents is the most likely missing feature for longer documents (reports, guides, handbooks), and the
guide lists it under "Current limitations". Papercut already knows everything a table of contents needs: headings
are known before layout, layout records where each heading lands (bookmarks), and page numbers are already filled in
after layout (`{page}` and `{total}`). So this is mostly wiring, not a new layout pass.

## Goals

- `<x-doc::toc />` in Blade and `Toc::make()` in PHP, placed anywhere in a page body.
- Lists the document's headings at chosen levels, each with the page number it lands on in the PDF.
- Entries link to their headings in the PDF.
- Dot leaders between an entry and its page number (on by default, can be turned off).
- Styled through named styles, so the theme controls its look.
- Word export writes a real Word table of contents field; HTML export writes a list of links.

## Non-goals

- A second layout pass. Layout runs once; page numbers are filled in when painting.
- Custom page numbering (roman numerals for front matter, restarting at 1). Entries show the same number as `{page}`:
  the physical page index, where a cover page is page 1.
- Lists of figures or tables, an index, or entries for things that aren't headings.
- Making `#fragment` links work in the PDF in general. The internal destinations built here make that easy later;
  it is not part of this work.

## Anti-goals

- A table of contents whose page numbers can be wrong in the PDF. Every number shown must be the page the heading
  was actually drawn on.
- Changing the output of documents that don't use a table of contents. Anchors, HTML ids and Word bookmarks are
  added only to headings that are entries of a table of contents in the same document, so existing goldens must not
  change. (Calls marked *my call* below were settled after a builder review, not by the maintainer.)

## How it works (decided)

### The entries

- The entries are the document's headings (`h1` to `h4`, including headings from `Markup::html()` and
  `<x-doc::markup>`) in document order, in page bodies only. Headings in headers, footers, backgrounds and overlays
  are never entries (the same rule bookmarks follow).
- An entry's text follows the bookmark rule: a heading's `bookmark('Short title')` sets its entry text too.
  `bookmark(false)` is stored as an empty title; an entry then falls back to the heading's own text (*my call*).
- An entry's level is the bookmark level when `bookmark($title, $level)` sets one, else the heading's level, so the
  table of contents and the PDF bookmarks panel nest the same way (*my call*). `levels(...)` filters on that level.
  In Word an updated field reads the `Heading1`–`Heading4` styles instead; that difference is accepted and
  documented.
- Levels: default 1 to 2. `levels(1, 3)` in PHP, `levels="1-3"` in Blade.
- A heading can leave the table of contents with `toc(false)` (a new style property, `toc="false"` in Blade). It
  keeps its bookmark. `bookmark(false)` turns off the bookmark only; it does not remove the heading from the table
  of contents.
- Entries are collected from the node tree before layout, so the table of contents can sit before or after the
  headings it lists.
- A table of contents with no entries draws only its title, if it has one, and reports a warning.

### Page numbers in the PDF

- During layout each entry's page number reserves the width of `999`, as `LeafLayouter` does for `{page}`.
- Each heading that is an entry gets an anchor when it is laid out: a new op that marks its position and draws
  nothing, emitted whether or not PDF bookmarks are turned on (`PdfOptions::bookmarks()` must not affect the table of
  contents; anchors need their own flag on the layout context, not `ctx->bookmarks`). Only headings that are entries
  get an anchor, so a custom `Backend` only meets the new op in documents with a table of contents; the CHANGELOG
  says custom backends must handle it. Like bookmarks, the anchor goes on the heading's first fragment only, and never on headings in repeated
  bands.
- The Painter first scans every placed page for anchors and records the page each one landed on, then paints. An
  entry's page number is a slot (like `PageNumberSlot`) resolved from that record and right-aligned in its reserved
  width.
- The PDF backend registers a named destination at each anchor (php-pdf `registerDestination`) and adds an internal
  link (`addInternalLink`) over each entry, covering its text, leader and number.

### Look

- Each entry is the heading text on the left and the page number on the right. A long title wraps, and the number
  sits on its last line. Deeper levels are indented.
- Dot leaders fill the space from the end of the title's last line to the number. `leaders(false)` /
  `leaders="false"` turns them off.
- An optional title above the list: `title('Contents')` / `title="Contents"`. No default title (so nothing needs
  translating). The title is not a heading: it gets no bookmark and isn't an entry.
- Named styles, restylable through the theme and `papercut.styles`: `toc` (the whole block), `toc.title`,
  `toc.entry` (every entry), and `toc.level-1` to `toc.level-4` (per level, for indentation, weight and spacing).
  Defaults: level 1 in bold with space above, deeper levels indented and regular weight.
- The table of contents is an ordinary block: it paginates like any other content (entries don't split across
  pages), and doesn't force page breaks. Authors put a `<x-doc::page-break />` after it if they want one.

### Word

- A real Word TOC field (`TOC \o "1-N" \h \z \u`, N being the deepest level) whose result is pre-filled with the
  entries and the page numbers from the PDF layout, using a right tab stop with a dot leader (or no leader). Readers
  see the PDF's numbers until they update the field; Word can renumber it with Update Field.
- Word export doesn't lay out pages today (it walks the tree and lets Word paginate). Decided by the maintainer
  (option A): when, and only when, the document has a table of contents, Word export runs the PDF layout (the
  paginator and the anchor scan, no PDF bytes written) to get the page numbers. That layout clips instead of
  throwing and reports nothing, so a document that exports to Word today never starts failing because of it.
  Documents without a table of contents pay nothing.
- Word's own pagination isn't 1:1 with the PDF, so a pre-filled number can be off by a page. The maintainer accepts
  this ("closer than not having anything"); the Word export docs say to use Update Field for exact numbers.
- Each heading that is an entry gets a Word bookmark, so the pre-filled entries link to it (`\h`).
- The heading paragraphs already use `Heading1` to `Heading4` styles, which is what the field reads when updated.
  `toc(false)` headings can't be excluded from an updated Word field by style; that difference is accepted and
  documented.
- php-docx only writes single-paragraph fields (`w:fldSimple`), and a TOC field spans paragraphs. Write it through
  `DocxPatcher` (marker paragraphs replaced with `fldChar` begin / `instrText` / separate / end). An upstream
  php-docx PR for multi-paragraph fields can come later.
- Don't set `w:updateFields` in settings: it makes Word ask a question every time the file is opened.
- Spike (2026-10-30, throwaway): a hand-patched TOC field (begin / `TOC \o "1-2" \h \z \u` / separate, one paragraph
  per entry with a right tab stop at the text width with `w:leader="dot"`, an indent per level, a `w:hyperlink
  w:anchor` to a `_Toc` bookmark wrapped around the heading's run, and the number as a `PAGEREF _Toc… \h` field with
  the cached number, then end) passes the OOXML validator and shows as written in LibreOffice (cached entries kept,
  leaders and indents right, links work). Use that form (the one Word writes itself). The maintainer accepted it.

### HTML

- `<nav>` with an ordered list of links to the headings, nested by level, with the title above. No page numbers or
  leaders.
- Headings that are entries get an `id` in the HTML export, unique within the document (slugs from the text, with a
  number appended on a clash).
- Leaving out the page numbers is reported as approximated, once per table of contents.

### Where it can't go

- A table of contents in a header, footer, background or overlay throws `InvalidStructure`. That exception lives
  only in `papercut/laravel` today: add `Papercut\Exception\InvalidStructure` to core and make the Laravel class
  extend it, so existing `catch` blocks keep working (*my call*).
- More than one table of contents in a document is allowed; each is independent.

## Docs and housekeeping

- A "Tables of contents" section in the guide (pages.md, near Bookmarks), the Blade tag reference, the style
  attribute reference (`toc`), and the named styles list.
- Remove "tables of contents" from the guide's "Current limitations" and from the root README's Status section.
- CHANGELOG entry under Unreleased.
- An example: add a table of contents to an existing multi-page example with several headings (the guide or the
  report), in both its Blade and PHP versions, and regenerate its PDF, Word and HTML outputs and the site images
  that show it.

## Left to the builder

- How entries are matched to anchors (node path, a per-render counter, or object identity). It must be correct when
  the same `Text` instance appears twice in a tree, and when the paginator lays a block out more than once before
  placing it.
- Exact leader drawing (dot spacing, whether dots line up across entries, the gap before the number).
- The exact default spacing and indentation values.
- Slug rules for HTML ids, as long as they're stable and unique.
- Internal class names (the anchor op, the entry slot, the layouter).
