# Decorator styles

These are the named styles each decorator uses, with their built-in defaults. Every theme has them, even one you build from scratch. To change one, define a style with the same name in `config/papercut.php` or with `Theme::withStyle()`. See [Restyling](/guide/decorators.md#restyling) for an example.

Colours are Tailwind palette names.

| Styles | Defaults |
|---|---|
| `card`, `card.title`, `card.body` | 1pt `slate-300` border, 4pt radius. A bold `slate-100` title strip with 3pt corners. 10pt body padding. |
| `callout`, `callout.<tone>`, `callout.title`, `callout.title.<tone>` | A 3pt rule on the left and a tinted box, in `sky`, `emerald`, `amber` or `red` for `note`, `tip`, `warning` and `danger`: the rule in `-500`, the box in `-50`. A bold title in `-800`. |
| `blockquote`, `blockquote.cite` | A 3pt `slate-300` rule on the left, italic `slate-700`. The cite at `0.9em` in `slate-500`, upright. |
| `section`, `section.heading`, `section.number`, `section.title`, `section.rule`, `section.body` | A 6pt gap. A bold title, a bold `slate-500` number 18pt wide, a 0.75pt `slate-200` rule. The heading is always a row. |
| `field`, `field.label`, `field.placeholder` | 1pt `slate-300` border, 3pt radius. A `0.9em` `slate-600` label. An italic `slate-400` placeholder. |
| `choice`, `choice.option`, `choice.marker`, `choice.marker.radio`, `choice.marker.selected`, `choice.mark`, `choice.mark.radio`, `choice.label.selected` | A `0.75em` marker with a 1pt `slate-400` border, round for radio. Selected: a `slate-800` border, a `0.42em` inner mark and a bold label. 6pt between marker and label. |
| `key-value`, `key-value.label`, `key-value.value` | No borders. Labels in `slate-600`. |
| `pre`, `code` | Monospace (the theme's mono family) at `0.9em` on a `slate-100` background. `pre` also keeps spaces, tabs and line breaks (`white-space: pre-wrap`), with 6pt 8pt padding and a 6pt bottom margin. `code` is inline, with no padding. |
| `code-block`, `code-block.code`, `code-block.label`, `code-block.bar` | The box (`<x-doc::pre language>`): 0.5pt border, 3pt radius, 6pt 8pt padding, 6pt bottom margin. The code is monospace at `0.9em` with `pre-wrap`. The label is `0.7em`, semibold, right-aligned. The bar (`strip` only) has a bottom border. Colours are not built in: they come from the code theme, and only the block's own style (`->background()` in PHP, `background="…"` on the tag) overrides them. Named styles set geometry. To recolour every block, pick or write a theme. |
| `code-block.strip`, `code-block.code.strip`, `code-block.bar.strip`, `code-block.label.strip`, `code-block.caption`, `code-block.label.caption`, `code-block.code.caption` | The `strip` and `caption` looks, applied after the plain name. `strip` moves the padding from the box to the code and puts the label on the left. `caption` drops the border, radius and padding of the box and gives the code a 2pt rule on the left; the block itself puts the label above the box, and `code-block.label.caption` only sets its alignment and margin. These are built in, unlike other variants. |
| `badge`, `badge.<tone>` | A bold pill at `0.9em`, in `slate`, `sky`, `emerald`, `amber` or `red` for `neutral`, `note`, `tip`, `warning` and `danger`: the background in `-100`, the text in `-800`. |

Setting `pre` or `code` in your theme replaces the whole built-in style, not just the properties you give: a `pre` that sets only `font-family` loses `white-space: pre-wrap`, its padding and its background. To change the font, set `mono_family` instead. When you do override `pre`, include `white-space: pre-wrap`.

## How the names combine

A `warning` callout applies `callout` and then `callout.warning`, and its title applies `callout.title` and then `callout.title.warning`. Where they overlap, the later style wins. In practice the base style sets the shape and the tone style sets the colours, so you can replace either one on its own.

Badges work the same way (`badge`, then `badge.<tone>`), and so do selected checkboxes (`choice.marker`, then `choice.marker.selected`).

## Variants

With `variant="large"` (`->variant('large')` in PHP), every name above that a decorator applies is followed by the same name with `.large` added, such as `section.title.large` or `callout.title.large`, if your theme defines it. The order is the built-in name, the tone, the variant, then your own `style=""`. Tone names (`badge.<tone>`, `callout.<tone>`, `callout.title.<tone>`) do not take variant suffixes: variants follow the tone-less part names. Variant styles are not built in. See [Variants](/guide/decorators.md#variants).
