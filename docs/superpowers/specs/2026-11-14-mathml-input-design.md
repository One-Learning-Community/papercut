# MathML input, limits, accents and bars

## Why

Papercut 0.11 added equations, but the only way to write one is the PHP builder (`Math::frac(...)`) or a Blade
attribute holding a built node or a plain string. Most people who write equations don't write PHP. They use an
equation editor inside a WYSIWYG tool: MathType/Wiris in CKEditor or TinyMCE, MathLive, or Word's "copy as MathML".
The HTML those tools produce reaches Papercut through `Markup::html()`, and today any `<math>` element in it is an
unknown tag: its text survives as a jumble of characters and the formula is lost.

MathML input was planned for early post-1.0 in the equations spec (`2026-11-13-equations-design.md`, non-goals). It
is being pulled forward because editor HTML is the realistic source of equations for Papercut's users.

Editors also let authors build things the 0.11 vocabulary can't draw. The most common by far are sums, integrals and
products with limits, accents (x̂, x̃, vector arrows), and over and under bars (x̄). Those come in the same release,
so that the first MathML that real users paste mostly works.

## Decisions taken with the user

- Wiris/MathType's `<img data-mathml>` and safeXml storage forms are recognised as well as `<math>`.
- The PHP builder gets methods for every new construct.

- Equations come from **any** of these: editor HTML through the HTML converter, a MathML string in the builder, and
  a MathML string in Blade. The main case is editor/WYSIWYG output.
- **Write our own reader. Don't adopt a library.** Parsing is just the built-in DOM. The work is mapping MathML onto
  `papercut/math`'s nodes, and no outside library knows those nodes.
- **Best effort, with a warning.** Input that can't be drawn exactly is still drawn as well as possible and reported
  through Papercut's existing fidelity notes (`approximated` / `dropped`, `onWarning`, strict mode). It never throws
  and never silently drops content.
- **Same release:** limits on big operators, accents, over and under bars. **Later:** matrices and tables (`mtable`).
- The php-pdf "show glyph by ID" gap is **not** part of this work. It blocks nothing, because `MathPainter` already
  draws code-point-less glyphs (size variants, stretched pieces) as paths. A local workaround is possible (see the end
  of this document) and stays a separate item.

## Goals

1. A MathML reader in `papercut/math` that turns MathML into the package's formula tree and reports what it had to
   approximate or drop.
2. `<math>` in `Markup::html()` input becomes an inline or block equation, in PDF, Word and HTML export.
3. A MathML string works wherever a formula is accepted: the builder and the Blade `equation` / `math` components.
4. New vocabulary, in layout and in all three writers (SVG/PDF, OMML, MathML) plus `TextWriter`:
   - under/over scripts (`munder`, `mover`, `munderover`), with large operators set at display size and limits
     above and below in display style, beside in inline style (`movablelimits`);
   - accents, including wide ones that stretch horizontally over their base;
   - over and under bars;
   - an explicit space (`mspace`) and an invisible box (`mphantom`);
   - a fraction with no bar (`mfrac linethickness="0"`, used for binomial coefficients);
   - letter styles from `mathvariant` (see below).
5. MathType/Wiris's other storage forms are recognised in editor HTML (see "Wiris storage forms").
6. The PHP builder gets public methods for every new construct (see "Builder methods").
7. Real editor output as test fixtures, collected from MathType/Wiris, MathLive, CKEditor/TinyMCE and Word's MathML
   copy. Fixtures stay generic: formulas from ordinary school and university maths, never consumer content.

## Non-goals

- `mtable` / matrices, `mlabeledtr` and equation numbering. They come next.
- `menclose`, `mmultiscripts` (prescripts), elementary maths (`mstack`, `mlongdiv`), `maction`. Handled by the
  fallback, not drawn properly.
- LaTeX input. The `<annotation encoding="application/x-tex">` that editors include in `<semantics>` is ignored. A
  TeX reader may come later through latex2mathml-php.
- Colour and size inside a formula (`mathcolor`, `mathbackground`, `mathsize`, `mstyle` colour). Formulas take the
  surrounding text's colour and size, as they do today. This matches the converter's default theme style mode,
  which ignores editor inline styles. *(My call.)*
- Passing the author's MathML through to HTML export unchanged. HTML export writes MathML from the formula tree, as
  it does today, so the output is clean and never carries editor markup or scripts.
- Line breaking inside a formula.
- The glyph-ID workaround.

## Anti-goals

- An exception, or a blank, for input the reader doesn't fully understand. Editor output is messy, and an author
  can't fix the MathML their editor writes.
- Warnings about editor clutter that loses nothing. A pasted equation that renders correctly must not produce a
  fidelity note.
