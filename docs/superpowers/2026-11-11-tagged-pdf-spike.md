# Tagged PDF / PDF/UA spike (2026-11-11)

Branch `spike/tagged-pdf` (local). Enable with `new PdfOptions(backend: new PhpPdfBackend(tagged: true))`.

## What was built

- `Fragment::$tag` (`Paint\Tag`: role, node-path key, alt, raw /A attributes) set in `LayoutEngine::layout()`:
  Text → P or H1–H6 (HeadingText level), ListNode → L, MarkedItem → LI, marker → Lbl, item body → LBody,
  Table → Table, row fragments → TR, TableCell → TD, or TH with `/Scope /Column` in header rows,
  Image with `alt()` → Figure /Alt, Image without alt, Svg, QrCode, PdfPage, Spacer → Artifact.
  Header, footer, background, overlay and template regions → Artifact. Repeated table headers → Artifact.
- `Painter::flatten()` wraps a tagged fragment's ops in a `Paint\Tagged` group (like Clip/Opacity).
- `PhpPdfBackend` (tagged mode): a Document root container; a group holding other tags becomes a container,
  otherwise a leaf with one MCID; untagged drawing (borders, fills, rules) is marked as a /Layout artifact.
  Containers stay open across pages and close lazily by node-path prefix, so a table split over pages
  is ONE Table element. Tab order /S, DisplayDocTitle, object streams on.
- `Image::alt(?string)`.

## Results (veraPDF 1.30, PDF/UA-1 profile, all 8 core examples)

Only three rules fail, and none is about structure:

| Rule | Cause | Owner |
|---|---|---|
| 7.1-8 Catalog /Metadata (XMP with pdfuaid:part) | php-pdf writes XMP only for PDF/A and PDF/X | upstream |
| 7.18.1-2, 7.18.5-2 Link annotations need /Contents | `addExternalLink`/`addInternalLink` take no description | upstream |
| 7.4.2-1 heading levels skipped (H1 → H3) | our example content (guide, paper, responses, transcript) | us: warn + fix examples |

With a throwaway local patch to php-pdf (XMP + /Contents): article, certificate, invoice and report are
PDF/UA-1 compliant, and the other four fail only on the heading skips.

Untagged output is byte-identical (GoldenBytesTest passes). Cost of tagging: about +5–10% render time.
File size is about the same as untagged once object streams are on (report 99 KB both ways;
transcript 63 → 73 KB; without object streams it was 136 KB).

## Gaps veraPDF does not catch (semantic quality)

- php-pdf puts each Link struct element (OBJR only) at the root, after all content, instead of
  inside the paragraph with the link text. Valid, but poor for screen readers. Upstream: let the caller
  place the Link element under the open container and add the text's MCID.
- A paragraph split across pages becomes two P elements (php-pdf leaves hold one MCID on one page).
  Upstream nice-to-have: leaves with several MCIDs / MCR dicts on other pages.
- /A attributes are injected through the role string (`TH /A << ... >>`). Upstream: a real attributes parameter.
- No alt-text API yet on Svg or QrCode (always artifacts); Markup `<img alt>` and Laravel components not wired.
- Decorators get no specific roles yet: Blockquote → BlockQuote, CodeBlock → Code, Toc → TOC/TOCI,
  Link node → Link, Callout/Card → Div or Sect, Figure captions → Caption.
- Word export: images could carry alt text as `docPr/@descr` for parity.
- A manual check with a screen reader or Acrobat's accessibility checker is still needed:
  veraPDF only machine-checks part of Matterhorn.

## To productionize

Papercut: a public option (e.g. `PdfOptions(tagged: true)`, with PDF/UA turning on lang/title checks),
compute tag keys only when tagging, alt text on Image/Svg/QrCode + Markup + Laravel, heading-skip warning,
decorator roles, fix the 79 tests whose display-list helpers don't recurse into `Tagged`, veraPDF UA-1 in CI, a docs page.

Upstream php-pdf (one PR, best stacked on #53 since both write XMP): XMP with pdfuaid + dc:title when tagged,
link /Contents, struct attributes, Link elements nested in place.
