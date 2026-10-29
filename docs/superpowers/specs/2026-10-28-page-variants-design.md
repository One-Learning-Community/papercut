# Papercut: First-Page and Even-Page Headers, Mirrored Margins — Design

**Status:** agreed with the maintainer, 2026-10-28. Items marked *(my call)* were decided by the designer, not the
maintainer, and are open to review.

## 1. Intent

A page run (`Page` in PHP, `<x-doc::page>` in Blade) has one header and one footer, repeated on every page. Real
documents often need more:

- **Letterhead on page 1.** A tall branded header on the first page, and a light running header (the title) on the
  pages after it. Without a first-page header, the letterhead has to sit in the body, and the running header shows
  on page 1 as well.
- **A cover or title page** with no header or footer at all.
- **Printed booklets.** Even (left-hand) and odd (right-hand) pages carry mirrored headers, such as a page number on
  the outside edge, and mirrored margins with a wider inside margin for the binding.

Word has all three as page-setup features ("Different First Page", "Different Odd & Even Pages", "Mirror margins"),
and apps moving off PhpWord rely on the first one. Papercut is a published package, so this is designed as a general
feature rather than for one document.

This design adds, in PDF, Word and HTML:
1. **First-page header and footer** on each page run.
2. **Even-page header and footer** on each page run.
3. **Mirrored margins** on each page run.

## 2. Goals, non-goals, anti-goals

**Goals**
- A page run can give the first page of that run, and its even pages, their own header and footer. Each can also be
  set empty, so the page has none.
- The body area on each page follows that page's header and footer, so a tall first-page header only shortens the
  first page's body.
- A page run can mirror its margins: inside and outside swap sides on even pages.
- In Word, the result uses Word's own settings, so someone editing the file sees "Different First Page",
  "Different Odd & Even Pages" and "Mirror margins" ticked, and the behaviour holds as they edit.
- Every page run keeps its own settings. A later run (a landscape appendix, say) can have its own first-page header,
  or none.

**Non-goals**
- Restarting page numbers, or numbering that starts at anything other than 1.
- A gutter (`w:gutter`) separate from the inside margin.
- Different first-page or even-page **backgrounds**, **overlays** or **PDF templates**. They stay the same on every
  page of a run.
- Last-page headers, or headers chosen by content (such as a chapter title).

**Anti-goals** (failures even if it works)
- Any change to PDF, HTML or DOCX golden output, or layout snapshots, for documents that use none of the new
  features.
