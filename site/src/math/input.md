# Input

A formula is a tree of nodes. Build it in PHP or read it from TeX or MathML; every writer takes the result.

## The builder

`Quoin\Math\Math` has a static method for each shape. Wherever a method takes a node, a string works too.

```php
use Quoin\Math\Math;

$quadratic = Math::row('x =', Math::frac(
    Math::row('−b ±', Math::sqrt(Math::sup('b', '2'), '− 4ac')),
    '2a',
));
```

A string is split into symbols: letters become italic variables, digit runs become numbers, known function names such as `sin` and `log` stay upright, and spacing comes from the operator dictionary, so `a+b` and `a + b` come out the same. An ASCII `-` is drawn as a true minus. Unicode scripts attach to what precedes them, so `x²` is a real superscript.

| Method | Builds |
|---|---|
| `row(...$parts)` | Parts side by side |
| `frac($top, $bottom)`, `binom($top, $bottom)`, `stack($top, $bottom)` | A fraction, a binomial, two lines without a rule |
| `sqrt(...$content)`, `root($radicand, $index)` | Roots |
| `sup`, `sub`, `subSup($base, $sub, $sup)` | Scripts |
| `prescripts($base, $preSub, $preSup, $sub = null, $sup = null)` | Scripts before the base, with optional scripts after it |
| `fenced($open, $close, ...$content)`, `middle($delimiter)`, `big($delimiter, $size)` | Stretching and fixed-size delimiters |
| `sum`, `product`, `integral`, `bigOp($op, $under, $over)` | Large operators with optional limits |
| `under`, `over`, `underOver`, `overbrace`, `underbrace`, `overline`, `underline` | Things above and below a base |
| `accent($base, $mark)`, `hat`, `tilde`, `vec`, `dot` | Accents |
| `matrix($rows, $fence, $align)`, `cases($rows)`, `aligned($rows)` | Grids |
| `xarrow($arrow, $over, $under)` | An arrow that stretches over its labels |
| `text($text)`, `styled($text, $variant)` | Upright words; text in a `mathvariant` such as `double-struck` |
| `space($em)`, `phantom(...$content)` | Spacing |

