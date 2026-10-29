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
| `badge`, `badge.<tone>` | A bold pill at `0.9em`, in `slate`, `sky`, `emerald`, `amber` or `red` for `neutral`, `note`, `tip`, `warning` and `danger`: the background in `-100`, the text in `-800`. |

## How the names combine

A `warning` callout applies `callout` and then `callout.warning`, and its title applies `callout.title` and then `callout.title.warning`. Where they overlap, the later style wins. In practice the base style sets the shape and the tone style sets the colours, so you can replace either one on its own.

Badges work the same way (`badge`, then `badge.<tone>`), and so do selected checkboxes (`choice.marker`, then `choice.marker.selected`).
