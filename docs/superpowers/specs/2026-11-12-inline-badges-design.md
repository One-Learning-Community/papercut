# Inline badges, and badges Word can show

## Why

A badge today is a block: `Badge` is a decorator that renders a padded, rounded `View`. That covers a badge in its own
table cell or row, but not one inside a sentence: "the request returns **[201]** when it works", "status
**[Beta]**", a version or HTTP-method label in running text. Those are common in technical and business documents.

Separately, the Word export writes every badge as a one-cell table nested in its container. Google Docs puts a nested
table below the line rather than beside it, so each row with a badge doubles in height: the guide example grows from
3 to 5 pages and the responses example from 1 to 2. Even LibreOffice wraps long labels inside the tiny table letter by
letter ("Stro/ngly/agre/e").

A real inline badge solves both: each output can draw it natively, and Word writes it as text.

## Goals

- Badges inside running text in every input: PHP (`Span`), inline markup strings, Blade, and the HTML converter where
  it fits.
- One look: inline badges use the same theme styles as block badges (`badge`, `badge.<tone>`, variants such as
  `badge.outline`), so a theme override restyles both.
- Native output in each format:
  - **PDF**: a rounded pill behind the text, with horizontal padding, sitting on the line. It never breaks across
    lines.
  - **HTML export**: an inline element with padding, radius, background and `white-space: nowrap`.
  - **Word**: a shaded run (see below), for inline badges **and** for block badges. No nested tables.
- Word's result looks right in Word, LibreOffice and Google Docs. Google Docs, which ignores run borders, still
  shows shading on the line.

## Non-goals

- Rounded corners or true vertical padding in Word. Word has no inline box.
- General inline boxes (inline images, inline-block containers).
- Restyling existing inline code or `<mark>`. Their look stays as it is.
- Any change to block badges in PDF or HTML.

## Anti-goals

- Badges whose Word output falls off the line in Google Docs or wraps mid-label in any app.
- Two theme entries, or two looks, for inline and block badges.
- PDF or HTML output that changes for documents without inline badges. Goldens move only where an example adds an
  inline badge, and in the Word examples that have block badges (allowed before 1.0; report them).

## Decisions

### Core model

- Inline spans gain the inline-box properties a badge needs: **horizontal padding**, **radius**, **border**
  (width and colour) and **background**, with CSS inline-box behaviour:
  - Horizontal padding adds space before and after the text, and counts in line breaking.
  - The background and border cover the padded text from roughly ascent to descent. They do not take the full line
    height and do not change the line height. Vertical padding, if a style sets it, only grows the painted box.
  - A span with horizontal padding or a border stays on one line. If it is wider than the line, treat it like an
    overlong word and do whatever the line breaker already does for those.
  - Spans without these properties are unchanged. Existing highlights keep their full-line-height rect, so no
    existing output moves.
- `Span::badge(string $text, string $tone = 'neutral')` is the public constructor. It uses the named styles
  `badge badge.<tone>`, the same as the block `Badge`'s `badge` part. Variants work as on the block decorator.
  The builder decides the exact API shape for variants, e.g. a `variant` parameter or a method on a small builder.
  The block-only parts of the `badge` style, such as `alignSelf`, mean nothing inline and are ignored.
- The tone rules are those of `Badge::tone()`: a single word, and `badge.<tone>` must exist in the theme.

### Inputs

- **An inline badge's label is plain text** *(my call)*. It is one span: no formatting, `<code>` or nested badges
  inside it. A badge is a short label, and keeping it to one span avoids giving spans a grouping identity (one pill
  across several runs, padding only at the outer ends). A badge inside a link (a badge span with an `href`) is fine.
  A block `Badge` keeps accepting rich content.
- **Inline markup** (`Text::markup()`, `InlineMarkup`): `<badge>201</badge>` and `<badge type="tip">201</badge>`,
  plus `variant` if the `Span::badge` API has it. Any tag inside `<badge>` is an error naming the rule.
  The attribute is **`type`**, matching Blade's existing `<x-doc::badge type="...">` *(my call: tag syntaxes agree
  with each other; PHP keeps `tone()`, as `Badge` already does)*.
