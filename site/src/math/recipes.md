# Recipes

Each recipe starts from TeX. `Math::mathml()` or the builder can take the place of `Math::tex()`.

## In a web page

Use SVG on the web. It stays sharp at any zoom and needs no math font. A browser reads its units as CSS pixels, so lay the formula out at the pixel size of the surrounding text.

```php
use Papercut\Math\Math;
use Papercut\Math\Render\SvgRenderer;

$svg = new SvgRenderer(Math::stixTwoMath(), 'currentColor');

// A formula on its own line, in display style.
$box = Math::layout(Math::tex('\sum_{n=1}^\infty \frac{1}{n^2} = \frac{\pi^2}{6}'), size: 18.0, display: true);
echo '<p class="equation">' . $svg->render($box, pad: 2.0) . '</p>';
```

`'currentColor'` takes the parent's text colour, so the formula follows a dark theme. Any CSS colour works.

Inside a line of text, use text style, which keeps fractions and limits small. The SVG's bottom edge sits the formula's descent below its baseline; shift it down by that much:

```php
function inlineMath(SvgRenderer $svg, string $tex, float $px = 16.0): string
{
    $box = Math::layout(Math::tex($tex), size: $px, display: false);
    $drop = round($box->descent, 3);

    return '<span style="display:inline-block;vertical-align:-' . $drop . 'px">' . $svg->render($box) . '</span>';
}

echo '<p>The area of a circle is ' . inlineMath($svg, '\pi r^2') . '.</p>';
```

With a `pad`, add it to the shift: `$box->descent + $pad`.

## Accessible formulas

Screen readers can't read paths. Label the SVG with the linear text from `TextWriter`:

```php
use Papercut\Math\Render\TextWriter;

$formula = Math::tex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}');
$label = htmlspecialchars(TextWriter::write($formula));   // x = (−b ± √(b² − 4ac))/(2a)

echo '<span role="img" aria-label="' . $label . '">' . $svg->render(Math::layout($formula)) . '</span>';
```

Or put `MathmlWriter::write($formula, display: false)` beside the SVG, visually hidden, and mark the SVG `aria-hidden="true"`.

## In email

Gmail and Outlook do not display SVG. Send PNG. `PngRenderer` needs the Imagick extension.

```php
use Papercut\Math\Render\PngRenderer;

$box = Math::layout(Math::tex('E = mc^2'), size: 16.0, display: false);
$png = (new PngRenderer(Math::stixTwoMath()))->render($box, scale: 2.0);
```

At `scale: 2.0` the image has two pixels per point, which stays sharp on high-density screens. Show it at half its pixel size:

```php
[$pw, $ph] = getimagesizefromstring($png);
$w = $pw / 2;
$h = $ph / 2;
$drop = round($h - PngRenderer::baselineOffset($box, scale: 2.0) / 2, 1);
// <img src="cid:formula-1" width="$w" height="$h" alt="…" style="vertical-align:-{$drop}px">
```

Many email clients block `data:` images: attach the PNG and reference it by `cid:`, or host it. Use `TextWriter` for the `alt` text.

## In Word

`OmmlWriter` writes Office Math, the format of Word's equation editor, so readers get live equations they can edit.

```php
use Papercut\Math\Render\OmmlWriter;

$inline = OmmlWriter::oMath(Math::tex('a^2 + b^2 = c^2'));                   // goes between runs in a paragraph
$block = OmmlWriter::oMathPara(Math::tex('\int_0^1 x\,dx = \tfrac12'), 'center'); // fills a paragraph of its own
```

The output uses the `m:` and `w:` prefixes without declaring them, so the document root must declare `xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"` beside the usual `w:`. Put `oMathPara` output in a `<w:p>` of its own. Word always sets equations in Cambria Math.

## Converting between formats

Every reader returns the same tree, and every writer takes it.

```php
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\TexWriter;

$mathml = MathmlWriter::write(Math::tex('\sqrt{x^2 + 1}'), display: false);
$tex = TexWriter::write(Math::mathml('<math><mfrac><mi>a</mi><mi>b</mi></mfrac></math>'));   // \frac{a}{b}
```

TeX from `TexWriter` reads back as the same formula. MathML details TeX can't express, such as an unusual `mathvariant`, become the nearest TeX.

## Untrusted input

User input can be malformed, huge or hostile. The readers are built for it.

- They never throw. Bad input still gives a formula, drawn as far as it could be read.
- Limits keep the cost fixed. TeX is cut off at 16 KB, 256 levels of nesting, 10,000 macro expansions and 10,000 table cells. MathML is cut off at 1 MB, 16,384 characters of text and 32,768 elements. Stretched delimiters stop at 100 em.
- Everything cut, dropped or drawn differently is reported.

The reader classes return the reports:

```php
use Papercut\Math\NoteLevel;
use Papercut\Math\Tex\TexReader;

$result = TexReader::read($userInput);

foreach ($result->notes() as $note) {
    if ($note->level === NoteLevel::Dropped) {
        // Part of the formula was not drawn. Tell the author.
    }
}
```

A formula at the limits is still large; its SVG can run to megabytes. If your formulas are short, cap input well below 16 KB.
