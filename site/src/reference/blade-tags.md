# Blade tags

Tags marked **Style** accept every [style attribute](./style-attributes.md), plus `style="name"` for a named style. Using an attribute a tag doesn't support throws `UnknownAttribute`, with a suggestion if there's a close match.

## Structure

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::document>` | `title`, `author`, `subject`, `keywords`, `lang` | The root. Holds `<x-doc::page>` tags; content with no page gets an implicit one. No style. |
| `<x-doc::page>` | `size`, `orientation`, `margin`, `header-gap`, `footer-gap` | `size` is a paper name or `"<width> <height>"`; `orientation` is `portrait` or `landscape`. Slots: `header`, `footer`, `background`. No style. |
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
| `<x-doc::markup>` | `html` or `text`, `styles`, `blank-paragraphs` | Converts stored HTML or plain text. See [Rich text](/guide/rich-text.md). |
| `<x-doc::link>` | `href` | Style. `href` is required. |

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
| `<x-doc::svg>` | `src` or `data`, or SVG markup inside the tag | Style. Sized like an image. Relative `src` stays inside `papercut.image_path`. |
| `<x-doc::qr-code>` | `value`, `level`, `quiet-zone` | Style. `level` is `L`, `M`, `Q` or `H`. Square: size it with `width`. No content. |
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