- **Blade**: `<x-doc::badge>` inside a text tag (`p`, `li`, `td`, `th`, headings, etc.) becomes an inline badge, the
  way `<x-doc::code>` becomes an inline span there. Elsewhere it stays the block badge. Same tag, same attributes
  (`type`, `variant`). Inside a text tag, its content must be text only, as for `<x-doc::code>`.
- **HTML converter**: out of scope *(my call)*. It has almost no class handling (only `language-` on `pre`), so
  `<span class="badge">` mapping would be new machinery. Future work.

### Outputs

- **PDF**: draw the pill (radius, background, border) behind the text, then the text. Tagged PDF needs no new role:
  the badge text is part of its paragraph's content. On the `tagged-pdf` branch it simply stays inside its P.
- **HTML export**: `<span class="pc-badge" style="...">` with the resolved padding, radius, background, border,
  colour, size, weight and `white-space: nowrap`.
- **Word**, decided by experiment on 2026-11-12 (Google Docs and LibreOffice conversions of five variants). A badge
  run has:
  - the background as run shading (`w:shd`), and the badge's text colour, weight and size;
  - **two no-break spaces on each side** of the label;
  - no-break spaces in place of the label's own spaces, so it never wraps;
  - a run border (`w:bdr`): in the fill colour when the badge has no border, which gives a little vertical padding
    in Word and LibreOffice; in the border colour for bordered variants such as `outline`. Google Docs ignores it and
    shows plain shading, which is acceptable.
  - The outline variant (transparent background plus a border) is written as a run border and no shading.
  - A **block** `Badge` whose content is a single paragraph of inline text becomes a paragraph holding that run, in
    place of today's nested table. Badges that are a table cell's only content, as in the guide's Method column, then
    sit in the cell's own paragraph. A block badge with other content (an image, a row, several paragraphs) keeps
    today's nested table *(my call: keeps current behaviour where a run can't hold the content)*.
  - Word recognises block badges by a hint on `View` set by `Badge::render()`, not by box shape. (The tagged-pdf
    branch adds a similar per-view hint, `structRole()`; it isn't on `main`, so add the hint independently.)
  - `packages/docx/src/Map/InlineRow.php` already writes badge-like items in an all-inline row as shaded runs, with
    one no-break space each side, guessed by box shape. *(My call:)* one shared badge-run writer produces the format
    above for every badge, whether in an inline row, alone in a cell, or inline in text. InlineRow uses the `View`
    hint for badges, and keeps its shape guess for user-made pill views that aren't badges, now written by the same
    writer. Word output for those pills changes slightly (two spaces, a fill-colour border); allowed before 1.0, report it.

## Examples and docs

- Add an inline badge where it reads naturally in an existing example; the guide example's API text is the obvious
  place. Keep examples generic (no consumer names).
- Document inline badges next to the block `Badge` docs (PHP, markup, Blade), and note the Word rendering.
- CHANGELOG entry.

## Verification

- Unit and feature tests per input and output, including line breaking (a badge doesn't split; its padding counts),
  theme override reaching inline badges, and the outline variant.
- PDF and HTML goldens are unchanged for examples without inline badges.
- Re-run the Google Docs round trip (upload the Word examples through the Drive connector, export as PDF, compare
  page counts): guide and responses should match the PDF's page counts, or come within a page and lose the
  dropped-badge rows. Also look at LibreOffice output.

## Left to the builder

- Where the inline-box painting lives in `TextLayouter`, and how padding enters `LineBreaker`/`Itemizer` measuring.
- How the theme's `badge` padding is read inline. Use the style's horizontal padding as given (6pt in the default
  theme).
- The `Span::badge` variant API.
- Branch: `inline-badges` off `main`. It is independent of PDF/A and tagged PDF; conflicts with those branches
  (TextLayouter, HtmlWriter, docx mappers) get resolved when they are condensed.
