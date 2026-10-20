# dskripchenko/php-docx v1.15.0 - API map for a Papercut DOCX exporter

Probe scripts were run against a read-only clone of php-docx v1.15.0.
All paths below are relative to `src/`; namespace root `Dskripchenko\PhpDocx\`. "L" = line number.

Headline: the model layer is a thin typed AST (readonly value objects). The writer (`Writer/BodyXmlBuilder.php`, `Writer/Word2007Writer.php`) is small and ignores several model fields. Anything outside what is listed below is NOT SUPPORTED.

## 13. composer.json
- `php: ^8.2`; `ext-dom`, `ext-mbstring`, `ext-zip`. No runtime Composer dependencies. `suggest`: tijsverkoyen/css-to-inline-styles (HTML path only). dev: phpunit ^11.5, phpstan ^2.0.
- The writer itself only needs ext-zip (ZipArchive). Autoload PSR-4 `Dskripchenko\PhpDocx\` => `src/`.

## 1. Entry point and bytes
- There are two layers. Both are public in the README.
  1. `Build\DocumentBuilder` (fluent): `DocumentBuilder::new()`; `->build(): Document`; `->toBytes(): string` (Build/DocumentBuilder.php L208); `->toFile(string): int`. `toBytes()` internally does `(new Word2007Writer($styles))->write($this->build())`.
  2. Direct AST + `Writer\Word2007Writer::__construct(StyleRegistry $styles = new StyleRegistry)`; `->write(Document $document): string` (Word2007Writer.php L37). Returns the bytes. It writes to `tempnam(sys_get_temp_dir(),'docx-')` through ZipArchive, reads it back and unlinks it (L122-185). The temp file is internal, so there is no in-memory path.
- `Document` (final readonly): `__construct(Section $section, ?string $watermarkText = null, ?CoreProperties $coreProperties = null)`. Exactly ONE `Section`.
- GOTCHA: `DocumentBuilder::build()` never sets `coreProperties` (L187-202), and the builder has no `coreProperties()` method. To get core.xml you must construct `new Document($section, null, new CoreProperties(...))` yourself. The same applies to a custom `StyleRegistry`: `DocumentBuilder::styles()` exists, but the `Document` route needs `new Word2007Writer($registry)`.
- Recommendation for an exporter: build the AST directly (`Section`, `Paragraph`, `Run`, `Table`, ...) and call `Word2007Writer::write`. The builder cannot express core props, keepNext, line rules or paragraph style ids, and the AST can. The model fields are the only way to set many things, but see the "model fields the writer ignores" table.
- Element ctor summary: `Paragraph(list<InlineElement> $children, ParagraphStyle $style = new, ?int $headingLevel = null)`; `Run(string $text, RunStyle $style = new)`; `LineBreak` (no args); `PageBreak` (block, no args); `HorizontalRule`; `Table(list<TableRow> $rows, TableStyle $style, ?string $caption, ?list<int> $gridColumnsTwips)`; `TableRow(list<TableCell> $cells, bool $isHeader=false, ?int $heightTwips=null)`; `TableCell(list<BlockElement> $children, CellStyle $style)`; `Hyperlink(?string $href, list<InlineElement> $children, ?string $anchor=null)` + `Hyperlink::internal($anchor,$children)`; `Bookmark(string $name, list $children=[])`; `Field(string $instruction, RunStyle $style)`; `Image(string $binary, ImageFormat $format, int $widthEmu, int $heightEmu, ?string $altText=null, int $offsetYEmu=0)`.

## 2. Sections / page setup
- ONE section per document (Section.php docblock: "Multi-section documents ... out of scope for v1"). NOT SUPPORTED: multiple sections, per-section orientation, mid-document sectPr. It would have to be injected in `Word2007Writer::renderDocumentXml` (L355) by emitting `<w:p><w:pPr><w:sectPr>..</w:sectPr></w:pPr></w:p>` section-break paragraphs into `$bodyXml`. There is no model for it.
- `Section(array $body, array $header=[], array $footer=[], PageSetup $pageSetup=new, array $firstHeader=[], array $firstFooter=[], array $evenHeader=[], array $evenFooter=[])`. Each header/footer is `list<BlockElement>`.
- `Style\PageSetup(PaperSize $paperSize=A4, Orientation $orientation=Portrait, int $marginTopTwips=1133, $marginRightTwips=850, $marginBottomTwips=1133, $marginLeftTwips=850, $headerOffsetTwips=283, $footerOffsetTwips=283)`. Units: twips. `PageSetup::fromMm(...)` takes margins only (it cannot set the header/footer offset). It uses 56.6929 per mm.
- `PaperSize` enum: A3, A4, A5, Letter, Legal (twips table in PaperSize.php). Custom page size is NOT SUPPORTED (inject at L362-364 where `$w/$h` are chosen). `Orientation::Landscape` swaps w/h and writes `w:orient`.
- XML (Word2007Writer L367-383):
  `<w:sectPr>[<w:headerReference w:type="default|first|even" r:id/>...][<w:footerReference .../>...]<w:pgSz w:w w:h w:orient/><w:pgMar w:top w:right w:bottom w:left w:header w:footer w:gutter="0"/>[<w:titlePg/>]</w:sectPr>`
- First-page header/footer => `<w:titlePg/>` automatic. Even => `word/settings.xml` gets `<w:evenAndOddHeaders/>` (SettingsXmlBuilder). Empty blocks list means no part. Header/footer parts are `word/header{N}.xml` and `footer{N}.xml`, numbered in order default, first, even (L67-91). Header root declares w, r, wp, a, pic, v, o namespaces.
- Watermark: `Document::$watermarkText` becomes a VML shape in the default header. This is irrelevant to Papercut unless wanted.
- No columns (`w:cols`), no `w:pgNumType`, no `w:vAlign`, no page borders.

## 3. Paragraphs
`Style\ParagraphStyle(Alignment $alignment=Start, int $spaceBeforeTwips=0, $spaceAfterTwips=0, $indentLeftTwips=0, $indentRightTwips=0, $indentFirstLineTwips=0, ?int $lineSpacingTwips=null, ?string $lineSpacingRule=null, bool $pageBreakAfter=false, bool $keepWithNext=false, ?BorderSet $borders=null, ?string $shadingColor=null)`. All twips.
Writer: `BodyXmlBuilder::renderParagraphProperties` (Writer/BodyXmlBuilder.php L227-284). Emit order is pStyle, pBdr, shd, spacing, ind, jc (schema-correct).

| Feature | Status |
|---|---|
| Alignment | `Alignment` enum values: Start='start' (omits `w:jc`), Center='center', End='end', Justify='both', Distribute='distribute'. Emitted as `<w:jc w:val="both"/>` (L275-277). NOTE: Alignment::End writes `w:jc="end"` (not `right`) and Start omits jc. `end` is valid in ISO transitional and Word 2010+, but older consumers may not honour it. |
| spacing before/after | `<w:spacing w:before w:after/>` only when nonzero (L243-256). You cannot write an explicit `0` to override a style. |
| line spacing | ONLY exact. Any `lineSpacingTwips` is written `w:line="N" w:lineRule="exact"` (L251-254) whatever `lineSpacingRule` says. `lineSpacingRule` is NEVER read by the writer (verified by grep; only the Reader/Html code use it). Multiple (`auto`, 240 = single) and `atLeast` => NOT SUPPORTED. Inject at L251-254. Fix: use `$style->lineSpacingRule ?? 'exact'` for `w:lineRule`. docDefaults has `w:line="276" w:lineRule="auto"` (1.15x). |
| indent | `<w:ind w:left w:right w:firstLine|w:hanging/>`. A negative `indentFirstLineTwips` becomes `w:hanging=abs` (L265-272). |
| keepNext | NOT EMITTED, confirmed. `keepWithNext` exists in the model but grep finds no `keepNext` in `Writer/` or `Build/`. Inject in `renderParagraphProperties` right after pStyle (schema: pStyle, keepNext, keepLines, pageBreakBefore, ..., numPr, ..., pBdr, shd, tabs, ..., spacing, ind, ..., jc). |
| keepLines, widowControl, pageBreakBefore | NOT SUPPORTED (no model field). |
| pageBreakAfter | model field exists, writer ignores it. Use a `PageBreak` block instead. |
| pStyle | ONLY via `headingLevel` 1..6 => `<w:pStyle w:val="Heading{n}"/>` (L231-233). Arbitrary style ids (Normal, Title, custom): NOT SUPPORTED on `Paragraph`; inject at L231. List items force `ListParagraph`. Table caption uses `Caption`. |
| borders | `BorderSet` => `<w:pBdr>` with sides top, left, bottom, right (+insideH/insideV, which are invalid inside pBdr; do not set them). `space="0"` hard-coded. |
| shading | `shadingColor` => `<w:shd w:val="clear" w:color="auto" w:fill="HEX"/>` (hex without #; not validated). |
| tabs / tab stops | NOT SUPPORTED. |
| PageBreak | `<w:p><w:r><w:br w:type="page"/></w:r></w:p>` (L417): a standalone paragraph. The paragraph mark lands on the new page (an empty line at its top). `pageBreakBefore` on the next paragraph would be cleaner but is NOT SUPPORTED. |
| Empty paragraph | `new Paragraph([])` => `<w:p/>`. |

## 4. Runs
`Style\RunStyle(?int $sizeHalfPoints, ?string $color, ?string $backgroundColor, ?string $fontFamily, bool $bold, $italic, $underline, $strikethrough, $superscript, $subscript, ?string $highlight, ?int $letterSpacingTwips, bool $allCaps, bool $smallCaps)`. Named args work. Also `RunStyle::fromPt(float $sizePt, ...)`. Units: size = half-points, spacing = twips, color = hex without `#`.
Writer: `renderRunProperties` (BodyXmlBuilder.php L341-392). Order is rFonts, b/bCs, i/iCs, u, strike, vertAlign, color, spacing, sz/szCs, highlight.
- Font: `<w:rFonts w:ascii="F" w:hAnsi="F" w:cs="F"/>`. NO `w:eastAsia` (NOT SUPPORTED; inject at L349). A single family for all scripts. No theme fonts.
- Size: `<w:sz w:val=hp/><w:szCs w:val=hp/>`. Bold `<w:b/><w:bCs/>`, italic `<w:i/><w:iCs/>`, underline `<w:u w:val="single"/>` only (no other underline styles), strike `<w:strike/>`, color `<w:color w:val="hex"/>` (no `w:val="auto"`).
- Char spacing: `<w:spacing w:val="twips"/>` (L376). Correct unit (twips) and negative allowed.
- `allCaps`/`smallCaps` exist in the model but the writer NEVER emits `<w:caps/>`/`<w:smallCaps/>` (grep: no matches). `backgroundColor` is deliberately ignored (comment L381-385). `highlight` is emitted.
- Line break: `LineBreak` => `<w:r><w:br/></w:r>` (L290).
- Page break inline: only the block `PageBreak` above.
- Tab: NOT SUPPORTED. `Run::$text` is escaped and put in a single `<w:t xml:space="preserve">` (L336), so "\t" and "\n" are written raw inside `w:t` (probe2 confirmed: a literal tab and newline in the text). Word renders those as a space or nothing. A tab run needs `<w:r><w:tab/></w:r>`; inject in `renderRun` (L331) by splitting on "\t"/"\n" and emitting `<w:tab/>`/`<w:br/>`.
- Text sanitising: `XmlEscape::text` = `htmlspecialchars(.., ENT_QUOTES|ENT_XML1, 'UTF-8')` (Writer/XmlEscape.php). SURPRISES (probe3): (a) invalid UTF-8 in the string makes `htmlspecialchars` return `''`, so the WHOLE run text is silently dropped (no `ENT_SUBSTITUTE`); (b) control chars such as \x01 pass through, producing malformed XML that Word rejects (simplexml parse = false). Sanitise input text upstream (strip C0 controls except \t\n\r and ensure valid UTF-8).

