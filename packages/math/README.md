# papercut/math

Math layout for PHP: fractions, roots, scripts and stretchy delimiters laid out from an OpenType MATH font, with SVG,
MathML and Word OMML writers. It needs PHP 8.4 and nothing else. Part of the
[Papercut](https://github.com/One-Learning-Community/papercut) monorepo, but it does not depend on Papercut.

```
composer require papercut/math
```

## Build a formula

Wherever a node is expected, a plain string works too.

```php
use Papercut\Math\Math;

$formula = Math::row('x =', Math::frac(
    Math::row('−b ±', Math::sqrt(Math::sup('b', '2'), '− 4ac')),
    '2a',
));
```

Builder methods: `row`, `frac`, `sqrt`, `root(radicand, index)`, `sup`, `sub`, `subSup`, `fenced(open, close, ...)`
and `text` (upright running text, for words such as `Math::text('mass')`).

Strings are tokenized: letters are italic identifiers, digit runs are numbers, known function names (`sin`, `log`, ...)
are upright, a leading minus is a prefix operator, whitespace is dropped (spacing comes from the operator dictionary),
and Unicode script digits such as `²` stay attached to the letter before them. Use `−` or `-` for minus; `-` is mapped
to U+2212.

## Lay it out

```php
$box = Math::layout($formula, size: 12.0, display: true);
```

`Math::layout(Node $root, float $size = 12.0, bool $display = true, ?TrueTypeFont $font = null, bool $scriptAlternates = true): Box`

- `$display` true is display style (block equation), false is text style (inline).
- `$font` defaults to the bundled STIX Two Math.
- `$scriptAlternates` false keeps scripts on the ordinary glyphs instead of the font's `ssty` script-size forms. Use it
  when your output can only draw glyphs reachable from a code point.
- `$fallbackAdvance` is an optional `Closure(string $char, float $size): ?float`. It is asked for the advance, in points,
  of each character the font has no glyph for (its `gid` is 0). A float replaces the font's `.notdef` advance, so a
  backend that draws such characters in another font can space them by that font's width; `null` keeps `.notdef`.

### The display list

`Box` has `width`, `ascent` (height above the baseline), `descent` (depth below it) and `items`. All numbers are
points. The origin is the baseline at the left edge, and y points down, so items above the baseline have negative y.

- `Glyph(gid, ?text, x, y, size)`: a glyph by its origin `(x, y)` and font size. `text` is the character drawn, for
  selectable text: a letter set in mathematical italic is `𝑥` (U+1D465), not the `x` that was typed. It is `null` for size
  variants and assembly pieces of a stretched symbol; those have no code point, so draw them by `gid`. For `ssty` script
  alternates `text` is the base character, but `gid` differs from the font's cmap entry for it. So a backend may set a
  glyph as text only when `text` is one code point and the font's cmap maps it to `gid`; draw every other glyph by `gid`.
- `Rule(x, y, w, h)`: a filled rectangle (fraction bar, radical overbar), `(x, y)` its top-left corner.

## Writers

SVG, from the display list (units are points; 1 user unit = 1 pt):

```php
use Papercut\Math\Render\SvgRenderer;

$svg = new SvgRenderer(Math::stixTwoMath());   // optional second argument: fill colour
echo $svg->render($box, pad: 2.0);              // baseline is SvgRenderer::baselineOffset($box, 2.0) from the top
echo $svg->glyphPaths($glyphs, $box);           // only the given glyphs, as paths
```

`glyphPaths(list<Glyph> $glyphs, Box $box)` returns a standalone `<svg>` whose `viewBox` is `0 -ascent width
ascent+descent` (the box's own coordinate space, baseline at y = 0, y down) with width and height in pt. Put it at the
box's top-left and the glyphs land where the layout put them. It is meant for the glyphs a backend cannot set as text: those without text, and those whose text does not map to their `gid`.

MathML:

```php
use Papercut\Math\Render\MathmlWriter;

echo MathmlWriter::write($formula, display: true);
```

Word OMML, always in Cambria Math (the configured font does not apply), with letters written as plain letters (Word
italicizes them) and `Math::text` as `m:nor` runs:

```php
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\RunFormat;

echo OmmlWriter::oMath($formula);                                         // inline, goes between runs
echo OmmlWriter::oMathPara($formula, 'center');                           // block; jc is left, center or right
echo OmmlWriter::oMath($formula, new RunFormat(colorHex: 'C00000', sizeHalfPoints: 24));
```

`oMathPara` output goes inside a `<w:p>` that holds nothing else. Empty slots (`Math::frac('', '2')`) lay out and write
empty containers.

## Fonts

STIX Two Math is bundled and is the default. Another font loads with `TrueTypeFont::fromFile($path)` and goes to
`Math::layout(..., font: $font)` and `new SvgRenderer($font)`. Only TrueType (`glyf`) math fonts are read for now. A
CFF-flavoured font, or a font without a `MATH` table, throws `Papercut\Math\Exception\UnsupportedFont` carrying the
path and the reason.

## Operator dictionary

Spacing, stretching and large-operator behaviour come from the W3C MathML Core operator dictionary. The snapshot used
is recorded in the header of `resources/operators.php`, which `bin/gen-operators.php` generates.

## Testing

```
vendor/bin/phpunit
composer analyse
php tests/render-fixtures.php   # writes tests/out/*.svg, .png (needs rsvg-convert) and index.html
```

`tests/Fixtures.php` holds the sample formulas. A fixture name ending in `-inline` is laid out in text style; all
others use display style.

## Credits

The layout design follows [ziamath](https://github.com/cdelker/ziamath) (MIT). The bundled STIX Two Math is under the
SIL Open Font License 1.1 (`resources/fonts/OFL.txt`).
