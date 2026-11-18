# Recipes

Each recipe starts from TeX. `MathRender::fromMathml()` or `MathRender::of()` with a builder formula can take the place of `MathRender::fromTex()`.

## In a web page

Use SVG on the web. It stays sharp at any zoom and needs no math font.

```php
use Quoin\Math\MathRender;

echo '<p>The area of a circle is ' . MathRender::fromTex('\pi r^2')->svg() . '.</p>';

echo '<p class="equation">'
    . MathRender::fromTex('\sum_{n=1}^\infty \frac{1}{n^2} = \frac{\pi^2}{6}')->display()->svg(size: 18, pad: 2)
    . '</p>';
```

`size` is the pixel size of the surrounding text. The color is `currentColor`, the parent's text color, so the formula follows a dark theme; pass `color:` for any other CSS color.

Inline, the formula is in text style, which keeps fractions and limits small, and the SVG carries the `vertical-align` that sets its baseline on the line's. A display formula sits on its own line and carries none.

## The LaTeX look

The look of a LaTeX document is Latin Modern Math. It is not bundled; download `latinmodern-math.otf` from CTAN or the GUST site (GUST Font License). Its outlines are CFF, which the math package reads like any other.

```php
use Quoin\Math\Font\Font;
use Quoin\Math\MathRender;

$font = Font::fromFile('/fonts/latinmodern-math.otf');

echo MathRender::fromTex('\int_0^\infty e^{-x^2}\,dx = \frac{\sqrt{\pi}}{2}')->display()->font($font)->svg(size: 18, pad: 2);
```

Load the font once and reuse the object. SVG, PNG and MathML output carry no font, so the file costs nothing there. In a PDF the font is embedded whole, which adds about 390 KB; see [Fonts](/guide/fonts.md#the-math-font).

Other math fonts with CFF outlines load the same way: New Computer Modern Math, the TeX Gyre math fonts, Fira Math, Libertinus Math and XITS Math. In a document, point the font set at the file with `withMathFont()`, or set `math_font` in Laravel.

## Accessible formulas

Screen readers can't read paths. `svg()` gives the SVG `role="img"` and an `aria-label` of the formula as linear text:

```php
echo MathRender::fromTex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}')->svg();
// <svg role="img" aria-label="x = (−b ± √(b² − 4ac))/(2a)" …>
```

Pass `label:` to write your own. To give screen readers MathML instead, pass an empty label, which marks the SVG `aria-hidden="true"`, and put the MathML beside it, visually hidden:

```php
$formula = MathRender::fromTex('a^2 + b^2 = c^2');

echo $formula->svg(label: '') . '<span class="visually-hidden">' . $formula->mathml() . '</span>';
```

## In email

Gmail and Outlook do not display SVG. Send PNG. It needs the Imagick extension.

```php
$png = MathRender::fromTex('E = mc^2')->png();

echo $png->img('cid:formula-1');
```

Attach `$png->bytes` to the message as an inline image with the content ID `formula-1`.

`png()` draws two image pixels to each CSS pixel, which stays sharp on high-density screens. `img()` writes the `<img>` at its CSS size, with the formula's text as `alt` and the `vertical-align` that sets it on the baseline. The same values are on the result as `width`, `height` and `verticalAlign`.

Many email clients block `data:` images, so attach the PNG and reference it by `cid:`, or host it. `dataUri()` serves pages that accept them.

## In Word

`omml()` writes Office Math, the format of Word's equation editor, so readers get live equations they can edit.

```php
$inline = MathRender::fromTex('a^2 + b^2 = c^2')->omml();                          // goes between runs in a paragraph
$block = MathRender::fromTex('\int_0^1 x\,dx = \tfrac12')->display()->omml();      // fills a paragraph of its own
```

The output uses the `m:` and `w:` prefixes without declaring them, so the document root must declare `xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"` beside the usual `w:`. Put display output in a `<w:p>` of its own; it is centered unless you pass `'left'` or `'right'`. Word always sets equations in Cambria Math.

## Converting between formats

Every reader returns the same tree, and every writer takes it.

```php
$mathml = MathRender::fromTex('\sqrt{x^2 + 1}')->mathml();
$tex = MathRender::fromMathml('<math><mfrac><mi>a</mi><mi>b</mi></mfrac></math>')->tex();   // \frac{a}{b}
```

TeX from `tex()` reads back as the same formula. MathML details TeX can't express, such as an unusual `mathvariant`, become the nearest TeX.

## Untrusted input

User input can be malformed, huge or hostile. The readers are built for it.

- They never throw. Bad input still gives a formula, drawn as far as it could be read.
- Limits keep the cost fixed. TeX is cut off at 16 KB, 256 levels of nesting, 10,000 macro expansions and 10,000 table cells. MathML is cut off at 1 MB, 16,384 characters of text and 32,768 elements. Stretched delimiters stop at 100 em.
- Everything cut, dropped or drawn differently is reported.

`notes()` returns the reports:

```php
use Quoin\Math\NoteLevel;

$formula = MathRender::fromTex($userInput);

foreach ($formula->notes() as $note) {
    if ($note->level === NoteLevel::Dropped) {
        // Part of the formula was not drawn. Tell the author.
    }
}
```

A formula at the limits is still large; its SVG can run to megabytes. If your formulas are short, cap input well below 16 KB.