## 5. Hyperlinks
- External: `new Hyperlink('https://...', [Run...])` => `<w:hyperlink r:id="rIdN">` + a relationship `Type=.../hyperlink Target=URL TargetMode="External"` in `word/_rels/document.xml.rels` (RelationshipManager::registerHyperlink L135; BodyXmlBuilder L408-410). Inside header/footer parts the rel goes in that part's own rels file (`word/_rels/header1.xml.rels`, verified in probe3). URL is XML-escaped. No scheme validation.
- Internal: `Hyperlink::internal('anchor', $children)` => `<w:hyperlink w:anchor="anchor">` (no rel); needs a matching `Bookmark($name, $children)` => `<w:bookmarkStart w:id=N w:name/>..<w:bookmarkEnd w:id=N/>`. Bookmark id is a per-BodyXmlBuilder counter (reset for each header/footer part; bookmarks in headers could collide, which is not a real concern). Names are not validated or sanitised by the writer (40-char / letter-first rules are on you).
- No `Hyperlink` character style, no automatic blue/underline: style the child Runs yourself. Every `w:hyperlink` has no `w:history`.

## 6. Fields
- `Element\Field(string $instruction, RunStyle $style)`; factories `Field::page()` => instr `PAGE \* MERGEFORMAT`, `Field::pageTotal()` => `NUMPAGES \* MERGEFORMAT`, `date/time/author/title`. Builder: `->pageNumber()`, `->totalPages()`, `->mergeField($name)`.
- Written as a simple field (BodyXmlBuilder L321-329): `<w:fldSimple w:instr=" PAGE \* MERGEFORMAT "><w:r>[rPr]<w:t>0</w:t></w:r></w:fldSimple>`. The cached value is the literal `0` for both PAGE and NUMPAGES until Word/LibreOffice updates fields (probe: header1.xml). Complex fldChar fields are NOT SUPPORTED; fldSimple is sufficient in headers/footers, and viewers that do not recompute fields will show "0".
- Works in header and footer (same renderer; probe verified in `word/header1.xml`). The run style on the Field is applied to the placeholder run only.

