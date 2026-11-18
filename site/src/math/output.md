# Output

## Quick output

`MathRender` reads a formula and writes it in one call.

```php
use Quoin\Math\Math;
use Quoin\Math\MathRender;

MathRender::fromTex('\pi r^2')->svg();
MathRender::fromTex($tex)->display()->svg(size: 18);
MathRender::fromMathml($mml)->png(scale: 3);
MathRender::of(Math::frac('a', 'b'))->mathml();
```

| Method | Reads |
|---|---|
| `fromTex($tex)` | TeX, as `Math::tex()` reads it |
| `fromMathml($input)` | MathML, a string or a `math` element of a parsed document |
| `of($node)` | a formula from the builder |

A formula starts in the style its source asked for: `$$…$$` and `\[…\]` in TeX, `display="block"` in MathML. Anything else, and every `of()`, starts inline. These change it, and each returns a new `MathRender`:

| Method | Effect |
|---|---|
| `display()` | Display style, for a formula on its own line |
| `inline()` | Text style, for a formula inside a line |
| `font($font)` | The `Font` SVG and PNG are drawn in; STIX Two Math by default |

| Output | Returns |
|---|---|
| `svg(size: 16, color: 'currentColor', pad: 0, label: null)` | SVG markup with `role="img"` and an `aria-label` of `text()`, or of `label`. An empty `label` writes `aria-hidden="true"` instead. Inline, it also carries the `vertical-align` that sets its baseline on the line's. |
| `png(size: 16, scale: 2, color: '#000000', pad: 0)` | A `MathPng` |
| `mathml()` | MathML, with `display="block"` in display style |
| `omml(align: 'center', format: null)` | Word's `m:oMath` inline; `m:oMathPara` aligned `left`, `center` or `right` in display style |
| `tex()` | TeX |
| `text()` | Plain text |
| `notes()` | The reader's reports; see [Untrusted input](./recipes.md#untrusted-input) |
| `node()` | The formula tree, for the classes below |

`size` is in CSS pixels. `svg()` and `png()` throw `InvalidArgumentException` for a size that is not above zero or a negative pad. `MathPng` holds `bytes`, the CSS `width` and `height` (the image has `scale` pixels to each), `verticalAlign`, which is `null` for a display formula, and `alt`, the formula as text. `dataUri()` returns the image as a `data:` URL, and `img($src, $alt = null)` writes an `<img>` at that size and baseline with the formula's text as `alt`.

The defaults suit text on the web: inline, 16 pixels, `currentColor`. They differ from the classes below, where `Math::layout()` defaults to display style at 12 points, `SvgRenderer` to black and `MathmlWriter` to display.

The rest of this page covers those classes. MathML, TeX, OMML and plain text are written from the formula tree. SVG and PNG are drawn from a layout.

## Layout

```php
$box = Math::layout($formula, size: 12.0, display: true);
```

| Argument | Default | Meaning |
|---|---|---|
| `size` | `12.0` | Font size in points. In a browser, SVG points are CSS pixels. |
| `display` | `true` | Display style, for a formula on its own line: large operators carry their limits above and below, fractions are full size. `false` is text style, for a formula inside a line. |
| `font` | STIX Two Math | A `Font`; see [Custom backends](./backends.md#fonts). |
| `scriptAlternates` | `true` | Use the font's script-size glyph forms. Turn it off when your output can only draw glyphs reachable from a character. |
| `fallbackAdvance` | `null` | A `Closure(string $char, float $size): ?float` that gives the width of a character the font lacks, for a backend that draws such characters in another font. |

The result is a `Box` with `width`, `ascent` (height above the baseline) and `descent` (depth below it), in points.

## SVG

```php
use Quoin\Math\Render\SvgRenderer;

$svg = new SvgRenderer(Math::stixTwoMath(), color: '#1a1a1a', opacity: 1.0);
echo $svg->render($box, pad: 2.0);
```

The SVG is `width + 2 × pad` wide and `ascent + descent + 2 × pad` high. `SvgRenderer::baselineOffset($box, $pad)` is the distance from its top edge to the baseline. Each glyph is one `<path>` whose outline is stored in font units and placed with a `transform="matrix(…)"`; fraction bars and radical rules are `<rect>`s. Pass the same font you laid out with.

## PNG

```php
use Quoin\Math\Render\PngRenderer;

if (PngRenderer::available()) {
    $png = (new PngRenderer(Math::stixTwoMath()))->render($box, pad: 1.0, scale: 2.0);
}
```

`PngRenderer` draws with the Imagick extension. `scale` is pixels per point; the image is `(width + 2 × pad) × scale` pixels wide, with a transparent background. It is drawn at four times that size and scaled down, which keeps strokes as fine as a browser's. `PngRenderer::baselineOffset($box, $pad, $scale)` gives the baseline in pixels. `PngRenderer::pixelSize($box, $pad, $scale)` gives the image's width and height in pixels. Without Imagick, `render()` throws `Quoin\Math\Exception\MissingExtension`. A large image is drawn at a smaller multiple to stay under `PngRenderer::MAX_PIXELS` (8 million); one over it even at its own size throws `InvalidArgumentException`.

The color is an ImageMagick color: `#rrggbb`, `rgb(…)` or a name. `currentColor` means nothing outside a browser, so it works only for SVG.

To rasterize the SVG yourself, use `rsvg-convert`. ImageMagick's SVG reader draws glyphs noticeably bolder.

## MathML

```php
use Quoin\Math\Render\MathmlWriter;

echo MathmlWriter::write($formula, display: false);   // <math xmlns="…" display="inline">…</math>
```

The output is MathML Core, which current browsers render natively.

## TeX

```php
use Quoin\Math\Render\TexWriter;

echo TexWriter::write($formula);   // x = \frac{- b \pm \sqrt{b^2 - 4 a c}}{2 a}
```

Standard TeX with no package-specific commands, so KaTeX and MathJax accept it, and `Math::tex()` reads it back as the same formula.

## Word (OMML)

```php
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\RunFormat;

OmmlWriter::oMath($formula);                                               // inline
OmmlWriter::oMathPara($formula, 'center');                                 // a paragraph of its own: left, center or right
OmmlWriter::oMath($formula, new RunFormat(colorHex: 'C00000', sizeHalfPoints: 24));
```

Letters are written plain and Word italicizes them; `Math::text()` becomes ordinary text. Word always uses Cambria Math. It does not stretch `=` over labels, so `\xlongequal` keeps its normal width. See [In Word](./recipes.md#in-word) for where the XML goes.

## Plain text

```php
use Quoin\Math\Render\TextWriter;

echo TextWriter::write($formula);   // x = (−b ± √(b² − 4ac))/(2a)
```

Linear Unicode with plain letters. Scripts become superscript and subscript characters where Unicode has them, and `^(…)` or `_(…)` where it doesn't. Use it for alt text, search indexes, slugs and plain-text email.
