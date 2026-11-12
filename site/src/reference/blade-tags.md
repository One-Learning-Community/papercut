# Blade tags

Tags marked **Style** accept every [style attribute](./style-attributes.md), plus `style="name"` for a named style. Using an attribute a tag doesn't support throws `UnknownAttribute`, with a suggestion if there's a close match.

## Structure

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::document>` | `title`, `author`, `subject`, `keywords`, `lang`, `theme` | The root. Holds `<x-doc::page>` tags; content with no page gets an implicit one. `:theme` overrides the theme for this document; see [Changing the theme for one document or page](/guide/styles.md#changing-the-theme-for-one-document-or-page). No style. |
| `<x-doc::page>` | `size`, `orientation`, `margin`, `header-gap`, `footer-gap`, `mirror`, `template` or `template-data`, `template-page`, `template-password`, `theme` | `size` is a paper name or `"<width> <height>"`; `orientation` is `portrait` or `landscape`. `template` draws a page of a PDF behind the page and sizes the page from it, so it can't be used with `size` or `orientation`. `mirror` is a boolean attribute that mirrors the left and right margins on even pages. Slots: `header`, `footer`, `first-header`, `first-footer`, `even-header`, `even-footer`, `background`, `overlay`. An empty `first-` or `even-` slot means nothing on that page. Slot names also accept camelCase (`<x-slot:firstHeader>`); if both spellings are given, the later one wins. `:theme` overrides the theme for this page, its headers, footers, background and overlay; see [Changing the theme for one document or page](/guide/styles.md#changing-the-theme-for-one-document-or-page). No style. |
| `<x-doc::place>` | `x`, `y`, `anchor`, `width` | Only in the `overlay` slot. `x`, `y` and `width` are lengths or percentages of the page; `anchor` is `top-left` (the default), `top`, `top-right`, `left`, `center`, `right`, `bottom-left`, `bottom` or `bottom-right` (`top-center`, `bottom-center`, `center-left` and `center-right` work too). No style. |
| `<x-doc::page-break>` | none | Starts a new page. |
| `<x-doc::toc>` | `levels`, `title`, `leaders` | Style. A [table of contents](/guide/pages.md#tables-of-contents). `levels` is `"2"` or a range like `"1-3"` (1 to 4; default `1-2`). `title` is a line above the list. `leaders` (default on) draws dot leaders; `:leaders="false"` or `leaders="false"` turns them off. No content allowed. |
| `<x-doc::page-number>` | `format`, `align`, `size` | Style. `format` uses `{page}` and `{total}`. No content. |

## Layout

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::view>` | `align` | Style. A generic box. |
| `<x-doc::row>` | `align` | Style. Children side by side. |
| `<x-doc::column>` | `align` | Style. Children top to bottom. |
| `<x-doc::spacer>` | `size` | A fixed gap with `size`, a flexible one without. Accepts `grow` and nothing else. |
| `<x-doc::column-flow>` | `columns`, `widths`, `column-gap`, `rule` | Style. Text set in [columns](/guide/layout.md#column-flow), like Word's. `columns` is 1 to 45 (default 2); `widths="2 1"` sets unequal columns as ratios; `column-gap` is one length, or one per gap (default 36pt); `rule` draws a line in each gap, bare or `rule="1pt slate-400"`. `gap` spaces the children, as on any box. No border, background, padding or size: wrap it in a `view` for those. Not inside a table cell, a row, another flow, or a header, footer, background or overlay. |
| `<x-doc::column-break>` | none | Sends what follows to the next column; in the last column, to the next page. Only inside a `column-flow`. |

On boxes, `align` is short for `align-items`.

## Text

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::text>` | `align`, `size`, `heading` | Style. Inline markup: `<b>`, `<i>`, `<u>`, `<s>`, `<mark>`, `<code>`, `<span>`, `<a href>`, `<br>`. `heading` (1 to 4) sets the heading level the document outline and a tagged PDF use, keeping the look: `<x-doc::h3 heading="2">`. |
| `<x-doc::p>` | `align`, `size`, `heading` | Style. The paragraph preset. `heading` (1 to 4) sets the heading level the document outline and a tagged PDF use, keeping the look: `<x-doc::h3 heading="2">`. |
| `<x-doc::h1>` to `<x-doc::h4>` | `align`, `size`, `heading` | Style. Heading presets. `heading` (1 to 4) sets the heading level the document outline and a tagged PDF use, keeping the look: `<x-doc::h3 heading="2">`. |
| `<x-doc::markup>` | `html` or `text`, `styles`, `blank-paragraphs`, `images`, `toc` | Converts stored HTML or plain text, or plain HTML written in the slot (one of the three). Papercut tags in the slot are an error; components that render HTML are fine. `images` is an `ImageResolver` class name for this tag instead of `papercut.markup.images`, or `none` (lower case only) to show each image's alt text. `toc="false"` keeps every heading it converts out of tables of contents. See [Rich text](/guide/rich-text.md). |
| `<x-doc::link>` | `href` | Style. `href` is required. |
| `<x-doc::pre>` | `language`, `theme`, `label`, `variant` | Style. A preformatted block in the `pre` style. With `language` it is a [code block](/guide/text.md#code-blocks): coloured by a TextMate `theme` (default `papercut-light`), with a `label` (default the language's name in capitals; `false` hides it) and a `variant`, `strip` (label in a header bar) or `caption` (label above the box, rule on the left). `theme`, `label` and `variant` need a `language`. Highlighting needs `phiki/phiki`. The content is literal: tags are not parsed and entities are decoded, so `{{ $code }}` comes out exactly as the variable's value. The first and last blank lines are dropped and the common leading indentation is removed (put `{{ $code }}` right after the opening tag, or at the same indentation as the rest of the block; the first line's indentation counts too). Components inside are an error. The tag is found by a compile step that runs on every Blade view in the app, so a PHP string holding a literal `<x-doc::pre>` tag in an ordinary (non-Papercut) view gets an invisible U+E000 character after it. If you wrap a pre in a component of your own (`<x-doc::pre>{{ $slot }}</x-doc::pre>`), Blade trims the outer slot before the pre sees it: the first line loses its indentation and later lines keep theirs, so give the slot content that starts at the left edge or has an unindented first line. |
| `<x-doc::code>` | none | Style, applied to the span. Inline code in the `code` style, for use inside `<x-doc::text>`, `<x-doc::p>` and the other text tags. The content is literal text. In PHP, `Span::code($text)` or `Text::code($text)`. |

On text tags, `align` is `text-align` and `size` is `font-size`.

## Lists

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::ol>` | `start`, `marker` | Style. A numbered list of `<x-doc::li>`. |
| `<x-doc::ul>` | `marker` | Style. A bulleted list of `<x-doc::li>`. |
| `<x-doc::li>` | `value` | Style. Holds any block content, nested lists included. |

`marker` is `decimal`, `lower-alpha`, `upper-alpha`, `lower-roman`, `upper-roman`, `disc`, `circle`, `square` or `none`.

## Tables

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::table>` | `columns`, `repeat-header` | Style. An optional `<x-doc::thead>` first, then `<x-doc::tr>` rows. |
| `<x-doc::thead>` | none | Cells for one header row, or `<x-doc::tr>` rows for several. |
| `<x-doc::tr>` | none | Style. Holds `<x-doc::td>` and `<x-doc::th>`. |
| `<x-doc::td>`, `<x-doc::th>` | `colspan`, `rowspan`, `align` | Style. `th` is a bold header cell. |

## Media and embedding

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::image>` | `src` or `data`, `fit`, `alt` | Style. `fit` is `contain`, `cover` or `fill`. `alt` is the alternative text (blank means decorative). Relative `src` stays inside `papercut.image_path`. |
| `<x-doc::pdf-page>` | `src` or `data`, `page`, `password` | Style. A page of an existing PDF, sized like an image. Relative `src` stays inside `papercut.image_path`. Left out of HTML and Word exports. |
| `<x-doc::svg>` | `src` or `data`, or SVG markup inside the tag, `alt` | Style. Sized like an image. `alt` replaces the SVG's `<title>` as its alternative text. Relative `src` stays inside `papercut.image_path`. |
| `<x-doc::qr-code>` | `value`, `level`, `quiet-zone`, `shape`, `eyes`, `eye-color`, `gradient`, `gradient-type`, `alt` | Style. `alt` is the alternative text, `QR code: <value>` by default. `level` is `L`, `M`, `Q` or `H`. Square: size it with `width`. `shape` is `square`, `rounded` or `dots` (the last two raise `level` to at least `Q`); `eyes` is `square`, `dot` or `pointy`; `gradient` is two colors. Styling needs `bacon/bacon-qr-code`; see [Styled QR codes](/guide/images-and-links.md#styled-qr-codes). No content. |
| `<x-doc::node>` | `node` | Embeds a `Node` or `Component` built in PHP. |

## Decorators

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::card>` | `title`, `heading`, `variant` | Style. `title` also works as a slot. `heading` (1 to 4) makes the title a heading, see [Titles as headings](/guide/decorators.md#titles-as-headings). |
| `<x-doc::callout>` | `type`, `title`, `variant` | Style. `type`: `note`, `tip`, `warning`, `danger`, or a tone you define. |
| `<x-doc::blockquote>` | `cite`, `variant` | Style. `cite` also works as a slot. |
| `<x-doc::section>` | `title`, `number`, `rule`, `heading`, `variant` | Style. `title` also works as a slot. `heading` (1 to 4) makes the title a heading, see [Titles as headings](/guide/decorators.md#titles-as-headings). |
| `<x-doc::field>` | `label`, `placeholder`, `lines`, `variant` | Style. `label` also works as a slot. `lines` is a positive integer. |
| `<x-doc::choices>` | `type`, `emphasize-selected`, `variant` | Style. `type`: `checkbox` or `radio`. Holds only `<x-doc::choice>`. |
| `<x-doc::choice>` | `selected` | Valid only inside `<x-doc::choices>`. No style. |
| `<x-doc::key-value>` | `label-width`, `variant` | Style. A length or percentage. Holds only `<x-doc::kv>`. |
| `<x-doc::kv>` | `label`, `total` | A label is required, as an attribute or a slot. `total` (a boolean) marks an emphasised row and is only allowed inside `<x-doc::totals>`. No style. |
| `<x-doc::totals>` | `variant` | Style. Holds only `<x-doc::kv>` rows: labels on the left, amounts right-aligned. 45% wide at the right by default. |
| `<x-doc::stat>` | `label`, `note`, `variant` | Style. The content is the value, with inline tags. `label` and `note` also work as slots. |
| `<x-doc::signature>` | `name`, `role`, `date`, `variant` | Style. The content sits on the line (an image, a typed name, or nothing). `name`, `role` and `date` also work as slots. |
| `<x-doc::badge>` | `type`, `variant` | Style. `type`: `neutral`, `note`, `tip`, `warning`, `danger`, or a tone you define. |

`variant` makes a decorator also apply `<name>.<variant>` for each part it styles; see [Decorators](/guide/decorators.md).
