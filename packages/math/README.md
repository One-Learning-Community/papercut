# quoin/math

Math typesetting in plain PHP. It reads TeX or MathML and writes SVG, PNG, MathML, TeX, Word equations (OMML) or plain
text. It needs PHP 8.4. No Node, no browser, no fonts on the server.

It is part of a [monorepo](https://github.com/One-Learning-Community/quoin) that uses it for equations in
PDF, Word and HTML documents, but it does not depend on the rest of it.

```
composer require quoin/math
```

```php
use Quoin\Math\MathRender;

echo MathRender::fromTex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}')->display()->svg(pad: 1);
```

Every glyph is a path, so the SVG needs no web font. Inline, it carries the `vertical-align` that sets it on the line's
baseline, and every SVG has an `aria-label` of the formula as text. The same call ends in `png()`, `mathml()`,
`omml()`, `tex()` or `text()`.

Chemistry reads the same way. The TeX writer writes `\ce` back as read, so TeX that leaves this package needs mhchem, which KaTeX and MathJax offer.

```php
echo MathRender::fromTex('\ce{CH4 + 2O2 -> CO2 + 2H2O}')->svg();
```

- Reads what KaTeX reads, and MathML from equation editors, Word and the web.
- Reads chemistry (`\ce`, `\pu` from mhchem) as ordinary nodes, so it reaches Word and MathML as real equations.
- Converts TeX to MathML, MathML to TeX, either to Word.
- Never throws on input. Fixed limits keep hostile formulas cheap, and anything not drawn as written is reported.
- About 0.1 ms per formula once the font is loaded.

## Documentation

- [Overview](https://one-learning-community.github.io/quoin/math/), with a comparison to KaTeX and MathJax
- [Recipes](https://one-learning-community.github.io/quoin/math/recipes.html): web pages, email, Word, conversion,
  alt text, untrusted input
- [Input](https://one-learning-community.github.io/quoin/math/input.html): the builder, TeX, MathML, notes and limits
- [Output](https://one-learning-community.github.io/quoin/math/output.html): one-call output, layout options and every writer
- [Custom backends](https://one-learning-community.github.io/quoin/math/backends.html): the display list, glyph
  outlines, fonts

## Testing

```
vendor/bin/phpunit
composer analyse
php tests/render-fixtures.php   # writes tests/out/*.svg, .png (needs rsvg-convert) and index.html
```

`tests/Fixtures.php` holds the sample formulas. A fixture name ending in `-inline` is laid out in text style; all
others use display style.

Spacing, stretching and large-operator behavior come from the W3C MathML Core operator dictionary. The snapshot used
is recorded in the header of `resources/operators.php`, which `bin/gen-operators.php` generates.

## Credits

The layout design follows [ziamath](https://github.com/cdelker/ziamath) (MIT). The bundled STIX Two Math is under the
SIL Open Font License 1.1 (`resources/fonts/OFL.txt`).
