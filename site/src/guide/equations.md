# Equations

Papercut sets mathematics in the PDF, HTML and Word exports. You build a formula from a few pieces, put it on its own line or inside a line of text, and each export writes it in its own native form. Letters and digits in a PDF equation are real text you can select, and a Word export carries live Word equations that stay editable.

![The quadratic formula, set by Papercut](/guide/equation-quadratic.png)

The formula above is one display equation:

```php
use Papercut\Equation;
use Papercut\Math\Math;

Equation::of('x', '=', Math::frac(
    Math::row('-b', '±', Math::sqrt(Math::sup('b', '2'), '-', '4ac')),
    '2a',
))->center();
```

The builder lives in the `papercut/math` package, which `papercut/core` installs for you.

## Building a formula

`Papercut\Math\Math` has one method for each shape. Wherever a method takes a node, a plain string works too, and is turned into nodes by the [rules below](#how-text-becomes-math).

| Method | Builds |
|---|---|
| `Math::row(...$parts)` | Parts side by side. Strings and nodes mix freely. |
| `Math::frac($numerator, $denominator)` | A fraction. |
| `Math::sqrt(...$content)` | A square root. |
| `Math::root($radicand, $index)` | A root with an index, such as a cube root. |
| `Math::sup($base, $superscript)` | A superscript. |
| `Math::sub($base, $subscript)` | A subscript. |
| `Math::subSup($base, $subscript, $superscript)` | A subscript and a superscript on one base. |
| `Math::fenced($open, $close, ...$content)` | Content between delimiters, such as `(` and `)`. The delimiters stretch to fit what is inside. |
| `Math::text($text)` | Literal text, such as a word in a formula. It is not tokenized. |

```php
Math::subSup('x', 'i', '2');                            // x with i below and 2 above
Math::fenced('(', ')', Math::frac('a', 'b'));           // a tall pair of brackets
Math::row('f', Math::fenced('(', ')', 'x'), '=', Math::root('x', '3'));
Math::row('speed', '=', Math::text('distance'), '/', Math::text('time'));
```

## A block equation

`Equation::of(...$parts)` takes strings and nodes, in the same way as `Math::row()`. It sits on a line of its own, in display style: fractions and scripts are drawn large, and the equation takes the surrounding text's size and colour.

- It uses the theme's `equation` style, which by default is a bottom margin of 6 pt. Change that style, or style the equation itself, like any other box.
- It aligns with text alignment: `->center()` or `->right()` in PHP, `text-align="center"` in Blade. The default is left.
- It never splits across pages.
- If it is wider than its box it overflows, like an overlong word. It does not wrap.

```blade
<x-doc::equation math="E = m c²" />
<x-doc::equation :math="$node" text-align="center" />
```

`math` takes a string, or a node you built with `Math` in PHP and passed to the view.

## An equation in a line of text

`Span::math(...$parts)` puts a formula inside running text. It is set in a compact style, with scripts and fractions at the size of the line, and sits on the line's baseline. The line grows to fit a tall formula, and the formula never breaks across lines.

```php
use Papercut\{Span, Text};
use Papercut\Math\Math;

Text::spans(
    Span::text('The fitted slope was '),
    Span::math(Math::sub('β', '1'), '=', '−0.04'),
    Span::text(' per centimetre.'),
);
```

In Blade, `<x-doc::math>` inside a text tag is the inline form:

```blade
<x-doc::p>
    A circle of radius <x-doc::math math="r" /> has area <x-doc::math math="π r²" />.
</x-doc::p>
```

Outside a text tag, `<x-doc::math>` is the same as `<x-doc::equation>`, a block equation. The other way round is an error: a block `<x-doc::equation>` inside a text tag throws. An inline `<x-doc::math>` takes no style attributes. Set the size and colour on the surrounding text.

## How text becomes math

A string is split into pieces, one per symbol:

- A letter becomes a math italic variable, so `x` is drawn as 𝑥.
- A run of digits, with an optional decimal point, becomes a number: `3.14` is one number.
- These function names are drawn upright: `sin`, `cos`, `tan`, `cot`, `sec`, `csc`, `sinh`, `cosh`, `tanh`, `arcsin`, `arccos`, `arctan`, `log`, `ln`, `exp`, `lim`, `max`, `min` and `det`. So `sin x` is "sin" followed by an italic 𝑥.
- Whitespace is dropped. Spacing around an operator comes from the MathML Core operator dictionary, the same table browsers use, so `a+b` and `a + b` come out the same.
- A leading `−` or `±`, at the start of a formula or after another operator, is a sign and gets no space after it. `Math::row('-b')` is a negative `b`.
- An ASCII hyphen `-` is drawn as a true minus sign, `−`.
- Two vertical bars `|` pair up as opening and closing, so `|x|` is an absolute value.
- Unicode superscript and subscript digits such as `²` and `₂` still work, and are drawn as the characters they are. Use `Math::sup()` and `Math::sub()` when you want real scripts, which are sized and placed correctly.

## The math font

Equations are set in a math font. Papercut bundles STIX Two Math and uses it unless you say otherwise. To use another, point the font set at the file:

```php
use Papercut\Export\PdfOptions;
use Papercut\Text\FontSet;

$fonts = (new FontSet())->withMathFont('/fonts/MyMath-Regular.ttf');
$document->toPdf(new PdfOptions(fonts: $fonts));
```

In Laravel, set `math_font` in `config/papercut.php` to the file's path, or leave it `null` for the bundled font.

The font must be a TrueType math font, with a `MATH` table. A font with CFF outlines, such as Latin Modern Math, is rejected with `Papercut\Math\Exception\UnsupportedFont`, which names the file and the reason. The chosen font applies to the PDF and HTML exports. Word equations always use Cambria Math.

A character the math font has no glyph for, such as a CJK ideograph or an emoji, is drawn with the document's [fallback fonts](./fonts.md#fallback-fonts).

## In each output

**PDF.** Letters and digits are real text in the math font, so they can be selected and searched. Copying them gives the math italic characters (𝑥, not x), because that is what the font's glyphs stand for. Delimiters and radicals that stretch to fit are assembled from glyph pieces drawn as shapes, and fraction bars are rules. In a table of contents and the PDF outline, a heading that holds a formula is listed in a linear text form.

**Word.** Equations are live Word equations (OMML), always in Cambria Math. A block equation is a paragraph of its own with its alignment written out, and an inline one sits among the runs of its line, so a reader can click into it and edit it. See [Word export](./word-export.md#equations-in-word) for what the editors do with them.

**HTML.** Equations are written as MathML Core, `<math display="block">` or `<math display="inline">`, and the browser lays them out. Which math font the browser uses depends on the [font mode](./web-fonts.md): with the default, `none`, it uses its own; `embed` embeds STIX Two Math, `link` links to it, and `sources` embeds it as a `data:` URI. Block equations are placed with margins.

## Not yet

These are not supported yet:

- Big operators with limits above and below, such as `∑` and `∫` with bounds set as under and over scripts.
- Accents and decorations over a symbol, such as a hat or a bar.
- Matrices and other grids.
- Reading MathML as input. This is planned for soon after 1.0.
- Math fonts with CFF outlines.
