# Custom backends

The SVG and PNG writers are short loops over a public display list. Any API that can fill a path can draw a formula the same way.

## The display list

`Math::layout()` returns a `Box`. Its numbers are points. The origin is the left end of the baseline, and y points down, so anything above the baseline has a negative y.

Walk it with `each()`, which hands you every item with the offset of the box it sits in:

```php
use Quoin\Math\Layout\DisplayItem;
use Quoin\Math\Layout\Glyph;
use Quoin\Math\Layout\Rule;

$box->each(function (DisplayItem $item, float $dx, float $dy): void {
    // the item's position on the page is ($dx + $item->x, $dy + $item->y)
});
```

There are two kinds of item.

- **`Rule`**: a filled rectangle, such as a fraction bar or the top of a radical. `x` and `y` are its top-left corner, `w` and `h` its size.
- **`Glyph`**: one glyph of the math font. `gid` is the glyph id, `(x, y)` its origin on the baseline, `size` the font size. `sx` and `sy` stretch the outline about the origin; they are 1.0 except on a stretched delimiter or arrow, where one straight piece is scaled to cover a long run. Ignore them and a tall bracket comes out short.

`$box->items` gives the same items as a flat list with absolute positions. It is built on every read, so read it once, or prefer `each()`.

## Drawing a glyph

`Font::outlinePath($gid)` returns the glyph's outline as path data: absolute `M`, `L`, `Q`, `C` and `Z` commands in font units, with y pointing up, cached per glyph. An empty glyph, such as a space, gives an empty string.

To place it, scale by `size / unitsPerEm`, multiply by `sx` and `sy`, and flip y:

```php
$font = Math::stixTwoMath();

$box->each(function (DisplayItem $item, float $dx, float $dy) use ($font, $canvas): void {
    if ($item instanceof Rule) {
        $canvas->fillRect($dx + $item->x, $dy + $item->y, $item->w, $item->h);

        return;
    }
    if (! $item instanceof Glyph) {
        return;
    }
    $k = $item->size / $font->unitsPerEm;
    $x = static fn (float $u): float => $dx + $item->x + $u * $k * $item->sx;
    $y = static fn (float $v): float => $dy + $item->y - $v * $k * $item->sy;

    preg_match_all('/([MLQCZ])([^MLQCZ]*)/', $font->outlinePath($item->gid), $commands, PREG_SET_ORDER);
    foreach ($commands as [, $op, $args]) {
        $n = array_map('floatval', preg_split('/ /', trim($args), -1, PREG_SPLIT_NO_EMPTY));
        match ($op) {
            'M' => $canvas->moveTo($x($n[0]), $y($n[1])),
            'L' => $canvas->lineTo($x($n[0]), $y($n[1])),
            'Q' => $canvas->quadTo($x($n[0]), $y($n[1]), $x($n[2]), $y($n[3])),
            'C' => $canvas->curveTo($x($n[0]), $y($n[1]), $x($n[2]), $y($n[3]), $x($n[4]), $y($n[5])),
            'Z' => $canvas->closePath(),
        };
    }
    $canvas->fill();   // nonzero winding
});
```

`$canvas` stands for your drawing API. Cache the parsed commands per glyph if you draw many formulas. `Q` is a quadratic curve and `C` a cubic one. The only curve a TrueType font writes is `Q`, and the only curve a font with CFF outlines writes is `C`, so a backend that accepts any math font handles both. If your API has only cubic curves, a quadratic from `p0` through control point `q` to `p1` is the cubic with control points `p0 + ⅔(q − p0)` and `p1 + ⅔(q − p1)`.

## Glyphs as text

A PDF backend can set ordinary letters as real text, so they can be selected and searched, as the PDF export does. `Glyph::$text` holds the character a glyph stands for. Set a glyph as text only when `text` is a single code point and the font maps that code point to `gid` (`$font->gid($codePoint) === $glyph->gid`), and `sx` and `sy` are 1.0. Draw every other glyph as an outline: stretched pieces have no character, and script-size forms share a character with the full-size glyph.

`SvgRenderer::glyphPaths($glyphs, $box)` draws just the glyphs you pass, as an SVG in the box's own coordinates (baseline at y = 0), for a backend that sets text itself and only needs the rest as shapes.

## Fonts

STIX Two Math is bundled and is the default. Load another math font with `Font::fromFile($path)` and pass it to both `Math::layout(..., font: $font)` and the writer.

The font needs an OpenType `MATH` table. Its outlines may be TrueType or CFF, so Latin Modern Math and the other `.otf` math fonts load the same way as a `.ttf`. The reader refuses CFF2 (variable) fonts, CID-keyed CFF fonts, a CFF font whose `FontMatrix` disagrees with its units per em, font collections, and fonts without a `MATH` table. A file that is not a font at all says so. A font that can't be used throws `Quoin\Math\Exception\UnsupportedFont`, which names the file and the reason.

`TrueTypeFont` is the old name of `Font`. It remains as a deprecated alias until 1.0.

A character the font has no glyph for is laid out with the font's `.notdef` glyph. If your backend draws such characters in another font, pass `fallbackAdvance` to `Math::layout()` so the layout leaves room for them.