- A run's first-page header lost in Word because the run was merged into the previous run's section.
- A page with no footer in Word because only the first-page header was set (Word blanks a first-page footer that
  isn't written).
- Examples, tests, fixtures, guide pages or the CHANGELOG framed around one consuming app's documents. Use neutral
  examples: letterhead, a cover page, a booklet.

## 3. Constraints

- PHP `^8.4`, strict types, PHPStan level 8 in all packages, no new runtime dependencies.
- php-docx `^1.18` already supports first and even headers and footers (`Section::$firstHeader`, `$firstFooter`,
  `$evenHeader`, `$evenFooter`). It writes `<w:titlePg/>` when a first header or footer is non-empty, and
  `<w:evenAndOddHeaders/>` in `word/settings.xml` when an even one is. An empty list there means "inherit from the
  previous section", not "blank".
- php-docx has **no mirror-margins setting**, and writes `word/settings.xml` only when there are even parts.
  Papercut adds `<w:mirrorMargins/>` in `DocxPatcher`, creating the settings part if needed (§5.2). The patcher
  already fixes php-docx output and throws when the markup it edits changes. A pull request
  adding the setting upstream goes through the maintainer's fork (remote `personal`, serpentblade/php-docx).
  Once a php-docx release has it, the patch is removed. Papercut doesn't wait for that release.
- In Word, `evenAndOddHeaders` and `mirrorMargins` apply to the **whole document**, not per section.
  `titlePg` is per section.
- Papercut has no page-number restarts, so a page's number is its position in the whole document, counting from 1.
  "Even page" means an even number.

## 4. API

### 4.1 PHP (`Papercut\Page`)

```php
Page::a4()
    ->header(Text::make('Annual report'))           // every page, unless a variant below applies
    ->footer(PageNumber::make('{page} / {total}'))
    ->firstHeader($letterhead)                      // first page of this run
    ->firstFooter()                                 // no arguments: no footer on the first page
    ->evenHeader($runningLeft)                      // even pages
    ->evenFooter($numberLeft)
    ->mirrorMargins();                              // left margin is the inside, right is the outside
```

- `firstHeader(Node|Component|string ...$nodes)`, `firstFooter(...)`, `evenHeader(...)`, `evenFooter(...)`: each
  appends like `header()` and `footer()` do. Each has three states:
  - **not set** (never called): the page uses the normal header or footer.
  - **set with content**: the page uses that content.
  - **set empty** (called with no arguments, and never with content): the page has no header or footer.
  Calling one with no arguments after it has content keeps the content. *(my call: matches append semantics; there is
  no "remove" method, as with `header()`)*
- Accessors: `firstHeaderNodes(): ?array`, `firstFooterNodes(): ?array`, `evenHeaderNodes(): ?array`,
  `evenFooterNodes(): ?array`; `null` means not set, `[]` means set empty.
- `mirrorMargins(bool $on = true): self` and `mirrorsMargins(): bool`. With mirroring on, `margin()`'s left edge is
  the **inside** and the right edge is the **outside**. On odd pages they sit left and right, as written; on even
  pages they swap. This is Word's model, so the values map to Word unchanged.
- A helper that gives a page's actual margins by page number (for example `marginsOn(int $pageNumber): Edges`) is
  left to the builder, as is any internal helper that picks a page's header and footer.

### 4.2 Which header and footer a page gets

For each page, header and footer are chosen **independently**:

1. On the **first page of the run**, the first-page variant if it is set.
2. Otherwise, on an **even page** (by document page number), the even-page variant if it is set.
3. Otherwise, the normal header or footer.

So the first page of a run that starts on page 4 gets the first-page header if one is set (it wins over even, as in
Word). It gets the even-page footer if no first-page footer is set.

### 4.3 Blade (`<x-doc::page>`)

- New named slots: `first-header`, `first-footer`, `even-header`, `even-footer`, next to `header`, `footer`,
  `background` and `overlay`. A slot that is present but empty (`<x-slot:first-header></x-slot:first-header>` or
  whitespace only) means **set empty**. A slot that is absent means **not set**. The builder must check what Blade
  passes for an empty named slot and make both forms work.
- New boolean attribute `mirror` (`<x-doc::page mirror>`). *(my call: no config default; `papercut.margin` stays
  as it is)*
- Error messages for unknown slots list the new names (the existing `SLOTS` check).

## 5. Behaviour per format

### 5.1 PDF (layout)

- The `Paginator` lays out each header and footer variant a run uses once, as it does the normal ones today, and
  picks per page (§4.2).
- **Body area per page:** the body's top and height come from that page's chosen header and footer (with their gaps).
  The layout engine already takes constraints per call while it resumes from a token, so the body flows into areas
  of different heights. Content width doesn't change: mirroring only swaps the sides, and inside + outside =
  left + right.
- **Mirrored x offset:** on even pages of a mirrored run, header, body and footer start at the outside margin (the
  `right` value) instead of `left`.
- **Backgrounds, PDF templates and overlays** are placed from the page's corner today, ignore margins, and stay
  the same on every page. *(my call)*
- **No room for content:** the existing check (throw, or clip each to a third in clip mode) runs for each
  header/footer combination the run actually uses, and names the page variant in the message (for example
  `header and footer leave no room for content on "page[1]" (first page)`). *(my call on wording)*
- `{page}` and `{total}` work in every variant as they do in the normal header and footer.
- Bookmarks are still suppressed in every header and footer variant.

### 5.2 Word (DOCX)

- **One section per run with first-page parts.** `DocxExporter::sections()` merges consecutive runs that look alike.
  `alike()` must also compare the first and even variants and the mirror flag. A run with a first-page header or
  footer must **always start its own section**, even if it otherwise matches the previous run. Otherwise Word shows
  its first-page header only at the start of the merged section.
- **First page:** when either first-page variant is set, the section gets both `firstHeader` and `firstFooter`, so
  php-docx writes `titlePg`:
  - a set variant: its content. Set empty: a hairline paragraph (`Flow::hairline()`), because an empty list in
    php-docx inherits from the previous section.
  - the other, unset variant: a copy of the section's normal header or footer (a hairline if it has none). Word
    blanks a first-page part that isn't written, which would remove the normal footer from page 1.
    This resolves the §4.2/§5.2 overlap for Word: a later run's first page on an even page shows the normal part, not
    the even one, and the exporter reports it as approximated.
- **Even pages:** `evenAndOddHeaders` is document-wide. If any run sets an even variant, **every** section gets both
  even parts: the run's own even variant where set (hairline if set empty), else a copy of its normal header or
  footer (hairline if none). A document with no even variants writes none, so its output is unchanged.
- **Mirrored margins:** if any run mirrors, `DocxPatcher` adds `<w:mirrorMargins/>` to `word/settings.xml`
  (`PatchSpec` grows a flag). php-docx writes `settings.xml` only when a section has even parts
  (`Word2007Writer`, `$hasEvenPage`). If the part is missing, the patcher creates it: the `settings.xml` file, its
  relationship in `word/_rels/document.xml.rels`, and its override in `[Content_Types].xml`, matching what php-docx
  writes when it does create the part. When the part exists, `<w:mirrorMargins/>` goes in before
  `<w:evenAndOddHeaders/>`, the order the settings schema requires. Both paths throw if the markup they edit changes.
  *(my call; requiring even parts for mirroring would be a strange rule for users)* The page margins are written as today; with the flag on, Word reads left as inside and
  right as outside. When the document mixes mirrored runs with non-mirrored runs whose left and right margins differ,
  Word would mirror those too. Each such run is reported as approximated (`mirrored margins` on that page path).
  *(my call: turn the flag on if any run mirrors and report the others. This replaces the "first run decides"
  rule mentioned in conversation.)*
- **Header gap:** the existing spacing trick (header gap as space after the last header paragraph; footer gap as
  space before the first footer paragraph) applies to every variant.
- **Background and overlay pictures** (`floats`, collected by `DocxVisitor` from the background and overlay only)
  are anchored today in a paragraph at the top of the normal header part. In Word, the first and even parts replace
  the normal part on their pages, so the floats paragraph goes at the top of **every** header part the section
  writes: normal, first and even, copies included. A set-empty first or even header then holds the floats paragraph
  instead of a bare hairline. That way a cover page with a background and no header keeps its background in Word.
  *(my call; follows the non-goal that backgrounds and overlays stay the same on every page)*
- Page numbers (`PAGE` / `NUMPAGES` fields) work in every variant unchanged.
- Word paginates by itself, so which content lands on page 1 can differ from the PDF. That is true today and isn't
  changed here.

### 5.3 HTML

HTML has no pages. A run is one `<section class="pc-page">` with the header at the top and the footer at the bottom.

- **Top of the run:** the first-page header if set (nothing, if set empty), else the normal header.
- **Bottom of the run:** the normal footer. The footer closes the run, so it stands for the last page, not the
  first. *(my call)*
- **Even variants** are not shown. When a run has any, it is reported approximated (`even-page header and footer`),
  next to the existing `header and footer once per page` note. *(my call; the only new HTML note)*
- **Mirrored margins:** on screen, the run's padding uses left and right as written. For print, `Stylesheet`
  writes one `@page` rule for the whole document, from the **first** run's size and margins only
  (`Stylesheet::css`, `$doc->pages()[0]`). Mirroring follows the same rule: if the first run mirrors, the full-page
  stylesheet adds `@page :left` and `@page :right` rules with the margins swapped, so a printed booklet mirrors.
  Later runs' mirroring isn't printed, just as their sizes and margins aren't today. That's already covered by the
  existing `page box` approximated note, so there's no new note. The fragment output writes no `@page` rule and
  mirrors nothing. *(my call; per-run named pages would rework the print stylesheet and is out of scope)*
- `TreeWalker` walks the variants as regions so that the HTML and DOCX visitors see them. New `Region` cases (or an
  equivalent) are left to the builder. Error paths follow the slot names, for example `page > first-header > text[0]`,
  matching the PDF layout paths.

### 5.4 Fidelity notes, summarised

| Situation | PDF | Word | HTML |
|---|---|---|---|
| First-page header/footer | exact | exact | first header shown; first footer not shown (no note: footer stands for the last page) |
| Even-page header/footer | exact | exact | approximated |
| Mirrored margins | exact | exact, unless mixed with a non-mirrored run with unequal margins (approximated) | print only, first run only; covered by the existing `page box` note |

## 6. Documentation

- `site/src/guide/pages.md`: a new section after "Headers, footers and backgrounds", "First-page and even-page
  headers", with Blade and PHP tabs. Neutral examples: letterhead on page 1 with a running title after it; a cover
  page with no header; a booklet with mirrored margins and page numbers on the outside edge. State the precedence
  (§4.2), the empty-slot rule and the HTML behaviour (§5.3).
- `site/src/reference/blade-tags.md`: the new slots and the `mirror` attribute on `<x-doc::page>`.
- The PHP reference/API docblocks on `Page` for each new method.
- CHANGELOG `[Unreleased]` → Added, one entry per feature.
- An example under `examples/` (core) only if the builder finds that the existing examples show page features;
  if one is added, its golden is new and doesn't touch existing goldens.

## 7. Testing

- **Core layout:** body height and y per page for each variant combination; first-page wins over even; a run
  starting on an even page; set-empty variants give zero header space; mirrored x offsets on odd and even pages;
  a body that flows across pages of different heights without losing or repeating content (including a table
  that splits and repeats its header across a first page and a later page); the no-room check for a tall
  first-page header in throw and clip mode.
- **Several runs:** the second run's first page gets its own first-page header; the first run's variants don't leak.
- **PDF:** text-extraction checks that page 1 shows the first header and page 2 the normal one, and even pages the
  even one. New golden/snapshot entries are allowed; existing ones must not change.
- **DOCX:** section XML has `titlePg` and the first/even header/footer references; `settings.xml` has
  `evenAndOddHeaders` / `mirrorMargins` exactly when used; a first header alone still gives page 1 the normal
  footer (the first footer part holds the copy); set-empty writes a hairline; a background picture appears in the
  normal, first and even header parts (and in a set-empty first header); mirroring without even parts creates
  `settings.xml` with its relationship and content type, and the file opens cleanly; two alike runs where the second has a
  first header stay separate sections; a document using none of the features is byte-identical to before;
  the mixed-mirror approximated note.
- **DocxPatcher:** the settings patch, both when it creates the part and when it inserts into an existing one,
  throws if the php-docx markup it edits changes.
- **HTML:** top shows the first header; even variants give the note; mirrored print rules appear only in the full
  page.
- **Blade:** each new slot, the empty-slot form, `mirror`, and the unknown-slot error listing the new names.
- **Unchanged output:** all existing goldens and snapshots pass untouched. Any change needs the maintainer's approval.

## 8. Upstream (php-docx)

Separately from the papercut work, open a pull request on `dskripchenko/php-docx` from the fork (push to remote
`personal`) adding a mirror-margins setting that writes `<w:mirrorMargins/>` to `word/settings.xml`, with reader
support to match the library's existing round-trip tests. When a release includes it, papercut raises its php-docx
constraint, sets the option and drops the `DocxPatcher` step. Neither the papercut PR nor its release waits on this.

## 9. Left to the builder

- Internal shape: how `Paginator` caches variant fragments, the name and shape of the per-page selection helper,
  `Region` cases versus a variant field.
- Whether `PatchSpec` gets a `mirrorMargins` bool or a more general settings list.
- Exact fidelity note wording, within the meaning above.
- Task order. A suggested order: core API and layout → Blade → DOCX → HTML → docs → the upstream PR.
