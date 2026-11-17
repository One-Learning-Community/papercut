# papercut/math

Math layout for PHP: fractions, roots, scripts and stretchy delimiters laid out from an OpenType MATH font, with SVG,
MathML, TeX and Word OMML writers. It reads MathML and TeX, so a formula can go from MathML to TeX or from TeX to MathML
and Word. It needs PHP 8.4 and nothing else. Part of the
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
and `text` (upright running text, for words such as `Math::text('mass')`). The rest are listed under
[More builder methods](#more-builder-methods).

Strings are tokenized: letters are italic identifiers, digit runs are numbers, known function names (`sin`, `log`, ...)
are upright, a leading minus is a prefix operator, whitespace is dropped (spacing comes from the operator dictionary),
and Unicode script digits such as `²` stay attached to the letter before them. Use `−` or `-` for minus; `-` is mapped
to U+2212.

### More builder methods

Large operators take their limits as optional arguments. `sum`, `product` and `bigOp(op, under, over)` put the limits
above and below in display style (beside the symbol as scripts in text style); `integral` always keeps them beside it.

```php
$series = Math::row(Math::sum('n = 1', '∞'), Math::frac('1', Math::sup('n', '2')));
$area = Math::row(Math::integral('0', '1'), 'x dx');
```

Parts above and below a base: `under`, `over` and `underOver(base, under, over)`. Accents are `accent(base, mark)` (the
mark is a combining character such as U+0302), with `hat`, `tilde`, `vec` and `dot` for the common ones.

```php
$mean = Math::row(Math::overline('x'), '=', Math::frac(Math::sum('i', 'n'), 'n'));
$unit = Math::hat('v');
$arrow = Math::vec('F');
```

`overline` and `underline` draw a rule as wide as the base. `overbrace` and `underbrace` stretch a brace over or under
the base, with an optional label on the far side of the brace.

```php
$total = Math::underbrace(Math::row('a + b + c'), 'three terms');
```

`binom(top, bottom)` is a binomial coefficient and `stack(top, bottom)` a fraction without a rule.

`matrix(rows, fence, align)` lays rows of cells out in aligned columns inside a stretching fence (`paren`, `bracket`,
`brace`, `bar`, `doublebar` or `none`, with `center`, `left` or `right` alignment for every column or per column).
`cases(rows)` is a left brace around left-aligned columns. Both throw `InvalidArgumentException` for no cells.

```php
$rotation = Math::matrix([['cos θ', '−sin θ'], ['sin θ', 'cos θ']], 'bracket');
$abs = Math::row('|x|', '=', Math::cases([['x', Math::text('if x ≥ 0')], ['−x', Math::text('otherwise')]]));
```

```php
$choose = Math::row(Math::binom('n', 'k'), '=', Math::frac('n!', Math::row('k!', Math::fenced('(', ')', 'n − k'), '!')));
```

`styled(text, variant)` sets text in a MathML `mathvariant`: `normal`, `bold`, `italic`, `bold-italic`,
`double-struck`, `script`, `bold-script`, `fraktur`, `bold-fraktur`, `sans-serif`, `bold-sans-serif`,
`sans-serif-italic`, `sans-serif-bold-italic` or `monospace`. An unknown variant or empty text throws
`InvalidArgumentException`.

```php
$field = Math::row(Math::styled('R', 'double-struck'), '∋', Math::styled('x', 'bold'));
```

`aligned(rows)` (a native equation array in Word) lines equations up on their alignment points, as TeX's `aligned` does: cells alternate right- and
left-aligned, with no gap inside a pair and two em between pairs. `middle(delimiter)` is a delimiter inside `fenced`
that stretches with it (TeX's `\middle`). `big(delimiter, size)` draws a delimiter at a fixed size from 1 to 4
(`Math::BIG_SIZES` lists the sizes in em). `xarrow(arrow, over, under)` is an extensible arrow with optional labels
above and below. It has a minimum length and reaches past its labels.

```php
$steps = Math::aligned([['x + 2', '= 5'], ['x', '= 3']]);
$set = Math::fenced('{', '}', 'x', Math::middle('|'), 'x > 0');
$bracket = Math::row(Math::big('(', 2), 'a + b', Math::big(')', 2));
$reaction = Math::xarrow('→', Math::text('heat'), Math::text('catalyst'));
```

`space(em)` adds horizontal space of that many ems (negative pulls the next item back). `phantom(...)` takes the size of
its content and draws nothing.

```php
$spaced = Math::row('a', Math::space(1.0), 'b', Math::phantom('c'));
```

## Reading MathML

Formulas often arrive as MathML: from an equation editor, a Word export or a hand-written page. `Math::mathml()`
reads it into the same node tree the builder makes, so it lays out and writes like any other formula.

```php
$formula = Math::mathml('<math display="block"><mfrac><mn>1</mn><mn>2</mn></mfrac></math>');
$box = Math::layout($formula);
```

`Math::mathml(string|\Dom\Element $input): Node` takes a string, or a `math` element from a document you have already
parsed. When you also need to know whether the source asked for display style, or what was lost, use the reader
directly:

```php
use Papercut\Math\Mathml\MathmlReader;

$result = MathmlReader::read('<math display="block"><msqrt><mi>x</mi></msqrt></math>');
$result->node;      // the formula
$result->display;   // true for display="block", false otherwise
$result->notes();   // list<MathNote>, see below
```

What a string may look like:

- Well-formed XML is read as XML. Anything else is read with the HTML5 rules, so entities such as `&nbsp;` and
  `&ApplyFunction;` work and the `math` element may sit inside other markup.
- The `mml:` prefix that Word writes works, and so does no prefix.
- Annotations, such as a TeX copy of the formula, are ignored.
- The safeXml form that Wiris and MathType produce (`«math…«/math»`, with `«`, `»`, `¨`, `§` and a backtick standing
  in for `<`, `>`, `"`, `&` and `'`) is accepted. Apps that find such text in their own content can use
  `Papercut\Math\Mathml\SafeXml::decode($text)` to turn it into MathML and `SafeXml::find($text)` to locate each
  `«math…«/math»` run (byte offset and length) in a larger text.

A bare fragment without a `math` element (`<mi>x</mi><mo>+</mo><mn>1</mn>`) is read as if wrapped in one, and empty
input gives an empty formula. The reader never throws. A string is cut off at `MathmlReader::MAX_INPUT` (1 MB), and a formula keeps at
most `MAX_TEXT` (16,384) characters of text and `MAX_ELEMENTS` (32,768) elements; past a limit the rest is dropped with
an approximated note.

### What is approximated or dropped

Not everything in MathML has a counterpart yet. The reader draws what it can and records a note for each kind of
element it changed, once per element name and reason in a formula (three `menclose` give one note).
`Math::notes(Node $root): list<MathNote>` returns them in reading order (the same as `$result->notes()`).

```php
use Papercut\Math\Math;
use Papercut\Math\NoteLevel;

$formula = Math::mathml('<math><menclose notation="box"><mi>x</mi></menclose><mi>y</mi></math>');

foreach (Math::notes($formula) as $note) {
    echo $note->level === NoteLevel::Dropped ? 'dropped' : 'approximated', ': ', $note->element, ' - ', $note->reason, "\n";
}
```

A `MathNote` has `level`, `element` (the MathML element name), `reason` (plain words) and `where` (the path to it in the
formula).

- `NoteLevel::Approximated`: the content is drawn, but not the way the source meant. This covers `mtable` attributes
  that are not drawn (`rowlines`, `columnlines`, `frame`, `rowspacing`, `columnwidth`, `equalrows`,
  `equalcolumns`, `align`, `groupalign`, `columnalign` and `rowalign` on `mtr`, `rowspan`, `columnspan`, `columnalign`
  and `rowalign` on `mtd`; a plain table is drawn as a grid and gives no note, and `columnspacing` is drawn, noted only when its value can't be read), `menclose`, `mmultiscripts`, `maction`
  (its first choice is drawn) and elements the reader does not know.
- `NoteLevel::Dropped`: the content is not drawn. This covers images and other media inside the math (`img`, `svg`,
  `video`, `mglyph` and the like), and content that an HTML tag pushed out of the `math` element: in HTML5 parsing a
  stray tag such as `<p>` inside math ends it, and what follows is lost.

## Reading TeX

Most formulas in the wild are TeX. `Math::tex()` reads math-mode LaTeX into the same node tree the builder makes.

```php
$formula = Math::tex('x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}');
$box = Math::layout($formula);
```

Write TeX in single-quoted PHP strings or a nowdoc (`<<<'TEX'`), never in double quotes: PHP would turn `\f`, `\n`,
`\t`, `\v`, `\r` and `\e` into control characters and `\frac` or `\nabla` would read as garbage, and a double-quoted
string also interpolates `$` variables (a `$x` in the TeX would be replaced). In single
quotes write TeX's `\\` line break as `\\\\`.

`Math::tex(string $tex): Node` never throws. To also learn whether the source asked for display style, or what was
lost, use the reader:

```php
use Papercut\Math\Tex\TexReader;

$result = TexReader::read('$$\sum_{n=1}^{\infty} \frac{1}{n^2}$$');
$result->node;      // the formula
$result->display;   // true for $$…$$, \[…\], a top-level \displaystyle, and display environments (align, gather, equation; not aligned)
$result->notes();   // list<MathNote>, as for MathML
```

The input is math mode. A wrapper (`$…$`, `$$…$$`, `\(…\)` or `\[…\]`) is optional and removed. The reader covers
what [KaTeX supports](https://katex.org/docs/supported.html) for math: fractions and binomials, roots, scripts and
primes, `\left`, `\right` and `\middle`, `\big` sizes, accents, over- and underbraces, extensible arrows,
large operators with limits, font and style commands, `\text`, spacing, and the environments `matrix` and its
variants, `array`, `cases`, `aligned`, `align`, `gathered` and `gather`. Macros work too: `\newcommand`,
`\renewcommand`, `\providecommand`, `\def` and `\DeclareMathOperator`, scoped to the group they appear in.

What it does not draw: colour (`\color`, `\textcolor`), boxes (`\boxed`, `\fbox`, `\cancel`), `\hline` and column
rules in `array`, `\tag`, commutative diagrams (`CD` is drawn as a flat row), and `\newenvironment` and the like, plus a few more: `\sideset`, `\tensor`, `\prescript`, `\mathchoice`, `\scriptstyle` and
`\scriptscriptstyle` (drawn in text style), `p{…}` and `@{…}` columns in `array`, and `smallmatrix` (drawn at normal
size). Each one gives a note, and an unknown command is drawn as its own name, as KaTeX shows it in its error colour.

Notes work as they do for MathML (see [What is approximated or dropped](#what-is-approximated-or-dropped)): one per
element and reason, with a level of approximated or dropped. For TeX, `element` is the command with its backslash
(`\boxed`), the environment name or the limit that was hit, and `where` is `offset N`, a byte offset into the input.

Limits keep a hostile or runaway formula cheap. They are constants on `TexReader`:

| Constant | Value | Meaning |
|---|---|---|
| `MAX_INPUT` | 16,384 bytes | Input beyond this is dropped. |
| `MAX_DEPTH` | 256 | Nesting (groups, arguments, environments) beyond this is drawn as its TeX source text. |
| `MAX_EXPANSIONS` | 10,000 | Macro expansions per formula. |
| `MAX_EXPANSION_BYTES` | 16,384 | What macro expansion may produce, in source bytes with the input included (equal to `MAX_INPUT`). |
| `MAX_CELLS` | 10,000 | Cells in all environments of a formula; rows past it are dropped and a long row is cut. |

Macro expansion is counted in source bytes against the same 16 KB, with the input included, so expanded text never
exceeds the size of a maximum raw formula. Hitting a limit gives an approximated note, never an exception. Stretched
delimiters and arrows are capped at 100 em.

With Xdebug's develop mode on, the call stack itself is limited (`xdebug.max_nesting_level`, 512 by default), and the
reader lowers `MAX_DEPTH` to what fits under it, so deep nesting gets its note sooner than 256 levels (with the default
about 45 levels). Raise `xdebug.max_nesting_level` to 4096, or turn develop mode off, for the full depth.

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
  `Glyph` also has `sx` and `sy` (both 1.0 for an ordinary glyph): factors that scale the glyph's outline along x and y
  about its origin `(x, y)`. A stretched symbol draws a run of identical straight pieces as one glyph scaled along the
  stretch, so a backend that draws glyphs itself must apply them (an SVG `transform="translate(x y) scale(sx sy)"`, or
  the same in your PDF matrix); ignoring them draws one unscaled piece where a long run should be. `size` still scales
  uniformly.
- `Rule(x, y, w, h)`: a filled rectangle (fraction bar, radical overbar), `(x, y)` its top-left corner.

`$box->items` is built on each read: a box keeps the boxes placed in it (so laying out deeply nested formulas stays
fast) and flattens them, shifting every item to its absolute position, whenever you read the property. Read it once into
a variable, or walk the tree without building the list with `$box->each(fn (DisplayItem $item, float $dx, float $dy) => …)`,
which gives each item as laid out in its own box plus the offset `(dx, dy)` to add to its `x` and `y`. Writing to the list
in place (`$box->items[] = …`) no longer works; assign a whole list, or leave a laid-out box alone.

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

### Writing TeX

```php
use Papercut\Math\Render\TexWriter;

echo TexWriter::write($formula);   // \frac{1}{2}
```

`TexWriter::write(Node $root): string` writes standard TeX, with no package-specific commands, so KaTeX and MathJax
accept it. Reading the output with `Math::tex()` gives the same formula back, for formulas read from TeX and for those
made with the builder. MathML has details TeX has no word for (a `mathvariant` TeX has no font for, an attribute the
tree does not keep); those are dropped, and the nearest TeX is written.

Word OMML, always in Cambria Math (the configured font does not apply), with letters written as plain letters (Word
italicizes them) and `Math::text` as `m:nor` runs:

```php
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\RunFormat;

echo OmmlWriter::oMath($formula);                                         // inline, goes between runs
echo OmmlWriter::oMathPara($formula, 'center');                           // block; jc is left, center or right
echo OmmlWriter::oMath($formula, new RunFormat(colorHex: 'C00000', sizeHalfPoints: 24));
```

Word does not stretch `=` in a group character, so `\xlongequal` is drawn at the normal `=` width in Word, with its
labels in place. The PDF stretches it, and Word stretches the other extensible arrows.

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