The [Equations guide](/guide/equations.md#building-a-formula) has every method with examples. Methods that get impossible arguments, such as a matrix with no cells or an unknown variant, throw `InvalidArgumentException`; the readers below never throw.

## TeX

```php
$formula = Math::tex('\int_0^1 x^2 \, dx = \frac{1}{3}');
```

Write TeX in single-quoted strings or a nowdoc (`<<<'TEX'`). In double quotes PHP turns `\f`, `\n`, `\t`, `\v`, `\r` and `\e` into control characters, so `\frac` and `\nabla` arrive broken, and `$x` is replaced by a PHP variable. Inside single quotes, TeX's `\\` line break is written `\\\\`.

The input is math mode. A `$…$`, `$$…$$`, `\(…\)` or `\[…\]` wrapper is optional. The reader covers what [KaTeX supports](https://katex.org/docs/supported.html) for math: fractions, roots, scripts and primes, `\left`, `\right` and `\middle`, `\big` sizes, accents, braces, extensible arrows, large operators with limits, font and style commands, `\text`, spacing, and the environments `matrix` and its variants, `array`, `cases`, `aligned`, `align`, `gathered` and `gather`. Macros work: `\newcommand`, `\renewcommand`, `\providecommand`, `\def` and `\DeclareMathOperator`, each scoped to its group.

These are read but not drawn, and each one is reported: color (`\color`, `\textcolor`), boxes (`\boxed`, `\fbox`, `\cancel`), `\hline` and column rules, `\tag`, commutative diagrams, `\newenvironment`, `\tensor` and `\mathchoice`. `\scriptstyle` and `\scriptscriptstyle` are drawn in text style, and `smallmatrix` at normal size. An unknown command is drawn as its own name, as KaTeX shows it.

The TeX writer keeps what the reader does not draw. `\boxed`, `\fbox`, `\color`, `\textcolor`, `\cancel`, `\bcancel`, `\xcancel` and `\sout` are written back with their content normalized, and `\tag` is written back at the top level of a formula. TeX in, TeX out loses none of them, though the other writers still draw only the content.

Scripts before a base are read from `{}^{a}_{b}X`, `\prescript{a}{b}{X}` and `\sideset`, and drawn right-aligned against the base. `\sideset` draws the scripts in its first two arguments; anything else in them is dropped and noted.

### Chemistry

`\ce` and `\pu` read [mhchem](https://mhchem.github.io/MathJax-mhchem/), the notation KaTeX and MathJax offer as an extension.

```php
$reaction = Math::tex('\ce{2H2 + O2 -> 2H2O}');
$ion = Math::tex('\ce{SO4^2-}');
$quantity = Math::tex('\pu{25 kJ/mol}');
```

The result is ordinary nodes: upright element symbols, subscript counts, superscript charges and stretchy arrows. There are no chemistry node types, so PDF, SVG, PNG, MathML and Word equations all draw it, and Word gets real element symbols and arrows instead of letter runs. The reader covers the common core of mhchem:

| Input | Drawn as |
|---|---|
| `H2O`, `Fe2O3`, `Fe(OH)3`, `[Cu(NH3)4]^2+` | Upright symbols, counts as subscripts, groups with a count after the bracket |
| `2H2O`, `1/2O2`, `$n$ H2O` | A coefficient, with a thin gap after a number, a fraction or a `$…$` amount |
| `SO4^2-`, `Ca^{2+}`, `NH4+`, `Cl-`, `e-` | Charges as superscripts; `-` is drawn as a true minus |
| `(aq)`, `(s)`, `(l)`, `(g)` | State symbols, upright, set off by a small gap |
| `CuSO4*5H2O`, `CuSO4.5H2O` | A hydrate dot |
| `CH3-CH3`, `C=C`, `C#C` | Single, double and triple bonds |
| `CO2 ^`, `AgCl v` | Gas and precipitate arrows |
| `^{14}_{6}C`, `^{14}C` | Isotopes, as prescripts |
| `$x$`, `\alpha` | Math, read by the TeX reader |

Between two atoms `-` is a bond; after a symbol, count or group and before a space or the end it is a charge. So `\ce{CH3-CH3}` has a bond and `\ce{Cl-}` a charge.

Arrows are `->`, `<-`, `<->`, `<-->` and `<=>`, with labels in `[above][below]`, as in `\ce{A ->[\Delta][H2O] B}`. They stretch to fit their labels. A label is read as chemistry, `$…$` is math, and a braced label such as `[{a b}]` is literal text. `<=>>` and `<<=>`, the unequal equilibrium arrows, are drawn as `⇌` with an approximated note.

`\pu` reads a number and units: `\pu{25 kJ/mol}`, `\pu{kJ mol-1}`, `\pu{1.2e3 kJ}` (drawn 1.2 ⋅ 10³), `\pu{10^-3 mol}` and `\pu{25 °C}`. Spacing follows mhchem, and `°C` is a plain degree sign followed by C.

A piece the subset does not cover, `\bond{…}` or a `\\` line break, is drawn as its source text, upright, with an approximated note, and the rest of the argument is still read. An unknown command inside `\ce`, such as `\cf` or `\cee`, is drawn as its name with a dropped note, and its argument is read as math. Nuclear notation such as `^{0}_{-1}e` reads as prescripts, and orbital notation such as `1s^2 2s^2` reads as an ordinary run. siunitx (`\SI`, `\si`, `\qty`, `\num`) is not supported. Input limits, including nesting, cover the argument and any math inside it.

The TeX writer writes `\ce{…}` and `\pu{…}` back as read, not expanded. The TeX it makes needs mhchem wherever it goes next; KaTeX and MathJax both offer it.

Equilibrium arrows such as `\xrightleftharpoons` stretch to their labels everywhere, not only in chemistry. An arrow without labels that Word cannot stretch is written there as a plain long arrow.

The reader also returns the display style and the notes:

```php
use Quoin\Math\Tex\TexReader;

$result = TexReader::read('$$\sum_{n=1}^{\infty} \frac{1}{n^2}$$');
$result->node;      // the formula
$result->display;   // true: $$…$$, \[…\], a top-level \displaystyle and display environments ask for display style
$result->notes();   // what was not drawn as written
```

| Limit | Value | Past it |
|---|---|---|
| `TexReader::MAX_INPUT` | 16,384 bytes | The rest of the input is dropped. |
| `TexReader::MAX_DEPTH` | 256 levels | Deeper groups are drawn as their TeX source. |
| `TexReader::MAX_EXPANSIONS` | 10,000 | Macro expansion stops. |
| `TexReader::MAX_EXPANSION_BYTES` | 16,384 bytes | Expansion may not grow the formula past what 16 KB of raw TeX could be. |
| `TexReader::MAX_CELLS` | 10,000 | Rows are dropped and a long row is cut. |

Under Xdebug's develop mode the reader lowers its depth limit to fit `xdebug.max_nesting_level` (512 by default). Raise that to 4096, or turn develop mode off, for the full 256 levels.

## MathML

```php
$formula = Math::mathml('<math display="block"><mfrac><mn>1</mn><mn>2</mn></mfrac></math>');
```

`Math::mathml()` takes a string or a parsed `Dom\Element`. It reads what equation editors, Word and hand-written pages produce:

- Well-formed XML is read as XML; anything else is read with HTML5 rules, so `&nbsp;` works and the `<math>` element may sit inside other markup.
- Word's `mml:` prefix works, and so does no prefix.
- A fragment without a `<math>` element is read as if it had one.
- Annotations, such as a TeX copy of the formula, are ignored.
- The text form that MathType and Wiris store (`«math…«/math»`) is accepted. `Quoin\Math\Mathml\SafeXml::find($text)` locates those runs in a larger text, and `SafeXml::decode()` turns one into MathML.

Entity declarations in a DOCTYPE are ignored.

`MathmlReader::read()` returns a result with the same three fields as the TeX reader's; `display` is true for `display="block"`.

`<mmultiscripts>` is read as prescripts and scripts, one pair on each side; more pairs are noted. What is not drawn as written: `<menclose>` is drawn as its content, `<maction>` as its first choice, and table lines, spacing and spans are ignored. Images and other media inside a formula are dropped, and so is content that an HTML tag pushed out of a `<math>` element in markup that isn't well-formed. `mathcolor`, `mathsize` and `mathbackground` are ignored.

| Limit | Value | Past it |
|---|---|---|
| `MathmlReader::MAX_INPUT` | 1 MB | The rest of the string is dropped. |
| `MathmlReader::MAX_TEXT` | 16,384 characters | Text across all tokens is cut. |
| `MathmlReader::MAX_ELEMENTS` | 32,768 | Reading stops; table rows and cells count. |
| nesting | 256 levels | Deeper content is drawn as flat text. |

## Notes

Both readers record what they changed, once per element and reason in a formula. `Math::notes($node)` returns them in reading order, as the reader results do.

```php
use Quoin\Math\NoteLevel;

foreach (Math::notes($formula) as $note) {
    echo $note->level === NoteLevel::Dropped ? 'dropped' : 'approximated', ': ', $note->element, ' (', $note->reason, ")\n";
}
```

| Field | Holds |
|---|---|
| `level` | `NoteLevel::Approximated`: drawn, but not as the source meant. `NoteLevel::Dropped`: not drawn. |
| `element` | The MathML element name, or the TeX command with its backslash, environment name or limit |
| `reason` | Plain words, such as `the box is not drawn` |
| `where` | A path to the element in MathML; `offset N`, a byte offset, in TeX |

### Missing glyphs

Some math fonts lack whole alphabets: Fira Math has no script or Fraktur letters, and Euler has no double-struck ones. A character the font has no glyph for is drawn as the font's missing-glyph box. These notes depend on the font, so they come from the laid-out box. `$box->notes()` returns one `Dropped` note per such character, with the character as `element`.

```php
$box = Math::layout(Math::tex('\mathcal{L}(x)'), font: $fira);

foreach ($box->notes() as $note) {
    echo $note->reason, "\n";   // the font has no glyph for U+2112, so it is drawn as a missing-glyph box
}
```
