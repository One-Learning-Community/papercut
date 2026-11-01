# Text watermark (parked)

Status: parked by the maintainer on 2026-10-31, before planning. Not in 0.6.0. Draft kept for when it comes back.

## Open question when resumed

Word: php-docx draws a fixed watermark (Word2007Writer::watermarkXml): silver #C0C0C0, opacity 0.5, Calibri,
stretched to a fixed 468x234pt box, centred on the margins. It never matches the PDF look below. Options put to the
maintainer: (A, recommended) patch the VML shape's colour, opacity, font, rotation and box size from the
`watermark` style via DocxPatcher (still a native Word watermark; upstream PR later), or (B) use php-docx's look
and report every Word watermark as approximated (strict mode then refuses). Undecided.

Also found by the builder check: php-docx writes the watermark into default headers only, and Papercut writes its
own first/even header parts whenever a template has a first-page variant or any template an even one, so those
pages lose it in Word (report and document). Shrink-to-fit should use the chord through the page centre at the
chosen angle, inset 10% and allowing for the text height, not the page diagonal (the diagonal rule runs off the
page at shallow angles and on landscape pages).

## Draft design


### Intent

A faint, tilted word across every page ("DRAFT", "CONFIDENTIAL", "COPY") is a common ask for generated documents,
and today it takes a hand-built overlay that can't be rotated. Papercut can already place things on a page and
draw them see-through; the missing piece is rotated text.

### Goals

- `Page::a4()->watermark('DRAFT')` in PHP and `<x-doc::page watermark="DRAFT">` in Blade.
- Drawn on every page the page template makes, like headers and backgrounds.
- Its look (font, size, weight, colour, opacity) comes from a `watermark` named style, so a theme or
  `papercut.styles` can restyle it.
- Its angle can be set: `->watermark('DRAFT', angle: 30)` / `watermark-angle="30"`. Default 45°, rising from bottom
  left to top right.
- PDF draws it exactly; HTML and Word come as close as each format allows and report the rest.

### Non-goals

- Image watermarks. A background or overlay already places an image; an image watermark can come later if asked
  for.
- A general rotation style for any content. Rotating content in the layout flow is a much bigger feature; this
  rotates one line of text that takes no space in the layout.
- Security. A watermark is visual only; it can be removed with a PDF editor. Encryption already exists for access
  control.
- Different watermarks per page within one page template (first page only, even pages). A document with different
  page templates can give each its own.

### Anti-goals

- Changing the output of documents without a watermark: every existing golden and snapshot stays unchanged.
- A watermark that hides the content. It must stay faint by default.
- Text running off the page by default (see "Size").

### How it works (decided)

- **On top.** The watermark is drawn over everything else on the page: the template PDF page, the background, the
  header, the body, the footer and the overlay. That way a shaded table row, a card or an image can't hide it.
  It's faint, so the text under it stays readable. (Decided by the maintainer.)
- **Placement.** Centred on the page (the page's centre, not the body's), rotated about its own centre.
  Page margins don't apply.
- **Text.** One line, no wrapping. Empty or whitespace-only text means no watermark. Calling `watermark()` again
  replaces it; `watermark(null)` removes it (so a page template derived from another can drop it).
- **Look.** Default `watermark` named style: weight 700, colour a mid grey (`#808080`), opacity 0.15, font size
  96pt, the document's font family. A theme overrides any of these. Other style properties (borders, padding,
  backgrounds, decoration) don't apply to a watermark and are ignored.
- **Size (*my call*).** The style's font size is used unless the text would be wider than the page's diagonal
  minus 10% at each end; then it's shrunk to fit that length, so a long watermark never runs off the page.
- **Angle.** Degrees, counter-clockwise, from -90 to 90 (anything else throws `InvalidValue`). 0 is horizontal.
- **Fonts and scripts.** It uses the same font loading and shaping as other text, so any script the font has works.
- **Not content.** The watermark is not a heading, gets no bookmark, isn't a table of contents entry and isn't
  read as part of the body by anything that extracts the body.

### Formats

- **PDF:** drawn exactly as above, with the same opacity mechanism other see-through content uses. The rotated
  text needs php-pdf's text-matrix drawing (`Page::showTextWithMatrix`, which takes embedded fonts). How the
  rotation reaches the backend (a new op, or a rotation on the text op) is left to the builder; whichever it is,
  the CHANGELOG tells custom `Backend` authors what to handle, and a custom backend that doesn't must fail loudly
  rather than draw the text unrotated.
- **HTML:** each page block gets the watermark centred over it with a CSS rotation, `pointer-events: none`, and
  the same colour, opacity, weight and size. HTML has no real pages, so it appears once per page block, not once
  per printed page; that's reported as approximated (once per page template), like the existing "header and
  footer once per page".
- **Word:** php-docx writes a watermark of its own fixed look (text only, tilted 45°, light grey, see-through) into
  the header, for the whole document. Papercut uses it:
  - The document's watermark text is the first page template's watermark.
  - It's reported as approximated when anything differs from what Word draws: a colour, opacity, size, font or
    angle other than the defaults; page templates with different watermarks, or some with and some without.
  - Word shows it behind the text, not on top; that difference is documented, not reported.
  - Whether it shows on a first page that has its own first-page header, and on even pages with an even-page
    header, depends on php-docx (it writes it into the default header only). The builder checks this; if those
    pages lose it, that's reported as approximated and documented.

### Docs

- A "Watermarks" section in the guide's pages page, with Blade and PHP tabs, the named style, the angle, and the
  per-format differences.
- The Blade tag reference (`watermark`, `watermark-angle` on `<x-doc::page>`), the named styles list.
- CHANGELOG entry.
- An example: add a watermark to one existing example only if it suits it (an invoice marked "PAID" or a report
  marked "DRAFT"); otherwise the guide's example and a visual fixture are enough.