## 7. Tables
Models: `Style\TableStyle(?int $widthTwips, ?int $widthPercent, bool $fixedLayout=false, ?BorderSet $borders=null, Alignment $alignment=Start, int $cellMarginTopTwips=0, $cellMarginRightTwips=80, $cellMarginBottomTwips=0, $cellMarginLeftTwips=80)`; `Style\CellStyle(?int $widthTwips, ?int $widthPercent, int $paddingTop/Right/Bottom/LeftTwips=0, VerticalAlign $verticalAlign=Top, ?string $backgroundColor, ?BorderSet $borders, int $gridSpan=1, int $rowSpan=1, bool $vMergeContinue=false)`. Writer: BodyXmlBuilder L431-644.
- tblPr (L468-511), order tblW, jc, tblBorders, tblLayout, tblCellMar:
  - `tblW`: `w:w=widthTwips w:type="dxa"`; else `widthPercent` as `type="pct"` where the value is fiftieths of a percent (100% = 5000); else `w:w="0" w:type="auto"` (always emitted).
  - `<w:tblLayout w:type="fixed"/>` only when `fixedLayout`.
  - `tblCellMar` emitted (top,left,bottom,right, dxa) whenever any of the four is nonzero. Defaults left/right = 80 so it is almost always emitted. If you set ALL four to 0 the element is omitted and Word falls back to its 108-twip default. To force near-zero use 1.
  - `tblInd` (table indent): NOT SUPPORTED (inject after jc in `renderTableProperties`, schema order ... tblW, jc, tblCellSpacing, tblInd, tblBorders ...). `tblStyle`, `tblLook` not emitted. `jc` on a table uses the paragraph alignment enum (`end`, `both` are not sensible there).