- Shipping the reader without the large-operator layout. Sums with limits are among the first formulas people will
  paste.

## The reader (`papercut/math`)

- A public class, e.g. `Papercut\Math\Mathml\MathmlReader`. *(Name is the builder's call.)* It takes either a MathML
  string or a `\Dom\Element` (the `<math>` element the HTML converter already has). It returns a result holding the
  formula `Node`, whether the `<math>` asked for block display (`display="block"`), and a list of notes. A note says
  what was lost (`approximated` or `dropped`), what the element was, and a short reason. The package can't depend on
  core, so the notes are its own small value class, and core maps them onto `Note` / `FidelityLevel`.
- **Parsing a string** uses PHP 8.4's HTML5 parser (`\Dom\HTMLDocument`), not the XML parser. Editors write HTML
  entities (`&nbsp;`, `&InvisibleTimes;`, `&#x2061;`), omit the namespace and sometimes leave tags unclosed. The
  HTML5 parser handles all of that the way a browser does and can't fail. A string with no `<math>` root is wrapped
  in one. `papercut/math` gains `ext-dom` in its requirements.
- **Depth.** Nesting deeper than the converter's limit (256) is flattened to text with one `approximated` note, as the
  HTML converter does for deep HTML (`FidelityLevel::Approximated`), so strict mode doesn't fail on it.
- **Where notes live.** *(My call.)* Notes are part of the formula tree. Anything the reader approximates or drops
  leaves a node behind: a fallback node holding the laid-out contents (or nothing, for dropped content) plus the
  note. A formula's notes are found by walking its tree, so they survive any route: a MathML node nested inside a
  builder formula, `Text::spans(Span::math(...))` written by hand, Blade. Core collects them when it reports. The
  equation's own notes come from its tree, and a `Text`'s notes include those of its math spans. `NoteReporter`
  learns to look inside equations and math spans. `Math::mathml()` therefore returns a plain `Node`.
- **Empty input** (`<math></math>`, or only whitespace) gives an empty formula and no note.

### Mapping

| MathML | Formula tree | Notes |
|---|---|---|
| `math`, `mrow`, `mstyle`, `mpadded`, `semantics` (first child only), `annotation*` (ignored) | `Row`, flattened when it holds one child | none |
| `mi` | `Identifier` (one letter italic, several upright, `mathvariant="normal"` upright) | none |
| `mn` | `Number` | none |
| `mo` | `Operator`; honours `stretchy`, `largeop`, `movablelimits`, `accent`, `form` | `lspace`/`rspace`/`minsize`/`maxsize` ignored silently *(my call)* |
| `mtext`, `ms` | `Text` (`ms` with its quotes) | none |
| `mspace` | new space node, width from `width` (em, ex, pt, px, mu-free named spaces such as `thinmathspace`) | none |
| `mphantom` | new phantom node: takes the space of its contents, draws nothing | none |
| `mfrac` | `Fraction`; `linethickness="0"` (or `0px` etc.) gives no bar | other thicknesses ignored silently |
| `msqrt`, `mroot` | `Root` | none |
| `msub`, `msup`, `msubsup` | `Scripts` | none |
| `munder`, `mover`, `munderover` | new under/over node (see below) | none |
| `mfenced` (deprecated, MathType still writes it) | `Fenced` with `open`/`close`; `separators` become operators between the children | none |
| a row whose first and last children are a matching stretchy fence pair | `Fenced` *(builder's call whether to detect this or rely on stretchy `mo` layout)* | none |
| `mstyle displaystyle="true\|false"` | display or inline style for its contents | other `mstyle` attributes ignored silently |
| `mtable`, `mtr`, `mtd` | each row's cells in one row, separated by a wide space; rows separated by a wider space | `approximated` |
| `menclose`, `mmultiscripts`, `maction` (first child), `merror`, and any unknown element | its children as a row (the MathML spec's rule for unknown elements) | `approximated` |
| foreign content inside math (an `<img>`, `<svg>`, HTML) | nothing | `dropped` |

`mathvariant` maps letters and digits to the Unicode mathematical alphanumeric code points: `bold`, `italic`,
`bold-italic`, `double-struck`, `script`, `bold-script`, `fraktur`, `bold-fraktur`, `sans-serif` and its bold and
italic forms, `monospace`. That is how editors' `mathvariant="double-struck"` ℝ and bold vectors survive. Characters
with no styled form stay as they are. Most editors already write the styled code point (𝐯, ℝ) themselves, and that
needs nothing. *(My call: a table in `Styles`, alongside the existing italic map.)*

## New layout

All of it is driven by the font's `MATH` table, like the existing constructs.

- **Under/over node.** A base with an optional under and an optional over part, and flags for `accent` /
  `accentunder`. Layout follows MathML Core §3.4.3 (the `MATH` constants `UpperLimitGapMin`,
  `UpperLimitBaselineRiseMin`, `LowerLimitGapMin`, `LowerLimitBaselineDropMin`, `StretchStack*`,
  `OverbarVerticalGap`, `AccentBaseHeight`, and so on). An over part that is an accent sits at the accent position,
  using the base's top accent attachment (`MathTopAccentAttachment`) to centre it.
- **Large operators.** An `mo` with `largeop` (from the operator dictionary or the attribute) in display style uses
  the font's vertical size variant of at least `DisplayOperatorMinHeight`. In inline style it stays at its normal
  size.
- **Movable limits.** A base that is an operator with `movablelimits` (∑, ∏, lim, …) puts its under/over parts in the
  subscript and superscript positions when the style is inline, and above and below when it is display. ∫ is
  `largeop` but not `movablelimits`, so `msubsup` on it keeps its scripts beside it, as the operator dictionary says.
- **Horizontal stretching.** Wide accents (^, ˜, ˇ over several letters), arrows (→ for vectors) and over/under
  braces stretch horizontally to the base's width using the font's horizontal variants and assemblies. `Stretcher`
  gains a horizontal counterpart. This was a non-goal in 0.11; it is in now because accents over wider bases need it.
- **Bars.** An over or under part that is `¯`, `‾` or `_` is drawn as a rule the width of the base (`OverbarRuleThickness`,
  `OverbarVerticalGap`, `OverbarExtraAscender` and their underbar counterparts), not as a stretched glyph.
- **Space and phantom.** Plain boxes. A phantom is the laid-out contents with its display items removed.

## Builder methods

(User decision: yes.) `Math` gets a public method for each new construct, in the style of the existing ones (plain
strings accepted wherever a node is). Roughly:

- a large operator with limits, e.g. `Math::sum($under, $over)`, `Math::integral(...)`, `Math::product(...)` and a
  general `Math::bigOp('⋃', $under, $over)`; limits optional;
- `Math::under($base, $under)`, `Math::over($base, $over)`, `Math::underOver($base, $under, $over)`;
- `Math::accent($base, '^')` (and named shortcuts such as `hat`, `tilde`, `vec`, if they read well);
- `Math::overline($base)`, `Math::underline($base)`, and over/under braces;
- `Math::space($width)`, `Math::phantom(...)`;
- `Math::binom($top, $bottom)` (a barless fraction in parentheses) and a barless fraction;
- styled letters, e.g. `Math::styled('R', 'double-struck')`, using the same `mathvariant` table.

Names and the exact set are the builder's call. The new node classes become public API the way the existing ones
are. The README and the Equations docs page cover the new methods, and Blade needs no new tags: Blade users pass a
built node or MathML.

PDF painting needs nothing new: size variants and stretched pieces are glyphs without their own code point and go
through the existing path drawing in `MathPainter`.

## Writers

- **MathML (`MathmlWriter`)** writes `munder`, `mover`, `munderover` (with `accent`/`accentunder`), `mspace`,
  `mphantom`, `mfrac linethickness="0"`, styled letters as their Unicode code points.
- **OMML (`OmmlWriter`)**, using real Word structures, so equations stay editable in Word's equation editor, even where an edge case then
  looks slightly different from the PDF:
  - a large operator with limits → `m:nary` with `m:chr`, `m:limLoc` (`undOvr` or `subSup`) and `m:subHide` /
    `m:supHide` when a limit is missing. `m:nary` also needs its body (`m:e`): the term that follows the operator in
    the row becomes the body, following what Word's own MathML-to-OMML conversion (MML2OMML.XSL) does. The exact rule
    for "the term that follows" is the builder's call; it must be defined and tested, and an operator with nothing
    after it gets an empty `m:e`;
  - an accent → `m:acc` with `m:chr`;
  - a bar → `m:bar` with `m:pos` top or bottom;
  - an over/under brace or bracket → `m:groupChr`;
  - any other under/over → `m:limLow` / `m:limUpp` (nested for both);
  - space → a run holding the nearest Unicode space characters; phantom → `m:phant`;
  - fraction with no bar → `m:f` with `m:type m:val="noBar"`.
- **SVG (`SvgRenderer`)** draws whatever layout produces, as today.
- **`TextWriter`** writes linear forms for outline titles and fallbacks: `∑_(i=1)^n`, `x̂` (combining accent for one
  letter, `hat(xy)` otherwise), `x̄` (combining macron) — the builder settles the details.

## Papercut core

- **HTML converter.** `<math>` (the HTML5 parser already puts its contents in the MathML namespace) goes to the
  reader. `display="block"` becomes a block `Equation`, which ends the current paragraph and starts a new one after
  it, the same way the converter treats other blocks inside text. Otherwise it becomes an inline `Span::math` in the
  current paragraph. The reader's notes become conversion notes on the converted node, so every export reports them,
  following the ruling that conversion notes are data on immutable nodes.
- **Builder.** `Math::mathml(string|\Dom\Element)` returns the formula node, notes included (see "Where notes live").
  It works anywhere a node does: `Equation::of(Math::mathml($s))`, `Span::math(Math::mathml($s))`.
- **Wiris storage forms.** (User decision: recognise both.) Depending on its configuration, Wiris/MathType saves a
  formula in one of two forms other than a literal `<math>`:
  - an image, `<img class="Wirisformula" … data-mathml="…">`. When `data-mathml` holds MathML (plain or safeXml),
    the converter reads that and ignores the image, with no note. An image without usable `data-mathml` stays an
    ordinary image;
  - "safeXml" text, where `«`, `»`, `¨`, `§` and `` ` `` stand for `<`, `>`, `"`, `&` and `'`, appearing as text in
    the HTML: `«math xmlns=¨…¨»…«/math»`. The converter finds each `«math…«/math»` run in text, decodes it and
    reads it as MathML; the text around it stays as it was. Decoding happens only inside such a run, so ordinary
    text containing `«` or `»` (French quotation marks) is left alone.
  The decoding lives in `papercut/math` next to the reader (e.g. a `safeXml` decode that `MathmlReader` accepts
  directly), so other apps can use it. Each form has its own fixtures, collected or reproduced from Wiris's
  documentation and plugins.
- **`display` outside the converter.** *(My call.)* Only the HTML converter reads `display="block"`. Elsewhere, the
  entry point decides: `Equation` is always a block and `Span::math` always inline. Blade keeps its existing rule
  (`<x-doc::math>` in a text tag is inline, otherwise a block; `<x-doc::equation>` is a block).
- **Blade.** `<x-doc::equation>` and `<x-doc::math>` already take a string. A string whose first non-space characters
  are `<math` is read as MathML; any other string is tokenized as today. *(My call: no new attribute.)*
- **Fidelity.** Notes read like the converter's existing ones: what was lost and where. E.g.
  `math element "mtable" (tables are drawn as rows of cells)` at `p > math > mtable`. Once per element name per
  formula.
- **HTML export, Word, PDF** need nothing beyond the writer and layout changes above.

## Examples, docs, tests

- Docs: a "MathML input" section on the Equations page, covering where it works, the supported elements, what the
  fallback does and how it's reported, and which editors were tested. The `papercut/math` README gets the reader.
- An example that converts editor HTML with a few equations. Mind the Letter/A4 balance.
- Tests:
  - mapping tests per element and attribute, including editor clutter producing no notes;
  - fallback tests for each `approximated` / `dropped` case;
  - collected editor fixtures through the reader and all three writers as snapshots;
  - layout metric tests for under/over, large operators in both styles, accents, bars and horizontal stretch;
  - OMML validated with the existing `bin/omml-check.php` / ooxml-validator setup;
  - core tests for the converter (inline, block splitting a paragraph, notes reaching `onWarning`, strict mode
    failing only on `dropped`) and for Blade's `<math` detection;
  - Wiris forms: `<img data-mathml>` with plain and safeXml MathML, an image without it, safeXml runs in text,
    and French quotation marks left alone;
  - notes surviving nesting: a MathML node inside a builder formula, and a hand-built `Text::spans(Span::math(...))`;
  - builder methods: layout and all writers for each.
- CHANGELOG entry. NOTICE.md needs nothing new unless a fixture source requires attribution.

## Left to the builder

- Class and method names.
- The `m:nary` body rule, within the constraint above.
- Whether matching stretchy `mo` fences in a row become `Fenced` or are laid out as stretchy operators.
- The exact `TextWriter` forms.
- How the reader's notes are worded, within the converter's existing style.
- Which editors' output can actually be collected. MathType/Wiris, MathLive and Word are expected. If one can't be
  reached, use published samples of its output and say so in the fixture's header.

## Aside: the glyph-ID workaround (not in scope)

If ever wanted before php-pdf ships "show glyph by ID": write the glyph ID through `Page::rawContentStream()->
textHexString()` (public; php-pdf's embedded fonts are Identity-H), get the font's resource name through a bound call
to `Page::registerEmbeddedFont`, and add the glyph to `PdfFont::$usedGlyphs` with its source code point so it is
subset and copies correctly. Guard it with a property-existence test like `PhpPdfBackend::guardTextStateProperties`.
It would turn path-drawn variants into real text and allow `ssty` script glyphs in PDF.
