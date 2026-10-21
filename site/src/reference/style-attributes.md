# Style attributes

Every tag marked **Style** in the [tag reference](./blade-tags.md) accepts these attributes. The PHP methods with the same names take typed values instead, like `width(mm(70))` or `background('slate-50')`.

**Lengths** can be `pt`, `mm`, `cm`, `in` or `px`, or relative to the element: `em` for its font size and `lh` for its line height. **Colours** are Tailwind palette names like `slate-500`, or hex codes. **Booleans** can be written on their own (`bold`) or as `true`, `1`, `false` or `0`.

## Box

| Attribute | Example | Accepts |
|---|---|---|
| `width`, `height`, `basis` | `width="70mm"` | a length, a percentage or `auto` |
| `min-width`, `max-width`, `min-height`, `max-height` | `max-width="80%"` | a length or a percentage |
| `padding`, `margin`, `border-width` | `margin="6pt 0 0 0"` | one to four lengths: top, right, bottom, left |
| `border-color`, `background` | `background="slate-50"` | a colour |
| `border-style` | `border-style="dashed"` | `solid`, `dashed` |
| `radius` | `radius="8pt"` | a length |
| `opacity` | `opacity="0.5"` | 0 to 1 |

## Flex

| Attribute | Example | Accepts |
|---|---|---|
| `direction` | `direction="column"` | `row`, `column` |
| `gap` | `gap="8pt"` | a length |
| `justify` | `justify="space-between"` | `start`, `end`, `center`, `space-between`, `space-around`, `space-evenly` |
| `align-items`, `align-self` | `align-items="center"` | `start`, `end`, `center`, `stretch` |
| `grow`, `shrink` | `grow="1"` | a number, 0 or more |
| `wrap` | `wrap` | a boolean |

## Text

| Attribute | Example | Accepts |
|---|---|---|
| `font` | `font="Merriweather"` | a family name |
| `font-size` | `font-size="9pt"` | a length above 0 |
| `weight` | `weight="600"` | an integer from 1 to 1000 |
| `bold`, `italic`, `underline`, `strike` | `bold` | a boolean |
| `color` | `color="slate-600"` | a colour |
| `line-height` | `line-height="1.4"` | a multiple of the font size, above 0 |
| `letter-spacing` | `letter-spacing="0.6pt"` | a length |
| `text-align` | `text-align="right"` | `left`, `right`, `center`, `justify` |

## Pagination

| Attribute | Example | Accepts |
|---|---|---|
| `keep-together` | `keep-together` | a boolean. Moves the block to the next page whole instead of splitting it. |
| `keep-with-next` | `keep-with-next` | a boolean. Keeps the block on the same page as the next one. |
| `break-before`, `break-after` | `break-before` | a boolean. Starts a new page. |
| `min-lines` | `min-lines="2"` | an integer, 1 or more. The minimum lines of a paragraph on each side of a page break. |

## Bookmarks

| Attribute | Example | Accepts |
|---|---|---|
| `bookmark` | `bookmark="Summary"` | a title for the PDF's bookmarks panel. Headings get one automatically; `:bookmark="false"` removes it. |
| `bookmark-level` | `bookmark-level="2"` | an integer, 1 or more. How deeply the bookmark is nested. |
