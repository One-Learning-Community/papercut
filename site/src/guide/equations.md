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
Math::row(Math::text('speed'), '=', Math::text('distance'), '/', Math::text('time'));
```

### Limits, accents and bars

Large operators take their limits as optional arguments. `Math::sum()` and `Math::product()` take the lower and upper limit; `Math::bigOp($operator, $under, $over)` does the same for any other operator. In a block equation the limits sit below and above the symbol; in a line of text they move beside it as scripts, so the line doesn't grow. `Math::integral()` always keeps its limits beside the symbol.

```php
Math::row(Math::sum('n = 1', '∞'), Math::frac('1', Math::sup('n', '2')));
Math::row(Math::integral('0', '1'), 'x dx');
```

| Method | Builds |
|---|---|
| `Math::sum($under, $over)`, `Math::product($under, $over)`, `Math::integral($under, $over)` | A large operator with limits. Both limits are optional. |
| `Math::bigOp($operator, $under, $over)` | The same for any operator character. |
| `Math::under($base, $under)`, `Math::over($base, $over)`, `Math::underOver($base, $under, $over)` | Something below, above, or both above and below a base. |
| `Math::accent($base, $mark)` | A mark over a base. `$mark` is a combining character such as U+0302. |
| `Math::hat($base)`, `Math::tilde($base)`, `Math::vec($base)`, `Math::dot($base)` | The common accents. |
| `Math::overline($base)`, `Math::underline($base)` | A rule as wide as the base. |
| `Math::overbrace($base, $label)`, `Math::underbrace($base, $label)` | A brace stretched across the base, with an optional label on its far side. |
| `Math::binom($top, $bottom)` | A binomial coefficient: two lines between parentheses. |
| `Math::stack($top, $bottom)` | Two lines like a fraction, without the rule. |
| `Math::space($em)` | Horizontal space of that many ems. A negative value pulls the next item back. |
| `Math::phantom(...$content)` | Takes the size of its content and draws nothing. |
| `Math::styled($text, $variant)` | Text in a MathML `mathvariant`, such as `bold` or `double-struck`. |

```php
Math::row(Math::overline('x'), '=', Math::frac(Math::sum('i', 'n'), 'n'));
Math::underbrace(Math::row('a + b + c'), 'three terms');
Math::row(Math::binom('n', 'k'), '=', Math::frac('n!', Math::row('k!', '(n − k)!')));
Math::row(Math::styled('R', 'double-struck'), '∋', Math::styled('x', 'bold'));
```

`styled()` accepts `normal`, `bold`, `italic`, `bold-italic`, `double-struck`, `script`, `bold-script`, `fraktur`, `bold-fraktur`, `sans-serif`, `bold-sans-serif`, `sans-serif-italic`, `sans-serif-bold-italic` and `monospace`. An unknown variant, or empty text, throws `InvalidArgumentException`.

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

`math` takes a string, or a node you built with `Math` in PHP and passed to the view. A string that starts with `<math` is read as [MathML](#mathml-input) instead.

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

Outside a text tag, `<x-doc::math>` is the same as `<x-doc::equation>`, a block equation. The other way round is an error: a block `<x-doc::equation>` inside a text tag throws. An inline `<x-doc::math>` takes no style attributes. Set the size and colour on the surrounding text. It can't sit inside a raw inline HTML tag such as `<b>` or `<a>`: Blade hands the tag's two halves to the markup parser separately, so it throws an error naming `<x-doc::math>`. Put the equation beside the tag, not inside it.

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

## MathML input

Formulas often arrive as MathML: from an equation editor, a Word equation, a question bank or a hand-written page. Papercut reads it into the same formula the builder makes, so it is set, exported and edited like any other.

There are three ways in.

**Rich text.** [`Markup::html()`](./rich-text.md) finds formulas in the HTML it converts. A `<math display="block">` becomes an equation on its own line, in display style. Any other `<math>` is a formula inside the line, in text style. Word's `<mml:math>` prefix works too. MathType and Wiris are read in both of the ways they store a formula:

- `<img class="Wirisformula" data-mathml="…">` is read as a formula, from the MathML in the `data-mathml` attribute. The picture itself is ignored.
- The text form, `«math…«/math»`, is read as a formula wherever it appears in a text run. Inside `<code>` or `<pre>` it stays literal text.

```php
use Papercut\Markup\Markup;

Markup::html('<p>The mean is <math><mover><mi>x</mi><mo>¯</mo></mover></math>.</p>
    <math display="block"><mfrac><mn>1</mn><mn>2</mn></mfrac></math>');
```

The formulas in the editors' output were tested against MathType and Wiris (markup captured from its own test suite), Word-style `mml:` markup, MathLive, and the output of CKEditor and TinyMCE with MathType. The MathLive, CKEditor, TinyMCE and Word cases are reproduced from the editors' documentation and source, not captured from a running editor.

**In PHP.** `Math::mathml($string)` returns a node you can use anywhere a node goes: in `Equation::of()`, `Span::math()` or `Math::row()`. It takes a string or a `Dom\Element`, never throws, and reads MathML with or without the `mml:` prefix and in the Wiris text form.

```php
use Papercut\Equation;
use Papercut\Math\Math;

Equation::of(Math::mathml('<math><msqrt><mi>x</mi></msqrt></math>'))->center();
```

**In Blade.** A `math` string that starts with `<math`, `<mml:math` or `«math` is MathML:

```blade
<x-doc::equation math='<math><mfrac><mn>1</mn><mn>2</mn></mfrac></math>' />
<x-doc::p>Half is <x-doc::math :math="$mathml" />.</x-doc::p>
```

A literal attribute is entity-decoded by Blade before Papercut sees it, so `&lt;` in MathML arrives as a bare `<`, which can change what the formula means. A formula with entities such as `&lt;`, `&amp;` or `&nbsp;` is safer passed bound, as `:math="$mathml"`. The string then reaches Papercut untouched.

### What a formula takes from its text

A formula read from MathML takes its colour, size, background and link from the text around it. It is never underlined, struck through, bold or italic because the text is: letters are math italic and digits upright, as in any other equation. The colour, size and background attributes in the MathML itself (`mathcolor`, `mathsize`, `mathbackground`) are ignored.

### What is approximated or dropped

Not all of MathML has a counterpart yet. The reader draws what it can and records a note, once per element name in a formula, so three `<menclose>` give one note.

- **Approximated.** The content is drawn, but not as the source meant. Matrices and other tables (`<mtable>`) are drawn as rows of cells for now, without alignment or lines. `<menclose>`, `<mmultiscripts>`, `<maction>` and elements the reader doesn't know are drawn as their contents.
- **Dropped.** The content is not drawn. This covers images and other media inside a formula, and content an HTML tag pushed out of a `<math>` element when the markup isn't well-formed XML.

Notes reach the [`onWarning`](./rich-text.md#conversion-warnings) callback as the document is exported. A formula with approximated parts exports normally in strict mode. One with dropped parts makes strict mode fail, like any other content an output can't draw.

```php
use Papercut\Export\PdfOptions;

$document->toPdf(new PdfOptions(onWarning: fn (string $message) => error_log($message)));
// pdf: approximated math <menclose> (drawn as its contents) at …
```

To read the notes yourself, `Math::notes($node)` returns them as a list of `MathNote`, each with a `level`, the `element` name, a `reason` and where it was found. The `papercut/math` package's README describes the reader in full.

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

- Matrices and other grids drawn as grids. MathML tables are read as rows of cells.
- Math fonts with CFF outlines.
