# One-call math output (`PendingMath`)

## Why

Every reader returns the same tree and every writer takes it, but getting a formula onto a page takes three objects
and some arithmetic. The recipes page shows the cost:

- A web page needs a `SvgRenderer` built with the font, a `Math::layout()` call, and the descent turned into a
  `vertical-align`. The inline recipe is a helper function (`inlineMath()`) the reader copies.
- Email needs a `PngRenderer`, a layout, `getimagesizefromstring()` and four lines to place the baseline.
- Accessible SVG needs `TextWriter` output escaped into an `aria-label` by hand.
- The display flag a reader found in the source (`$$…$$`, `\[…\]`, `display="block"`) is on `TexResult` and
  `MathmlResult`, but `Math::tex()` and `Math::mathml()` drop it, so the caller passes `display` again.

Success: the common cases in the recipes page become one chain, and the hand-written baseline and label code goes away.

## Decisions taken with the user

- A wrapper class, `Quoin\Math\PendingMath`, named after the Laravel package's `PendingDocument`: a formula that has
  been read and is waiting for a format method.
- `Math::tex()` and `Math::mathml()` keep returning `Node`, so they still compose with the builder.
- Display is a modifier (`display()`, `inline()`), not a second set of method names. `inlineSvg()` is out: core's HTML
  options already use `inlineSvgs` for SVG markup written into a page.
- Inline `svg()` returns one bare `<svg>` that carries its own `vertical-align` and accessible label.
- The existing writers and renderers stay public and unchanged. `PendingMath` only calls them.

## API

```php
use Quoin\Math\PendingMath;

PendingMath::tex('\pi r^2')->svg();                      // inline, 16 px, currentColor
PendingMath::tex($tex)->display()->svg(size: 18);
PendingMath::mathml($mml)->png(scale: 2);
PendingMath::of(Math::frac('a', 'b'))->mathml();
PendingMath::tex($tex)->display()->omml();               // oMathPara
PendingMath::tex($tex)->tex();
PendingMath::tex($tex)->text();
PendingMath::tex($userInput)->notes();
```

### Construction

| Method | Source |
|---|---|
| `PendingMath::tex(string $tex)` | `TexReader::read()`; display mode from the result |
| `PendingMath::mathml(string\|\Dom\Element $input)` | `MathmlReader::read()`; display mode from the result |
| `PendingMath::of(Node $node)` | a tree from the builder or elsewhere; starts inline |

The constructor is private. Like the readers, the TeX and MathML constructors never throw on bad input.

### Modifiers

Each returns a new instance; the class is `final readonly`.

| Method | Effect |
|---|---|
| `display()` | display style: SVG and PNG laid out with `display: true`, MathML `display="block"`, OMML `oMathPara` |
| `inline()` | text style, overriding a display flag from the source |
| `font(Font $font)` | the font for `svg()` and `png()`; default STIX Two Math |

The mode starts from the source: `$$x$$` and `\[x\]` in TeX and `display="block"` in MathML start in display mode,
everything else inline. `tex()`, `text()` and `notes()` ignore the mode.

### Output

| Method | Returns |
|---|---|
| `svg(float $size = 16.0, string $color = 'currentColor', float $pad = 0.0, ?string $label = null)` | `string` |
| `png(float $size = 16.0, float $scale = 2.0, string $color = '#000000', float $pad = 0.0)` | `MathPng` |
| `mathml()` | `string`, `MathmlWriter::write()` with the mode |
| `omml(string $align = 'center', ?RunFormat $format = null)` | `string`: `oMath` inline, `oMathPara` with `$align` in display mode |
| `tex()` | `string`, `TexWriter::write()` |
| `text()` | `string`, `TextWriter::write()` |
| `notes()` | `list<MathNote>`, `Math::notes()` on the tree |
| `node()` | the tree, for the low-level classes |

`svg()` and `png()` take `size` in CSS pixels. For SVG that is the layout size, since a browser reads SVG points as CSS
pixels. `$align` is ignored inline.

#### `svg()`

The output of `SvgRenderer::render()` with three attributes added to the root `<svg>`:

- `role="img"`
- `aria-label`: `$label` if given, otherwise `text()`, escaped for an XML attribute. An empty label (`''`) writes
  `aria-hidden="true"` instead, for a caller that puts MathML beside the SVG.
- `style="vertical-align:-Npx"` with N the descent plus the pad, rounded to three places. Inline mode only: a display
  formula sits on its own line, and the caller places it.

A `<title>` element is not added. `aria-label` alone is what screen readers use, and a title also shows as a tooltip.

#### `png()` and `MathPng`

`png()` throws what `PngRenderer` throws: `MissingExtension` without Imagick, `InvalidArgumentException` for a bad
color or scale, or an image over `MAX_PIXELS`.

`MathPng` is a `final readonly` value:

| Member | Meaning |
|---|---|
| `string $bytes` | the PNG |
| `float $width`, `float $height` | display size in CSS pixels: the pixel size divided by `scale` |
| `float $verticalAlign` | the CSS `vertical-align` in pixels (negative: below the baseline), rounded to one place |
| `dataUri(): string` | `data:image/png;base64,…` |
| `img(string $src, ?string $alt = null): string` | an `<img>` with `width`, `height`, `alt` (default `text()`) and `style="vertical-align:…"` |

`img()` takes the `src` because email wants `cid:` or a hosted URL and a web page may want the data URI. The value
holds the alt text from the formula it was drawn from.

## Defaults differ from the low-level classes

`Math::layout()` defaults to display style at 12 pt, `SvgRenderer` to `#000000`, and `MathmlWriter` to display.
`PendingMath` defaults to inline at 16 px in `currentColor`, because most formulas sit in running text on the web.
The docs state this beside the first example. The low-level defaults do not change.

## Where it goes

- `packages/math/src/PendingMath.php` and `packages/math/src/MathPng.php`, beside `Math`.
- The architecture test keeps `Node` and the layout free of `Render` dependencies. `PendingMath` may depend on
  everything.

## Docs

- `site/src/math/README.md`: open with `PendingMath`.
- `site/src/math/output.md`: a "Quick output" section first; the low-level classes follow as now.
- `site/src/math/recipes.md`: rewrite web, email, accessible and converting recipes on `PendingMath`. The
  `inlineMath()` helper and the email baseline arithmetic go. The LaTeX-look recipe uses `font()`. The untrusted-input
  recipe uses `notes()`.
- CHANGELOG entry.

## Tests

- Every output equals the low-level call it wraps with the matching arguments (byte for byte, before the added
  attributes).
- Mode: `$$…$$`, `\[…\]` and `display="block"` start in display; `inline()` overrides; `of()` starts inline;
  modifiers return new instances and leave the original unchanged.
- `svg()`: the three attributes; the label escaped (`<`, `&`, `"` in `\text{}`); the empty label giving
  `aria-hidden`; no `vertical-align` in display mode; the offset including the pad.
- `png()` (skipped without Imagick): size and offset against `PngRenderer::baselineOffset()`; `img()` markup and
  escaping; `dataUri()`.
- `omml()`: `oMath` inline, `oMathPara` with the alignment in display mode.
- `font()`: the given font reaches the layout and the renderer (a CFF fixture font changes the SVG).

## Out of scope

- A Laravel Blade component or helper over `PendingMath`. The existing equation component covers documents.
- Caching rendered output.
- HTML-with-MathML-fallback markup (SVG plus hidden MathML in one call). The empty label supports building it by hand.
