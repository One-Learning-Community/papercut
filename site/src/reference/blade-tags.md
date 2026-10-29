# Blade tags

Tags marked **Style** accept every [style attribute](./style-attributes.md), plus `style="name"` for a named style. Using an attribute a tag doesn't support throws `UnknownAttribute`, with a suggestion if there's a close match.

## Structure

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::document>` | `title`, `author`, `subject`, `keywords`, `lang` | The root. Holds `<x-doc::page>` tags; content with no page gets an implicit one. No style. |
| `<x-doc::page>` | `size`, `orientation`, `margin`, `header-gap`, `footer-gap`, `mirror`, `template` or `template-data`, `template-page`, `template-password` | `size` is a paper name or `"<width> <height>"`; `orientation` is `portrait` or `landscape`. `template` draws a page of a PDF behind the page and sizes the page from it, so it can't be used with `size` or `orientation`. `mirror` is a boolean attribute that mirrors the left and right margins on even pages. Slots: `header`, `footer`, `first-header`, `first-footer`, `even-header`, `even-footer`, `background`, `overlay`. An empty `first-` or `even-` slot means nothing on that page. No style. |
| `<x-doc::place>` | `x`, `y`, `anchor`, `width` | Only in the `overlay` slot. `x`, `y` and `width` are lengths or percentages of the page; `anchor` is `top-left` (the default), `top`, `top-right`, `left`, `center`, `right`, `bottom-left`, `bottom` or `bottom-right`. No style. |
| `<x-doc::page-break>` | none | Starts a new page. |
| `<x-doc::page-number>` | `format`, `align`, `size` | Style. `format` uses `{page}` and `{total}`. No content. |

## Layout

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::view>` | `align` | Style. A generic box. |
| `<x-doc::row>` | `align` | Style. Children side by side. |
| `<x-doc::column>` | `align` | Style. Children top to bottom. |
| `<x-doc::spacer>` | `size` | A fixed gap with `size`, a flexible one without. Accepts `grow` and nothing else. |

On boxes, `align` is short for `align-items`.

## Text

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::text>` | `align`, `size` | Style. Inline markup: `<b>`, `<i>`, `<u>`, `<s>`, `<mark>`, `<span>`, `<a href>`, `<br>`. |
| `<x-doc::p>` | `align`, `size` | Style. The paragraph preset. |
| `<x-doc::h1>` to `<x-doc::h4>` | `align`, `size` | Style. Heading presets. |
| `<x-doc::markup>` | `html` or `text`, `styles`, `blank-paragraphs`, `images` | Converts stored HTML or plain text. `images` is an `ImageResolver` class name for this tag instead of `papercut.markup.images`, or `none` (lower case only) to show each image's alt text. See [Rich text](/guide/rich-text.md). |
| `<x-doc::link>` | `href` | Style. `href` is required. |
| `<x-doc::pre>` | none | Style. A preformatted block in the `pre` style. The content is literal: tags are not parsed and entities are decoded, so `{{ $code }}` comes out exactly as the variable's value. The first and last blank lines are dropped and the common leading indentation is removed (put `{{ $code }}` right after the opening tag, or at the same indentation as the rest of the block; the first line's indentation counts too). Components inside are an error. If you wrap a pre in a component of your own (`<x-doc::pre>{{ $slot }}</x-doc::pre>`), Blade trims the outer slot before the pre sees it: the first line loses its indentation and later lines keep theirs, so give the slot content that starts at the left edge or has an unindented first line. |
| `<x-doc::code>` | none | Style, applied to the span. Inline code in the `code` style, for use inside `<x-doc::text>`, `<x-doc::p>` and the other text tags. The content is literal text. |

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
| `<x-doc::td>`, `<x-doc::th>` | `colspan`, `align` | Style. `th` is a bold header cell. |

## Media and embedding

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::image>` | `src` or `data`, `fit` | Style. `fit` is `contain`, `cover` or `fill`. Relative `src` stays inside `papercut.image_path`. |
| `<x-doc::pdf-page>` | `src` or `data`, `page`, `password` | Style. A page of an existing PDF, sized like an image. Relative `src` stays inside `papercut.image_path`. Left out of HTML exports. |
| `<x-doc::svg>` | `src` or `data`, or SVG markup inside the tag | Style. Sized like an image. Relative `src` stays inside `papercut.image_path`. |
| `<x-doc::qr-code>` | `value`, `level`, `quiet-zone`, `shape`, `eyes`, `eye-color`, `gradient`, `gradient-type` | Style. `level` is `L`, `M`, `Q` or `H`. Square: size it with `width`. `shape` is `square`, `rounded` or `dots` (the last two raise `level` to at least `Q`); `eyes` is `square`, `dot` or `pointy`; `gradient` is two colors. Styling needs `bacon/bacon-qr-code`. No content. |
| `<x-doc::node>` | `node` | Embeds a `Node` or `Component` built in PHP. |

## Decorators

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::card>` | `title` | Style. `title` also works as a slot. |
| `<x-doc::callout>` | `type`, `title` | Style. `type`: `note`, `tip`, `warning`, `danger`, or a tone you define. |
| `<x-doc::blockquote>` | `cite` | Style. `cite` also works as a slot. |
| `<x-doc::section>` | `title`, `number`, `rule` | Style. `title` also works as a slot. |
| `<x-doc::field>` | `label`, `placeholder`, `lines` | Style. `label` also works as a slot. `lines` is a positive integer. |
| `<x-doc::choices>` | `type`, `emphasize-selected` | Style. `type`: `checkbox` or `radio`. Holds only `<x-doc::choice>`. |
| `<x-doc::choice>` | `selected` | Valid only inside `<x-doc::choices>`. No style. |
| `<x-doc::key-value>` | `label-width` | Style. A length or percentage. Holds only `<x-doc::kv>`. |
| `<x-doc::kv>` | `label` | A label is required, as an attribute or a slot. No style. |
| `<x-doc::badge>` | `type` | Style. `type`: `neutral`, `note`, `tip`, `warning`, `danger`, or a tone you define. |
