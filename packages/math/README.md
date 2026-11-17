# papercut/math

Math typesetting in plain PHP. It reads TeX or MathML and writes SVG, PNG, MathML, TeX, Word equations (OMML) or plain
text. It needs PHP 8.4. No Node, no browser, no fonts on the server.

It is part of the [Papercut](https://github.com/One-Learning-Community/papercut) monorepo, which uses it for equations in
PDF, Word and HTML documents, but it does not depend on Papercut.

```
composer require papercut/math
```

```php
use Papercut\Math\Math;
use Papercut\Math\Render\SvgRenderer;

$box = Math::layout(Math::tex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}'), size: 16.0);
echo (new SvgRenderer(Math::stixTwoMath()))->render($box, pad: 1.0);
```

Every glyph is a path, so the SVG needs no web font.

- Reads what KaTeX reads, and MathML from equation editors, Word and the web.
- Converts TeX to MathML, MathML to TeX, either to Word.
- Never throws on input. Fixed limits keep hostile formulas cheap, and anything not drawn as written is reported.
- About 0.1 ms per formula once the font is loaded.

## Documentation

- [Overview](https://one-learning-community.github.io/papercut/math/), with a comparison to KaTeX and MathJax
- [Recipes](https://one-learning-community.github.io/papercut/math/recipes.html): web pages, email, Word, conversion,
  alt text, untrusted input
- [Input](https://one-learning-community.github.io/papercut/math/input.html): the builder, TeX, MathML, notes and limits
- [Output](https://one-learning-community.github.io/papercut/math/output.html): layout options and every writer
- [Custom backends](https://one-learning-community.github.io/papercut/math/backends.html): the display list, glyph
  outlines, fonts

## Testing

```
vendor/bin/phpunit
composer analyse
php tests/render-fixtures.php   # writes tests/out/*.svg, .png (needs rsvg-convert) and index.html
```

`tests/Fixtures.php` holds the sample formulas. A fixture name ending in `-inline` is laid out in text style; all
others use display style.

Spacing, stretching and large-operator behaviour come from the W3C MathML Core operator dictionary. The snapshot used
is recorded in the header of `resources/operators.php`, which `bin/gen-operators.php` generates.

## Credits

The layout design follows [ziamath](https://github.com/cdelker/ziamath) (MIT). The bundled STIX Two Math is under the
SIL Open Font License 1.1 (`resources/fonts/OFL.txt`).
