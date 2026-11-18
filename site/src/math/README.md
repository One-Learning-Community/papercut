# Math

`quoin/math` typesets mathematics in plain PHP. It reads TeX or MathML and writes SVG, PNG, MathML, TeX, Word equations (OMML) or plain text. It needs PHP 8.4 with `ctype`, `dom` and `mbstring`. No Node, no browser, no fonts on the server.

The rest of the monorepo uses it for equations in PDF, Word and HTML documents, but it does not depend on them.

```
composer require quoin/math
```

```php
use Quoin\Math\MathRender;

echo MathRender::fromTex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}')->display()->svg(pad: 1);
```

![The quadratic formula as SVG](/guide/equation-quadratic.png)

Every glyph is a path, so the SVG looks the same everywhere and needs no web font.

## What it does

A formula passes through three stages.

1. **Read.** `Math::tex()` and `Math::mathml()` turn source text into a formula tree. The `Math` builder makes the same tree in PHP.
2. **Lay out.** `Math::layout()` sets the tree in an OpenType math font, by the rules TeX and MathML Core use.
3. **Write.** A writer turns the tree or the layout into output.

`MathRender` runs all three in one call. The classes behind it stay public for finer control.

| Output | From | Use it for |
|---|---|---|
| SVG | the layout | Web pages, any vector pipeline |
| PNG | the layout | Email, chat, anything that won't show SVG (needs ext-imagick) |
| MathML | the tree | Browsers that render MathML themselves, screen readers |
| TeX | the tree | Editing, storage, KaTeX or MathJax on the client |
| OMML | the tree | Word documents, as live equations a reader can edit |
| Plain text | the tree | Alt text, search indexes, plain-text email |

Every reader produces the same tree, so the package also converts: TeX to MathML, MathML to TeX, either to Word.

## Compared with KaTeX and MathJax

KaTeX and MathJax are the standard tools for math on the web, and they are excellent. Both are JavaScript, so a PHP site runs Node beside PHP or renders in the visitor's browser.

`quoin/math` is the PHP-only alternative. It reads the TeX KaTeX reads (gaps listed under [Input](./input.md#tex)), runs in your PHP process and returns a string. It also writes Word equations, which neither JavaScript library does.

Not yet supported: line breaks inside long formulas, color and boxes within a formula, and equation numbers.

## Speed and safety

Once the font is loaded, TeX to SVG takes about 0.1 ms per formula. The first formula in a request takes about 5 ms with opcache, font loading included. Time and memory grow linearly with formula size.

The readers never throw. Input that is too large or too deep is cut off at fixed limits, drawn as far as it got and reported, so formulas typed by users are safe to render. See [Untrusted input](./recipes.md#untrusted-input).

## Where next

- [Recipes](./recipes.md): formulas in a web page, in email, in Word; converting between formats; alt text; untrusted input.
- [Input](./input.md): the builder, TeX and MathML, and what each reader reports.
- [Output](./output.md): the writers and their options.
- [Custom backends](./backends.md): drawing a layout yourself, and other math fonts.
- For equations in documents, see [Equations](/guide/equations.md).