- tblGrid: `<w:tblGrid><w:gridCol w:w/>..` from `Table::$gridColumnsTwips` if set, else `computeGridColumns` (L520-546): taken from the FIRST ROW ONLY; each cell's `widthTwips` (or a pct approximation of 9000), default 2000; a cell with `gridSpan` N repeats the same width N times (so a spanning cell's tcW and the grid disagree: probe2 produced grid 2000/2000/2000 for a 2000-wide gridSpan=2 cell plus another 2000 cell). ALWAYS pass explicit `gridColumnsTwips` (or `TableBuilder::columns(...)`).
- trPr (L548-557): `<w:tblHeader/>` if `isHeader`; `<w:trHeight w:val=twips w:hRule="atLeast"/>` ALWAYS atLeast (exact is NOT SUPPORTED; inject L555). `cantSplit`: NOT SUPPORTED (confirmed absent; inject in trPr, schema allows any order inside trPr).
- tcPr (L587-644), order tcW, gridSpan, vMerge, tcBorders, shd, tcMar, vAlign: `tcW` dxa or pct (fiftieths); `gridSpan`; `rowSpan>1` => `<w:vMerge w:val="restart"/>`, `vMergeContinue` => `<w:vMerge/>` (YOU must add the continuation cells in following rows; the writer does not); `tcBorders`; `<w:shd w:val="clear" w:color="auto" w:fill="hex"/>`; `tcMar` (top,left,bottom,right dxa, only if any is nonzero); `<w:vAlign w:val="top|center|bottom"/>` (top omitted). No `noWrap`, `textDirection`, `hMerge`. An empty `<w:tcPr/>` is emitted when nothing is set (valid).
- Borders (`Style\Border(BorderStyle $style=Single, int $sizeEighthsOfPoint=4, string $color='000000')`, `BorderSet(top,right,bottom,left,insideH,insideV)` all `?Border`, `BorderSet::all($b, $withInside=true)`, `BorderSet::none()`). Output `<w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/>` (L672-679). Width unit: `w:sz` in eighths of a point (4 = 0.5pt, 8 = 1pt, 24 max in schema). `BorderStyle`: none, single, double, dashed, dotted, thick. `space` fixed at 0. Side order top, left, bottom, right, insideH, insideV (schema-correct). Style `none` writes `w:val="none" w:sz="0" w:space="0" w:color="auto"`. Borderless table: `BorderSet::none()` as `TableStyle::$borders` (probe2: full tblBorders with none). `borders = null` => NO tblBorders (Word's table default is then also none unless the "Table Grid" style is applied; no style is applied). Per-cell `BorderSet::all(.., true)` also writes insideH/insideV, which are invalid in tcBorders for some validators; pass `withInside:false`.
- Nested tables: supported (`TableCell::$children` may hold a `Table`); the writer appends `<w:p/>` after it so the cell ends with a paragraph (L576-582). Empty cells always get `<w:p/>` (probe2 confirmed). The document body gets a trailing `<w:p/>` if it ends in a table (L396 / trailingParagraph).
- Table `caption` emits an extra paragraph with the `Caption` style BEFORE the table (hard-coded italic, centred, 10pt grey, colour 6b7280 in the run). Leave it null.
- Paragraphs inside cells use the normal paragraph writer. There is no per-table paragraph spacing: docDefaults after=0.

## 8. Images
- `Element\Image(string $binary, ImageFormat $format, int $widthEmu, int $heightEmu, ?string $altText=null, int $offsetYEmu=0)`; `Image::fromPx($binary,$format,$widthPx,$heightPx,$altText)` (1 px = 9525 EMU). `ImageFormat`: Png, Jpeg, Gif, Bmp, Tiff (ext png, jpg, gif, bmp, tiff). The model has `offsetYEmu`, but the inline writer does not use it.
- Inline (in a Paragraph's children) or block (an `Image` in a body list => wrapped in `<w:p>`; BodyXmlBuilder L683-687). Always `wp:inline` (floating/anchored NOT SUPPORTED).
- Builder: `->imageFromBytes($bin, ImageFormat, int $widthPx, int $heightPx, ?alt)`, `->imageFromFile(string $path, ?int $widthPx=null, ?int $heightPx=null, ?alt)` (format from extension, else magic bytes PNG/JPEG/GIF/BMP; size via `@getimagesizefromstring` when not given, fallback 100x100), `->imageFromDataUrl(...)` (returns silently without adding anything on failure). Exceptions: `\InvalidArgumentException('Image file not readable: ...')`, `'Unsupported image format: ...'`.
- Corrupt bytes: NOT validated. `new Image('garbage', ImageFormat::Jpeg, ...)` or `imageFromBytes` writes `word/media/imageN.jpg` containing the raw bytes (probe2: 7-byte "image2.jpg") with a valid-looking drawing; Word shows a red X / "can't display". `imageFromFile('bad.png')` accepted because the extension decides the format; size falls back to 100x100 px. You must validate (e.g. `getimagesizefromstring`) before adding.
- XML (L700-727): `<w:r><w:drawing><wp:inline distT/B/L/R="0"><wp:extent cx cy/><wp:effectExtent .../><wp:docPr id=N name="Image" descr="alt"/> ... <a:blip r:embed="rIdN"/> ... </wp:inline></w:drawing></w:r>`. `docPr` ids are document-wide unique counters (`nextDrawingId`). The pretty-printed template has whitespace between elements (harmless). `alt` defaults to "image". No dedupe: the same bytes used twice become two media files. Media at `word/media/image{N}.{ext}`; Content_Types gets `<Default Extension="png|jpg|...">`. Images in headers/footers register their rel in the part's own rels file (correct).
- No crop, rotation, border, or wrap.

## 9. Styles part
- `Style\StyleRegistry`: `heading(int $level 1..6, RunStyle, ?ParagraphStyle)` (throws `InvalidArgumentException` otherwise), `paragraph(string $styleId, RunStyle, ParagraphStyle)`, `StyleRegistry::defaults()`, `all()`, `isEmpty()`. Character styles (`type: 'character'`) are in the array type but no public method creates one.
- If the registry passed to `Word2007Writer` is EMPTY, `defaults()` is used (Heading1..6 sized 22/18/14/12/11/10pt bold, ListParagraph with left indent 720, Caption). A non-empty custom registry REPLACES the defaults entirely: ListParagraph (used by every list item) and Caption would then be missing unless you register them. `DocumentBuilder::styles()` has the same semantics.
- `Writer\StylesXmlBuilder` output (probe: word/styles.xml): `<w:docDefaults>` HARD-CODED: `rFonts ascii=Calibri hAnsi=Calibri`, `sz/szCs=22`, `spacing after=0 line=276 lineRule=auto`. No `eastAsia`/`cs`/`lang`, no way to change docDefaults (inject in `renderDocDefaults`, StylesXmlBuilder L36-49).
- Per style: `<w:style w:type="paragraph" w:styleId="ID"><w:name w:val="Human Name"/>[<w:basedOn w:val="Normal"/><w:next w:val="Normal"/>][<w:qFormat/>][pPr][rPr]</w:style>`. Order is schema-correct (name, basedOn, next, qFormat, pPr, rPr). basedOn/next are emitted only for ids starting "Heading" and ListParagraph.
  - `w:styleId` = your id verbatim (NOT escaped, NOT validated: a quote or space would break the XML). `w:name` = `humanize($id)`: inserts a space before each capital (except first) and before digits after letters: Heading1 => "Heading 1", ListParagraph => "List Paragraph", `My_Style-1` => "My_ Style-1" (probe2).
  - NO `Normal` style exists by default, yet headings are `basedOn Normal`. Dangling reference (Word tolerates it; it uses its built-in Normal). Registering `paragraph('Normal', ...)` emits a style with `styleId="Normal"` but WITHOUT `w:default="1"`.
  - Style pPr supports only spacing before/after, ind left/right, jc (StylesXmlBuilder L121-153). It ignores line spacing, firstLine, keepNext, borders, shading, pageBreakAfter. Style rPr supports rFonts (ascii/hAnsi/cs), b, i, u, strike, color, sz (no spacing/caps/vertAlign/highlight).
  - NO `outlineLvl` anywhere (inject in `renderParagraphProperties` of StylesXmlBuilder). Heading detection relies on the built-in names "Heading N" (Word and LibreOffice treat these as outline levels; but do not count on it for custom styles). No `w:link`, `w:uiPriority`, or latentStyles. Table styles and numbering styles are NOT SUPPORTED (no "TableGrid").
  - Heading colours default to 0f172a/1f2937/374151 (dark navy/grey), which differ from black; override the registry.

## 10. Core properties and determinism
- `Style\CoreProperties(?string $title, $subject, $creator, $keywords, $description, $lastModifiedBy, ?\DateTimeInterface $created, ?\DateTimeInterface $modified, ?string $category)`. Only non-null/non-empty fields are written, in this order: dc:title, dc:subject, dc:creator, cp:keywords, dc:description, cp:lastModifiedBy, dcterms:created, dcterms:modified, cp:category. Dates: `<dcterms:created xsi:type="dcterms:W3CDTF">2020-01-02T03:04:05Z</dcterms:created>` (UTC, seconds). Dates are caller-supplied and NEVER defaulted to "now" (CorePropertiesXmlBuilder L55-64), so they can be fixed. If all fields are empty or `coreProperties` is null, NO core.xml is written (and no docProps/app.xml ever).
- Nothing in the writer uses rand/uniqid/time/date (grep of Writer/Build/Style/Element/Document/Section: only the CoreProperties date formatter). No rsid, no GUIDs. IDs are monotonic counters: relationship ids `rId1..` allocated in call order (body first, then headers, footers, numbering, settings, styles), `docPr` ids, bookmark ids, `numId`s. Content is therefore deterministic for identical input.
- ZIP (Word2007Writer L122-186): `ZipArchive` on a temp file, `addFromString` for each entry, default deflate compression, entry order: `[Content_Types].xml`, `_rels/.rels`, `docProps/core.xml`?, `word/document.xml`, `word/_rels/document.xml.rels`, `word/header*.xml`, `word/footer*.xml`, `word/numbering.xml`?, `word/settings.xml`?, `word/styles.xml`, per-part rels, `word/media/*` (fixed, deterministic).
- NON-DETERMINISM: ZipArchive stamps each entry's DOS mtime with the CURRENT time (local timezone) since the writer never calls `setMtimeName/setMtimeIndex`. Probe results: two `write()` calls within the same second are byte-identical; a call 2 s later differs (`a===c` false); across separate processes, runs in the same second gave sha1 0b634d... twice and a run 2 s later gave 1580e5.... Therefore output is byte-deterministic only within a second, and the DOS timestamp is 2-second granular and TZ-dependent. (libzip 1.11.4, PHP 8.4, ZipArchive::setMtimeName exists.) Also the temp-file approach uses `sys_get_temp_dir()`; no dependence of bytes on the filename.

## 11. Language / settings
- NO `w:lang` anywhere (docDefaults, run props, styles; grep: no "w:lang"). Document language is NOT SUPPORTED. Inject `<w:lang w:val="en-US" w:eastAsia="..." w:bidi="..."/>` into `rPrDefault/rPr` in `StylesXmlBuilder::renderDocDefaults` (L40-44, after `szCs`).
- `word/settings.xml` is written ONLY when even/odd headers are used, and contains only `<w:evenAndOddHeaders/>`. No `defaultTabStop`, `compat`, `themeFontLang`, `updateFields`. To make fields refresh on open you would need `<w:updateFields w:val="true"/>` in a settings.xml you add (NOT SUPPORTED; it also triggers a Word prompt).

## 12. Extension seams
- NONE for XML hooks: `Word2007Writer` is `final`, `BodyXmlBuilder`, `StylesXmlBuilder` are `final`, all render methods are private, and `write()` builds the ZIP in one pass. No events/filters/interfaces/ part-injection API. (`BodyXmlBuilder::render(array $blocks): string` is public and returns a body fragment; `RelationshipManager` is public. So you could assemble a package yourself, but that means re-implementing Word2007Writer.)
- Practical options:
  1. Post-process the ZIP bytes: write bytes to a temp file/`ZipArchive::open`, `getFromName('word/document.xml')`, transform, `addFromString`, `close`. Header/footer parts are `word/header{N}.xml`, `word/footer{N}.xml` (mapping type => rId via sectPr + document.xml.rels). Also styles.xml, settings.xml.
  2. Fork or copy `Word2007Writer`/`BodyXmlBuilder` (both are short, ~400 and ~730 lines).
  3. Compose it all yourself with `BodyXmlBuilder` plus your own ZIP.
- Determinism when re-zipping: re-open the bytes with ZipArchive and rewrite entries => new DOS mtimes (now). Fix by calling `ZipArchive::setMtimeName($name, $fixedTs)` (or `setMtimeIndex`) for every entry after `addFromString`, then `close()`. Do this even if you did not alter anything, since the original archive carries the current time. Also keep entry order stable (rewriting an existing name keeps its slot with `addFromString`; deleting/re-adding moves it). Recompress with the same flags. Check that your fixed timestamp is >= 1980 and set a TZ (libzip stores local DOS time; use `date_default_timezone_set('UTC')` around the write for cross-machine stability).

## 14. Surprises and gaps (summary)
Model fields the writer ignores: `ParagraphStyle::keepWithNext`, `pageBreakAfter`, `lineSpacingRule`; `RunStyle::allCaps`, `smallCaps`, `backgroundColor`; `Image::offsetYEmu`; `Document` coreProperties is not reachable from `DocumentBuilder`.
- keepNext: not emitted (confirmed). cantSplit: absent (confirmed). keepLines/widowControl/pageBreakBefore/tabs/eastAsia/lang/tblInd/atLeast+auto line rules/trHeight exact/arbitrary pStyle/multiple sections: NOT SUPPORTED.
- Lists (checked paths only): in `renderListItem` and `renderTreeItems` the pPr is `pStyle, numPr[, pBdr..jc]`, which I found schema-ordered. I did not find the misplaced numPr you mention; it may concern another path (HTML Converter or an `ListItem::$style`). `numbering.xml` is generated by `NumberingXmlBuilder` (not analysed in depth; changelog says its element order was fixed in 1.15.0).
- Text: invalid UTF-8 silently empties the run; control chars give malformed XML; tabs/newlines are not converted.
- Unvalidated values everywhere: colours (`hex` strings written verbatim into attributes without escaping, e.g. `w:fill="`.$hex`), styleId, border sizes; no exceptions are thrown by the writer for out-of-range values. Only `\InvalidArgumentException` for bad heading level (1..6) and image file problems, `DocxException` for temp-file/ZipArchive open failures.
- `Alignment::End` => `w:jc="end"`, `Start` => nothing. Justify = `both`.
- PageBreak produces an extra empty paragraph; HorizontalRule is a bottom-border paragraph (single, sz 6, 999999).
- Table default cell margin 80 left/right and all-zero margins are omitted (Word then uses 108).
- Auto grid computation is first-row-only and wrong for spans: always set explicit grid columns.
- Heading style names/ids: Heading1..6 only; fonts default to Calibri 11 pt, 1.15 line spacing from docDefaults.
- `Hyperlink` has no character style; `Field` cached text is "0".
- Hyperlink targets and header parts: header rIds restart at rId1 inside each part's rels (correct per OPC), while document-level rIds are shared across hyperlinks, header/footer refs, settings, styles, numbering (all deterministic).
- The package ships `Html\Converter` (HTML to AST) and `Reader` (DOCX to AST); neither is needed for a Papercut exporter. A Papercut AST can map straight onto Paragraph/Run/Table/Image.

## Probe: 1 section, header with PAGE/NUMPAGES, 2-col fixed table (2000/3000 + header row), hyperlink paragraph
Probe script: see the note at the top. Built with `DocumentBuilder` + `Word2007Writer::write`.
- `write()` twice in the same process: byte strings IDENTICAL (2717 bytes). A third `write()` after `sleep(2)`: NOT identical (ZIP entry mtimes changed).
- Entries (all mtime = time of write): `[Content_Types].xml`, `_rels/.rels`, `word/document.xml`, `word/_rels/document.xml.rels`, `word/header1.xml`, `word/styles.xml`. (No settings.xml, no core.xml, no header1.xml.rels since the header has no links/images.)

word/document.xml (namespaces stripped, trimmed):
```xml
<w:body>
  <w:tbl>
    <w:tblPr>
      <w:tblW w:w="5000" w:type="dxa"/>
      <w:tblLayout w:type="fixed"/>
      <w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar>
    </w:tblPr>
    <w:tblGrid><w:gridCol w:w="2000"/><w:gridCol w:w="3000"/></w:tblGrid>
    <w:tr>
      <w:trPr><w:tblHeader/></w:trPr>
      <w:tc><w:tcPr/><w:p><w:r><w:t xml:space="preserve">A</w:t></w:r></w:p></w:tc>
      <w:tc><w:tcPr/><w:p><w:r><w:t xml:space="preserve">B</w:t></w:r></w:p></w:tc>
    </w:tr>
    <w:tr> ... 1 | 2 ... </w:tr>
  </w:tbl>
  <w:p>
    <w:r><w:t xml:space="preserve">See </w:t></w:r>
    <w:hyperlink r:id="rId1"><w:r><w:t xml:space="preserve">example</w:t></w:r></w:hyperlink>
  </w:p>
  <w:sectPr>
    <w:headerReference w:type="default" r:id="rId2"/>
    <w:pgSz w:w="11906" w:h="16839" w:orient="portrait"/>
    <w:pgMar w:top="1133" w:right="850" w:bottom="1133" w:left="850" w:header="283" w:footer="283" w:gutter="0"/>
  </w:sectPr>
</w:body>
```
Note: cells have `tcW` only when you set a width on the CellStyle (here widths came from `columns()`, so no tcW at all: with fixed layout Word uses the grid, LibreOffice too, but you should still set `CellStyle::$widthTwips` per cell for robustness).

word/header1.xml:
```xml
<w:hdr ...>
  <w:p>
    <w:r><w:t xml:space="preserve">Page </w:t></w:r>
    <w:fldSimple w:instr=" PAGE \* MERGEFORMAT "><w:r><w:t>0</w:t></w:r></w:fldSimple>
    <w:r><w:t xml:space="preserve"> of </w:t></w:r>
    <w:fldSimple w:instr=" NUMPAGES \* MERGEFORMAT "><w:r><w:t>0</w:t></w:r></w:fldSimple>
  </w:p>
</w:hdr>
```
word/_rels/document.xml.rels: rId1 hyperlink (External, `https://example.com/?a=1&amp;b=2`), rId2 header1.xml, rId3 styles.xml. The rId order follows allocation order, not sectPr order.

Second probe (probe2.php): all-features section (landscape Letter, first header + even footer): sectPr emitted headerReference default/first, footerReference default/even, pgSz landscape (15840x12240), pgMar, `<w:titlePg/>`; settings.xml `<w:evenAndOddHeaders/>`; Paragraph with every ParagraphStyle field showed `keepWithNext`/`pageBreakAfter`/`lineSpacingRule='auto'` dropped and `w:lineRule="exact"` forced; the rPr with allCaps/smallCaps showed neither.
