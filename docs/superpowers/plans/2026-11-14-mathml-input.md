# MathML Input Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Read MathML (including editor output and Wiris's storage forms) into `papercut/math` formulas, and add
under/over scripts, large operators with limits, accents, bars, spaces, phantoms, barless fractions and styled letters
to layout, all writers and the builder.

**Architecture:** `packages/math` gains new nodes (`UnderOver`, `Space`, `Phantom`, `Fallback`), a horizontal
stretcher, a `MathmlReader` that parses with PHP's HTML5 parser and maps elements onto nodes, and a `SafeXml`
decoder. Anything the reader can't render exactly becomes a `Fallback` node carrying a `MathNote`, so notes live in
the formula tree. Core's `NoteReporter` collects them from equations and math spans. `HtmlConverter` reads `<math>`,
Wiris images and safeXml text, and Blade reads strings that start with `<math`.

**Tech Stack:** PHP 8.4 (`\Dom\HTMLDocument`), PHPUnit 13, PHPStan, OpenType MATH table, OMML, MathML Core.

**Spec:** `docs/superpowers/specs/2026-11-14-mathml-input-design.md`

## Global Constraints

- Work in worktree `~/work/olc/papercut-mathml`, branch `mathml`. Never touch `~/work/olc/php-pdf` (the user's
  checkout, on another branch with local changes).
- Run `composer install` in `packages/math`, `packages/core`, `packages/docx` and `packages/laravel` before the first
  test run; packages find each other through path repositories.
- `papercut/math` depends on nothing in Papercut. Its `require` becomes `php ^8.4`, `ext-ctype`, `ext-mbstring`,
  `ext-dom`. Nothing else.
- The reader never throws on input and never returns nothing for non-empty input it could partly read. Unknown or
  undrawable content → `approximated`; content lost → `dropped`. Editor clutter that loses nothing → no note.
- Nesting deeper than 256 levels → `approximated` (not `dropped`).
- One note per element name per formula.
- Colour/size attributes inside MathML (`mathcolor`, `mathbackground`, `mathsize`, `mstyle` colour) are ignored
  silently. HTML export writes MathML from the tree, never the input markup.
- Word equations stay in Cambria Math; OMML uses real Word structures (`m:nary`, `m:acc`, `m:bar`, `m:groupChr`,
  `m:limLow`, `m:limUpp`, `m:phant`).
- Existing goldens and snapshots stay byte-identical, except where a task says a display-style large operator now
  draws at display size; report every changed snapshot or golden in the task's commit message.
- No consumer-specific names in code, tests, docs or examples. Fixtures are ordinary school/university maths.
- Commits end with `Co-Authored-By: Claude <model> <noreply@anthropic.com>` and **no** `Claude-Session` line or
  session URL.
- Tests in `packages/math`: `vendor/bin/phpunit`; analysis: `composer analyse`. Same in `packages/core`. Snapshot
  updates: `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --filter <Test>`; inspect every new snapshot by eye before
  committing it.

## Review Focus

1. **Prefixed MathML from Word** (`<mml:math xmlns:mml="…"><mml:mi>x</mml:mi>…`). The HTML5 parser keeps these as
   HTML elements named `mml:mi`. The reader must match on the part after the last `:` and read the formula normally
   with no notes. Tests in Task 6 (string) and Task 11 (converter).
2. **A paragraph holding only an inline equation** (`<p><math><mfrac>…</mfrac></math></p>`). `InlineRun::take()`
   counts only text as visible, so a math span with empty text would be dropped as a blank paragraph. It must
   count as visible. Test in Task 11.
3. **Unpaired and nested fences** (`<mo>(</mo><mi>a</mi>` with no close, `( [ a ] )`, `|x|`). Pairing must never
   lose a child or reorder it; unmatched fences stay plain operators. Tests in Task 6.
4. **Entities and junk around the formula**: `&nbsp;`, `&InvisibleTimes;`, `&#x2061;`, an `<?xml …?>` declaration, a
   leading BOM or whitespace, `<annotation>` holding TeX with `<` and `&`. All parse; invisible operators (U+2061–
   U+2064) produce no glyph and no gap; TeX annotations never leak into the output. Tests in Task 6.
5. **The same formula through every entry point gives the same notes.** A MathML `mtable` read in the converter,
   through `Math::mathml()` nested in a builder fraction, and in Blade, each reports exactly one `approximated` note
   in an export, never zero and never two (no double reporting by both converter and tree). Tests in Tasks 10, 11
   and 12.

---

## Part A: `papercut/math`

### Task 1: Notes in the tree: `Node::children()`, `Fallback`, `MathNote`, `Math::notes()`

**Files:**
- Modify: `packages/math/src/Node/Node.php` and every node class (`Row`, `Fraction`, `Root`, `Scripts`, `Fenced`;
  token nodes inherit the empty default)
- Create: `packages/math/src/Node/Fallback.php`, `packages/math/src/MathNote.php`, `packages/math/src/NoteLevel.php`
- Modify: `packages/math/src/Math.php`, all four writers in `src/Render/`
- Test: `packages/math/tests/NotesTest.php`

**Interfaces:**
- Produces:
  - `abstract class Node { /** @return list<Node> */ public function children(): array }` (default `[]`).
  - `enum NoteLevel { case Approximated; case Dropped; }`
  - `final readonly class MathNote { __construct(public NoteLevel $level, public string $element, public string $reason, public string $where = '') }`
  - `final class Fallback extends Node { __construct(public readonly ?Node $content, public readonly MathNote $note) }`.
    Lays out as `$content` (an empty `Box` when null). Every writer writes the content, or nothing.
  - `Math::notes(Node $root): list<MathNote>`: depth-first over `children()`, collecting each `Fallback`'s note.

- [ ] **Step 1:** Write failing tests: `test_notes_found_at_any_depth` (a `Fallback` inside a `Fraction`
  numerator inside a `Scripts` base: `Math::notes()` returns that one note), `test_no_notes_without_fallback`
  (every entry of `Fixtures::all()` gives `[]`), `test_fallback_lays_out_as_its_content` (a fallback around
  `Math::frac('a','b')` gives the same width/ascent/descent as the fraction), `test_dropped_fallback_is_empty` (null
  content: width 0, and MathML/OMML/Text writers write nothing for it).
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter NotesTest`. Expected: FAIL (classes missing).
- [ ] **Step 3:** Implement. `children()` returns the node's child nodes in reading order (`Scripts`: base, sub,
  sup, skipping nulls; `Root`: radicand, index).
- [ ] **Step 4:** Run the whole math suite. Expected: PASS, snapshots unchanged.
- [ ] **Step 5:** Commit `feat(math): notes carried in the formula tree`.

### Task 2: Small constructs: `Space`, `Phantom`, barless fraction, styled letters

**Files:**
- Create: `packages/math/src/Node/Space.php`, `packages/math/src/Node/Phantom.php`
- Modify: `src/Node/Fraction.php`, `src/Styles.php`, `src/Math.php`, `src/Render/{MathmlWriter,OmmlWriter,TextWriter}.php`
- Test: `tests/Layout/SmallConstructsTest.php`, `tests/Render/*WriterTest.php` (new cases), `tests/Fixtures.php`
  (new entries `binomial`, `spaced-dx`, `phantom-align`, `double-struck-r`)

**Interfaces:**
- Produces:
  - `final class Space extends Node { __construct(public readonly float $em) }`: width `$em × size`, no ascent or
    descent. Negative widths allowed (they pull the next item back).
  - `final class Phantom extends Node { __construct(public readonly Node $content) }`: the content's metrics, no items.
  - `Fraction::__construct(Node $numerator, Node $denominator, bool $bar = true)`: `bar: false` draws no rule and
    uses the `stack*` constants (`stackTopShiftUp`/`stackTopDisplayStyleShiftUp`, `stackBottomShiftDown`/
    `stackBottomDisplayStyleShiftDown`, `stackGapMin`/`stackDisplayStyleGapMin`) instead of the fraction ones.
  - `Styles::variant(int $cp, string $variant): int` for MathML `mathvariant` values `normal`, `bold`, `italic`,
    `bold-italic`, `double-struck`, `script`, `bold-script`, `fraktur`, `bold-fraktur`, `sans-serif`,
    `bold-sans-serif`, `sans-serif-italic`, `sans-serif-bold-italic`, `monospace`, covering Latin letters, digits
    (where Unicode has them) and Greek (bold, italic, bold-italic, sans-serif bold forms). Use the Unicode
    Mathematical Alphanumeric Symbols block, including its holes (ℎ ℬ ℰ ℱ ℋ ℐ ℒ ℳ ℛ ℯ ℊ ℴ ℭ ℌ ℑ ℜ ℨ ℂ ℍ ℕ ℙ ℚ ℝ ℤ).
    A code point with no styled form is returned unchanged.
  - `Math::space(float $em): Space`, `Math::phantom(string|Node ...$content): Phantom`,
    `Math::binom(string|Node $top, string|Node $bottom): Fenced` (`(` `)` around a barless fraction),
    `Math::stack(string|Node $top, string|Node $bottom): Fraction` (barless), and
    `Math::styled(string $text, string $variant): Identifier`: an upright identifier (`italic: false`) holding the
    mapped code points; `variant` outside the list throws `\InvalidArgumentException`.
- Writers:
  - MathML: `<mspace width="0.167em"/>` (em, up to 3 decimals), `<mphantom>`, `<mfrac linethickness="0">`, styled
    identifiers as their code points in `<mi mathvariant="normal">` (single styled letter) so browsers don't
    re-italicise.
  - OMML: space → `m:r` with Unicode spaces approximating the width (U+2009 for ≤ 0.2em, U+2005 ≤ 0.3em, U+2004
    ≤ 0.4em, U+2002 ≤ 0.6em, U+2003 per whole em; a negative space writes nothing); phantom →
    `<m:phant><m:phantPr><m:show m:val="0"/></m:phantPr><m:e>…</m:e></m:phant>`; barless fraction → `<m:f><m:fPr><m:type m:val="noBar"/></m:fPr>…`;
    styled letters → a run with `<m:rPr><m:sty m:val="p"/></m:rPr>` holding the styled code points.
  - Text: space → `' '` when `em ≥ 0.15`, else `''`; phantom → `''`; barless fraction → `(top; bottom)`; binom
    → `(top; bottom)` inside its parentheses only once (no doubled parentheses).

- [ ] **Step 1:** Write failing tests: `test_space_width` (`Math::space(0.5)` at 12pt is 6pt wide),
  `test_negative_space_pulls_back` (row `a`, space −0.2, `b` is narrower than `a b` by 2.4pt at 12pt),
  `test_phantom_has_metrics_but_no_items`, `test_barless_fraction_has_no_rule` (no `Rule` in items),
  `test_styles_variant` (data provider: `R` double-struck → `ℝ`, `x` bold → `𝐱`, `B` script → `ℬ`, `h` italic →
  `ℎ`, `1` double-struck → `𝟙`, `α` bold → `𝛂`, `+` bold → `+`), `test_styled_rejects_unknown_variant`, plus writer
  cases asserting the exact strings above for each new construct.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement nodes, `Styles::variant`, builder methods and writer cases.
- [ ] **Step 4:** Add the four fixtures to `Fixtures::all()`, run with `UPDATE_SNAPSHOTS=1`, look at each new
  snapshot, then run the full suite. Expected: PASS, old snapshots unchanged.
- [ ] **Step 5:** Commit `feat(math): spaces, phantoms, barless fractions and styled letters`.

### Task 3: Top accent attachment and horizontal stretching

**Files:**
- Modify: `packages/math/src/Font/MathTable.php`, `packages/math/src/Layout/Stretcher.php`
- Test: `tests/Font/MathTableTest.php` (create), `tests/Layout/StretcherTest.php` (create)

**Interfaces:**
- Produces:
  - `MathTable::topAccentAttachment(int $gid): ?int` in font units, read from `MathGlyphInfo` offset +2
    (`MathTopAccentAttachment`: coverage + values, same layout as italics correction). Null when the glyph isn't
    covered.
  - `Stretcher::horizontal(TrueTypeFont $font, int $gid, float $requiredUnits): array{pieces: list<array{int,float}>, xmin: float, xmax: float, ascent: float, descent: float}`:
    pieces are `[gid, x origin offset in font units]`, left to right. Mirrors `vertical()`: natural glyph if wide
    enough, else the first variant whose advance ≥ required, else an assembly from `MathTable::horizontal($gid)->parts`
    (left to right, connector overlap `minConnectorOverlap`), else the largest variant. `ascent`/`descent` are the
    max over pieces from `bbox`.

- [ ] **Step 1:** Write failing tests against STIX Two Math: `test_top_accent_attachment_of_italic_f` (the gid of
  U+1D453 has a non-null attachment greater than half its advance, since italic f leans right),
  `test_horizontal_picks_variant` (U+0302 combining circumflex, required = 2 × its advance: a single piece whose
  advance ≥ required), `test_horizontal_assembles_arrow` (U+2192 →, required 20 em: more than one piece,
  `xmax − xmin` ≥ required − 1 unit), `test_horizontal_without_construction_returns_natural` (gid of `x`).
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3:** Implement. Share the assembly arithmetic with `vertical()` by extracting the part-size solver
  into a private helper that takes the axis.
- [ ] **Step 4:** Run the full suite. Expected: PASS.
- [ ] **Step 5:** Commit `feat(math): top accent attachment and horizontal stretching`.

### Task 4: `UnderOver` layout, large operators, movable limits, accents, bars and the builder

**Files:**
- Create: `packages/math/src/Node/UnderOver.php`
- Modify: `src/Node/Operator.php`, `src/Math.php`, `tests/Fixtures.php`
- Test: `tests/Layout/UnderOverTest.php`

**Interfaces:**
- Consumes: Task 3's `topAccentAttachment`, `Stretcher::horizontal`.
- Produces:
  - `Operator::__construct(string $char, bool $stretchy = false, ?bool $largeop = null, ?bool $movablelimits = null)`;
    null means "from the operator dictionary". `Operator::isLargeop(string $form): bool`,
    `Operator::hasMovableLimits(string $form): bool`.
  - In display style, a large operator draws the first vertical variant (`Stretcher::vertical`) at least
    `displayOperatorMinHeight` tall, centred on the math axis, as `Fenced::delimiter` centres delimiters. Glyph text is
    the operator's character. In inline style it stays at normal size. **This changes display-style ∑/∫/∏ already in
    documents**; report any changed snapshot or golden.
  - `final class UnderOver extends Node { __construct(public readonly Node $base, public readonly ?Node $under, public readonly ?Node $over, public readonly bool $accent = false, public readonly bool $accentUnder = false) }`
    (throws `\InvalidArgumentException` when both parts are null), `children()`: base, under, over.
  - Builder: `Math::under($base, $under)`, `Math::over($base, $over)`, `Math::underOver($base, $under, $over)`,
    `Math::bigOp(string $op, string|Node|null $under = null, string|Node|null $over = null): Node` (no limits →
    the operator alone), `Math::sum(...)`/`Math::product(...)`/`Math::integral(...)` (∑ ∏ ∫, same signature without
    `$op`; `integral` builds `Scripts`, not `UnderOver`, because ∫ keeps its limits beside it),
    `Math::accent(string|Node $base, string $mark): UnderOver` (accent: true), `Math::hat`, `Math::tilde`,
    `Math::vec`, `Math::dot` (each `(string|Node $base)`), `Math::overline($base)`, `Math::underline($base)`,
    `Math::overbrace($base, string|Node|null $label = null)`, `Math::underbrace($base, string|Node|null $label = null)`.
    Mark characters: hat U+0302, tilde U+0303, vec U+20D7, dot U+0307; overline U+00AF (drawn as a bar), underline
    U+005F (bar); braces U+23DE/U+23DF.

**Layout rules** (MathML Core §3.4.3, TeX rules where Core defers to them):
- **Movable limits.** Base is an `Operator` (or a one-child `Row` of one) with movable limits, and the style is
  inline: lay out exactly as `new Scripts($base, $under, $over)`.
- **Large-operator limits** (base a large operator, display style, or base any operator with `largeop` and not
  movable): over baseline sits `max(upperLimitBaselineRiseMin, upperLimitGapMin + over.descent)` above the base's
  ascent; under baseline sits `max(lowerLimitBaselineDropMin, lowerLimitGapMin + under.ascent)` below the base's
  descent. Limits are centred on the base, offset by half the base's italic correction (over right, under left).
- **Stretchy base or part** (base or part is a single stretchy-horizontal `Operator`, e.g. →, ⏞): it stretches with
  `Stretcher::horizontal` to the widest of the other parts; gaps `stretchStackGapAboveMin`/`stretchStackGapBelowMin`,
  shifts `stretchStackTopShiftUp`/`stretchStackBottomShiftDown`.
- **Bars.** An over part that is `Operator('¯')`, `Operator('‾')` or `Operator('_')` (or Math::overline) is drawn as a
  `Rule` the base's width, `overbarRuleThickness` thick, `overbarVerticalGap` above the base, with
  `overbarExtraAscender` added to the ascent. Under bars use the `underbar*` constants.
- **Accent over** (`accent` true): the accent glyph (or its horizontal variant when the base is wider than it, via
  `Stretcher::horizontal`) is placed with its baseline lowered by `max(0, accentBaseHeight − base.ascent)` from the
  base's baseline height, i.e. raised by `base.ascent − min(base.ascent, accentBaseHeight)` relative to an x-height
  base. Horizontal position: the accent's own attachment (or half its width) on the base's attachment when the base
  is a single glyph (`firstGid === lastGid`, use `topAccentAttachment`, falling back to half the width); otherwise
  centred.
- **Anything else over/under:** gap `overbarVerticalGap` / `underbarVerticalGap`, centred, parts at script level
  (`$c->script()`) unless they are accents (accents stay at the base's level).
- Width: the widest of base and parts. Italic correction and `simple` are cleared.

- [ ] **Step 1:** Write failing tests: `test_display_sum_uses_display_variant` (∑ alone in display style is taller
  than in inline, and ≥ `displayOperatorMinHeight` in points), `test_display_limits_above_and_below` (`Math::sum('i=1','n')`
  display: the over part's lowest point is above the ∑'s top by ≥ `upperLimitGapMin`, the under part's top is
  below its bottom by ≥ `lowerLimitGapMin`), `test_inline_sum_puts_limits_as_scripts` (inline: same box as
  `Math::subSup('∑','i=1','n')` laid out inline), `test_integral_keeps_side_scripts_in_display`,
  `test_hat_sits_on_x` (accent box top < base top, accent centred within 0.5pt of the attachment),
  `test_wide_hat_stretches` (`Math::hat('xyz')`: accent glyph wider than over `x` alone),
  `test_overline_is_a_rule_as_wide_as_base`, `test_vec_arrow_stretches_over_ab`,
  `test_overbrace_with_label` (label above the brace, brace as wide as the base), `test_both_parts_null_throws`.
  Add fixtures `sum-display`, `sum-inline`, `integral-display`, `limit-inline` (`lim` with `x→0` under it),
  `x-hat`, `wide-hat`, `x-bar`, `vector-ab`, `overbrace`.
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3:** Implement `Operator` changes, `UnderOver::layout`, the builder methods.
- [ ] **Step 4:** Run `php tests/render-fixtures.php` and look at the new fixtures' SVGs. Fix anything that looks
  wrong against how a browser renders the same MathML (open the MathML snapshot in Firefox or Chrome to compare).
- [ ] **Step 5:** Run the full suite. Expected: PASS. Existing snapshots: unchanged unless a fixture holds a
  display-style large operator; report any change.
- [ ] **Step 6:** Commit `feat(math): limits, large operators, accents and bars`.

### Task 5: Writers for `UnderOver` and large operators

**Files:**
- Modify: `packages/math/src/Render/{MathmlWriter,OmmlWriter,TextWriter}.php`
- Test: `tests/Render/{MathmlWriterTest,OmmlWriterTest,TextWriterTest}.php`, new snapshots for Task 4's fixtures

**Interfaces:**
- Consumes: `UnderOver`, `Operator::isLargeop()`, `Operator::hasMovableLimits()`.
- MathML: `<munder>`, `<mover>`, `<munderover>` with `accent="true"` / `accentunder="true"` when set; an operator with
  an explicit `largeop`/`movablelimits` override writes that attribute.
- OMML:
  - **n-ary:** a `Row` child that is an `UnderOver` or `Scripts` whose base is a large `Operator` becomes
    `<m:nary><m:naryPr><m:chr m:val="∑"/><m:limLoc m:val="undOvr|subSup"/>[<m:subHide m:val="1"/>][<m:supHide m:val="1"/>]</m:naryPr><m:sub>…</m:sub><m:sup>…</m:sup><m:e>…</m:e></m:nary>`.
    `undOvr` for `UnderOver`, `subSup` for `Scripts`. Always write `m:chr`. **Body rule** (as MML2OMML.XSL): the
    next sibling in the row becomes `m:e` and is not written again, unless it is an `Operator` or there is none, in
    which case `m:e` is empty. A large operator alone (no limits) in a row is a plain run.
  - **accent** → `<m:acc><m:accPr><m:chr m:val="X"/></m:accPr><m:e>…</m:e></m:acc>`, with X the combining form:
    `^ ˆ U+0302 → U+0302`, `~ ˜ U+0303 → U+0303`, `→ U+20D7 → U+20D7`, `˙ U+0307 → U+0307`, `¨ U+0308 → U+0308`,
    `ˇ U+030C → U+030C`, `´ U+0301 → U+0301`, `` ` `` U+0300 → U+0300, `˘ U+0306 → U+0306`. Other marks are written as given.
  - **bar** (over part ¯ ‾, under part _) → `<m:bar><m:barPr><m:pos m:val="top|bot"/></m:barPr><m:e>…</m:e></m:bar>`.
  - **brace** (⏞ ⏟ ⎴ ⎵ as the stretched part) → `<m:groupChr><m:groupChrPr><m:chr m:val="⏞"/><m:pos m:val="top|bot"/><m:vertJc m:val="bot|top"/></m:groupChrPr><m:e>…</m:e></m:groupChr>`,
    wrapped in `m:limUpp`/`m:limLow` when there is a label.
  - **anything else** → `<m:limLow><m:e>base</m:e><m:lim>under</m:lim></m:limLow>`, `m:limUpp` for over, and
    `m:limUpp` around `m:limLow` for both.
- Text: limits as scripts (`∑_(i=1)^n`, using the existing script helper), accents and bars as the combining mark
  after a one-character base, else after the base in parentheses (`(xy)̂`), braces as their label as a script.

- [ ] **Step 1:** Write failing writer tests asserting the exact strings for: `Math::sum('i=1','n')` followed by
  `Math::sup('i','2')` in a row (one `m:nary` whose `m:e` holds the `sSup`; the `sSup` not repeated after it),
  `Math::sum('i=1','n')` followed by `+` (empty `m:e`), `Math::integral('0','1')` (`subSup`), a sum with only an
  under part (`m:supHide`), `Math::hat('x')`, `Math::overline('x')`, `Math::underbrace('a+b', 'n')`,
  `Math::over('=', 'def')` (`m:limUpp`); MathML for each; Text: `∑_(i=1)^n i²`, `x̂`, `x̄`.
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run `php bin/omml-check.php` (it writes every `Fixtures::all()` entry into
  `tests/out/omml-check.docx`), then `soffice --headless --convert-to pdf tests/out/omml-check.docx` and look at the
  new fixtures in the PDF. Expected: converts, and each new construct shows as an equation, not a picture or text.
- [ ] **Step 5:** Run the full suite with `UPDATE_SNAPSHOTS=1` for the new fixtures only, inspect, then without.
  Expected: PASS.
- [ ] **Step 6:** Commit `feat(math): write limits, accents and bars as MathML, OMML and text`.

### Task 6: `MathmlReader`: parsing and the supported vocabulary

**Files:**
- Create: `packages/math/src/Mathml/MathmlReader.php`, `packages/math/src/Mathml/MathmlResult.php`,
  `packages/math/src/Node/Styled.php` (and its case in all three writers)
- Modify: `packages/math/composer.json` (`ext-dom`), `src/Math.php`
- Test: `tests/Mathml/MathmlReaderTest.php`

**Interfaces:**
- Consumes: every node from Tasks 1–4.
- Produces:
  - `final readonly class MathmlResult { __construct(public Node $node, public bool $display) }` and
    `MathmlResult::notes(): list<MathNote>` (= `Math::notes($this->node)`).
  - `MathmlReader::read(string|\Dom\Element $input): MathmlResult` (static).
  - `Math::mathml(string|\Dom\Element $input): Node` (= `MathmlReader::read($input)->node`).
- Parsing a string: strip a UTF-8 BOM and a leading `<?xml …?>`; parse with
  `\Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $s, LIBXML_NOERROR)`; take the first element
  in the body whose local name (after the last `:`) is `math`; if none, re-parse with the string wrapped in
  `<math>…</math>`. Element names are always compared by the part after the last `:`, lower-cased.
- Mapping (attributes not mentioned are ignored silently):
  - `math`, `mrow`, `mpadded`, `mstyle`: children as a `Row` (one child → that child; none → empty `Row`).
    `mstyle displaystyle="true|false"` wraps its row in a style switch: implement as a small internal node
    `Styled(Node $content, bool $display)` in `src/Node/` whose layout re-enters with `display` set; writers write the
    content (MathML writer writes `<mstyle displaystyle="…">`).
  - `semantics`: its first element child only; `annotation`, `annotation-xml` never read.
  - `mi`: `Identifier` (`mathvariant="normal"` → upright; another `mathvariant` → `Math::styled`-style mapping
    through `Styles::variant`, upright); `mn`: `Number`; `mtext`: `Text`; `ms`: `Text` wrapped in `"…"`.
  - `mo`: `Operator` with `stretchy`, `largeop`, `movablelimits` attributes as overrides. Invisible operators
    U+2061–U+2064 and an empty `mo` are dropped from the row without a note.
  - `mspace`: `Space` from `width`: units `em`, `ex` (0.5em), `pt`, `px` (0.75pt), `mm`, `cm`, `in` (converted to em
    at 12pt: `pt / 12`), the named spaces `veryverythinmathspace` 1/18 … `veryveryhighmathspace` 7/18 and their
    `negative…` forms. Missing or unparseable width → `Space(0)`.
  - `mphantom`: `Phantom`; `mfrac`: `Fraction`, `linethickness` that parses to zero (`0`, `0px`, `0em`, `0%`) → no bar;
    `msqrt` (children as a row), `mroot`; `msub`/`msup`/`msubsup`: `Scripts`; `munder`/`mover`/`munderover`:
    `UnderOver` with `accent`/`accentunder` attributes; when `accent` is absent, an over part that is a single `mo`
    whose character is in Task 5's accent table is an accent.
  - `mfenced`: `Fenced(open ?? '(', close ?? ')')` around the children joined by separators (`separators` default
    `,`; whitespace removed; the last separator repeats; empty string → none).
  - Element with the wrong number of children (e.g. `mfrac` with one): missing children are empty `Row`s, extra ones
    are appended after the construct in a row; `approximated` note `"<mfrac> with 1 child"`.
  - **Fence pairing** in every row (after mapping its children): scan left to right with a stack; an `Operator`
    that `OperatorDictionary::isOpening()` and isn't `stretchy="false"` pushes; a matching `isClosing()` operator
    pops and replaces the run from opener to closer with `Fenced(open, close, Row(between))`. `|` and `‖` pair with
    the next identical character. Unmatched fences stay plain operators in place. The pairing table:
    `( )`, `[ ]`, `{ }`, `⟨ ⟩`, `⌈ ⌉`, `⌊ ⌋`, `⟪ ⟫`, and also `[ )` `( ]` (interval notation).
- `display`: true when the `math` element has `display="block"` (case-insensitive).

- [ ] **Step 1:** Write failing tests (string input unless noted), each asserting the resulting tree with
  `MathmlWriter::write()` output or node classes, and `notes() === []`:
  `test_reads_quadratic_formula` (MathML for the quadratic formula; written MathML equals the `quadratic-display`
  snapshot's body), `test_reads_dom_element` (an element from an `HTMLDocument`), `test_prefixed_word_mathml`
  (`<mml:math xmlns:mml="…"><mml:mfrac><mml:mi>a</mml:mi><mml:mi>b</mml:mi></mml:mfrac></mml:math>` reads as
  `Fraction`), `test_entities_and_invisible_operators` (`<mi>f</mi><mo>&ApplyFunction;</mo><mo>(</mo><mi>x</mi><mo>)</mo>`:
  no operator node for U+2061, parentheses become `Fenced`), `test_xml_declaration_and_bom`,
  `test_semantics_ignores_tex_annotation` (annotation holds `\frac{a}{b} < c &amp; d`; written MathML contains no
  `frac`), `test_mfenced_with_separators`, `test_fence_pairing` (data provider: `(a)` → Fenced; `( [ a ] )` →
  Fenced in Fenced; `(a` → Row with plain `(`; `|x|` → Fenced; `[0, 1)` → Fenced with `[` and `)`; children
  count and order preserved), `test_mspace_units` (provider over the units above), `test_linethickness_zero`,
  `test_accent_inferred_from_mo`, `test_mathvariant_double_struck`, `test_display_block`,
  `test_mstyle_displaystyle_false_makes_inline_fraction` (layout smaller than display), `test_empty_math`
  (empty `Row`, no notes), `test_wrong_child_count` (one `approximated` note).
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3:** Implement `MathmlReader` as one class with a `match` on the element name, a private depth counter
  and a per-read set of noted element names. Add `ext-dom` to `composer.json` and run `composer validate`.
- [ ] **Step 4:** Run the full math suite and `composer analyse`. Expected: PASS, clean.
- [ ] **Step 5:** Commit `feat(math): read MathML`.

### Task 7: Reader fallback: unknown elements, tables, foreign content, depth

**Files:**
- Modify: `packages/math/src/Mathml/MathmlReader.php`
- Test: `tests/Mathml/MathmlFallbackTest.php`

**Interfaces:**
- Consumes: `Fallback`, `MathNote`, `NoteLevel` (Task 1).
- Every case below wraps what it keeps in one `Fallback` carrying the note; `where` is the element path inside the
  formula, `math > mrow[0] > mtable[1]`-style (local names, element index among element siblings). `element` is the
  local name; `reason` the text in brackets:
  - `mtable`: each `mtr`'s `mtd` contents in one row separated by `Space(1)`, rows separated by `Space(2)`;
    `approximated`, reason `"tables are drawn as rows of cells"`. `mlabeledtr` drops its label cell.
  - `menclose`, `mmultiscripts`, `merror`, `mstack`, `mlongdiv`, `msgroup`, `msrow`, `mscarries`, `msline`, and any
    unknown element: children as a row; `approximated`, reason `"drawn as its contents"`. `mmultiscripts`
    additionally drops `mprescripts` and `none` markers from the row.
  - `maction`: its first child (or the one `selection` names); `approximated`, reason `"drawn as its first choice"`.
  - `mglyph`, and any element in the HTML or SVG namespace (`img`, `svg`, `span`, …): nothing; `dropped`, reason
    `"not drawn"`. Text inside an HTML element nested in `mtext` is kept as text with no note (editors wrap text in
    `<span>`).
  - Nesting deeper than 256 elements: the deepest element's text content as `Text`; `approximated`, element
    `math`, reason `"nesting deeper than 256 levels (flattened)"`, once per read.
- One note per element name per read: later occurrences are drawn the same way inside a `Fallback`-free row.

- [ ] **Step 1:** Write failing tests: `test_mtable_rows_of_cells` (2×2 matrix in parentheses: one note,
  `approximated`, element `mtable`; `TextWriter` gives the four entries in order), `test_two_tables_one_note`,
  `test_menclose_draws_contents`, `test_unknown_element`, `test_maction_first_child`, `test_img_inside_math_dropped`
  (`dropped`, element `img`), `test_span_in_mtext_kept_silently`, `test_depth_limit` (300 nested `mrow`s around `x`:
  one `approximated` note, written text contains `x`), `test_where_path`
  (`math > mrow[0] > mtable[1]` for an mtable as the second element of the first mrow).
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the full suite and `composer analyse`. Expected: PASS.
- [ ] **Step 5:** Commit `feat(math): best-effort reading with notes for what MathML input loses`.

### Task 8: SafeXml and the editor fixture corpus

**Files:**
- Create: `packages/math/src/Mathml/SafeXml.php`, `packages/math/tests/fixtures/editors/*.html`,
  `packages/math/tests/Mathml/EditorFixturesTest.php`, `packages/math/tests/snapshots/editors/{mathml,omml,text}/`
- Test: `tests/Mathml/SafeXmlTest.php`

**Interfaces:**
- Produces:
  - `SafeXml::decode(string $s): string`: `«`→`<`, `»`→`>`, `¨`→`"`, `§`→`&`, `` ` ``→`'`.
  - `SafeXml::find(string $text): list<array{int, int}>`: byte offset and length of each `«math…«/math»` run (case-
    insensitive, `«math` followed by space, `»` or `:`; `«/math»` or a prefixed `«/mml:math»` closes).
  - `MathmlReader::read()` decodes a string that starts (after whitespace) with `«` before parsing.
- **Fixture corpus.** Collect real output for ordinary formulas (quadratic formula, a sum with limits, a definite
  integral, a 2×2 matrix, a vector with an arrow, x̄, a binomial coefficient, √ with an index, a fraction inside
  parentheses, sin²θ + cos²θ = 1) from: MathType/Wiris (plain MathML, `<img class="Wirisformula" data-mathml>` and
  safeXml), MathLive (`convertLatexToMathMl` output), Word's "copy as MathML" (`mml:` prefixed), and CKEditor 5 /
  TinyMCE content with Wiris. Use the editors' public demos, docs or source tests (WebSearch/WebFetch). Each fixture
  file starts with an HTML comment naming the editor, the version or date, and where it came from; where real
  output couldn't be reached, say "reproduced from <source>" instead. Store the HTML fragment exactly as the editor
  writes it.
- `EditorFixturesTest`: for each fixture, find every formula (a `math` element, a Wiris image's `data-mathml`, or
  a safeXml run), read it, and snapshot MathML, OMML and text output. Assert no notes, except fixtures whose file
  name contains `-table` (exactly one `approximated` note).

- [ ] **Step 1:** Write failing `SafeXmlTest`: `test_decode`, `test_find_two_runs_in_text`,
  `test_french_quotes_untouched` (`« bonjour »` → no runs), `test_reader_accepts_safexml`.
- [ ] **Step 2:** Run. Expected: FAIL. Implement `SafeXml` and the reader hook. Run. Expected: PASS.
- [ ] **Step 3:** Collect the fixtures (at least 3 editors, at least 15 formulas in all, all three Wiris forms).
- [ ] **Step 4:** Write `EditorFixturesTest`, generate snapshots with `UPDATE_SNAPSHOTS=1`, and read every
  snapshot. Any reader gap they reveal (an element or attribute the editors use that Tasks 6–7 handle badly) is
  fixed in the reader with its own unit test in `MathmlReaderTest`.
- [ ] **Step 5:** Put the editor formulas through the same `omml-check` + `soffice` route (temporarily add them to
  the script's list, or a scratch copy of it). Expected: converts, equations look right.
- [ ] **Step 6:** Run the full suite and `composer analyse`. Expected: PASS.
- [ ] **Step 7:** Commit `feat(math): safeXml and editor fixtures`.

### Task 9: `papercut/math` README

**Files:**
- Modify: `packages/math/README.md`

- [ ] **Step 1:** Add sections "Reading MathML" (`Math::mathml()`, `MathmlReader::read()`, `display`, notes and
  `Math::notes()`, safeXml, what is approximated or dropped) and the new builder methods with one example each
  (sum with limits, hat, overline, binom, styled letters, space). State that tables are drawn as rows of cells for
  now. Keep the README's existing tone and structure.
- [ ] **Step 2:** Run every code sample in the new sections through a scratch script against the package; each
  must run and produce non-empty MathML.
- [ ] **Step 3:** Commit `docs(math): MathML input and the new constructs`.

## Part B: Papercut core, Laravel, docs

### Task 10: Math notes reach fidelity reports

**Files:**
- Modify: `packages/core/src/Export/NoteReporter.php`
- Create: `packages/core/src/Markup/MathNotes.php`
- Test: `packages/core/tests/Export/MathNotesReportTest.php`

**Interfaces:**
- Consumes: `Math::notes()`, `MathNote`, `NoteLevel`.
- Produces: `MathNotes::of(MathNode $root): list<Note>` (internal): maps each `MathNote` to
  `new Note(FidelityLevel::Approximated|Dropped, 'math <' . $element . '> (' . $reason . ')', $where)`.
  `NoteReporter::enter()` additionally reports `MathNotes::of($node->math())` for an `Equation` and, for a `Text`,
  `MathNotes::of($span->math)` for every span with `math !== null`, using the same path rule as converter notes.

- [ ] **Step 1:** Write failing tests: `test_block_equation_from_mathml_reports_table` (Document with
  `Equation::of(Math::mathml('<math><mtable>…</mtable></math>'))`, PDF export with an `onWarning` collector: exactly
  one message, containing `math <mtable>`), `test_nested_in_builder_fraction`
  (`Equation::of(Math::frac(Math::mathml('<mtable>…'), 'x'))`: one message), `test_inline_span`
  (`Text::spans(Span::text('a '), Span::math(Math::mathml('<menclose>…')))`: one message), `test_strict_mode_fails_on_dropped_only`
  (an `img` inside math under strict mode throws `UnsupportedFeature`; an `mtable` does not), `test_html_and_docx_exports_report_too`.
- [ ] **Step 2:** Run (`vendor/bin/phpunit --filter MathNotesReportTest` in `packages/core`). Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the core suite and `composer analyse`. Expected: PASS.
- [ ] **Step 5:** Commit `feat: report what MathML input loses`.

### Task 11: HTML converter reads `<math>`, Wiris images and safeXml

**Files:**
- Modify: `packages/core/src/Markup/HtmlConverter.php`, `packages/core/src/Markup/InlineRun.php`
- Test: `packages/core/tests/Markup/HtmlMathTest.php`

**Interfaces:**
- Consumes: `MathmlReader::read()`, `MathmlResult`, `SafeXml::find()`/`decode()`, `Equation::of()`,
  `Span::math()`.
- Produces: `InlineRun::math(MathNode $math): void` (appends `Span::math($math)`; `take()` counts a math span as
  visible content).
- Converter rules:
  - An element whose local name after the last `:` is `math`: read it. Empty result (an empty `Row`) → nothing.
    `display` → `flush($preset)` then `$this->blocks[] = [Equation::of($node), false]`. Otherwise `$this->run->math($node)`.
  - `img` whose `class` list contains `Wirisformula` and whose `data-mathml` is non-empty: read `data-mathml`
    (decoded with `SafeXml` when it contains `«`) as inline math. If the read gives an empty `Row`, fall through to the
    normal image path.
  - A text node outside `pre`: split on `SafeXml::find()`; text between runs goes to `run->text()` as now; each run
    is decoded and read like a `math` element (block or inline by its `display`).
  - Reader notes are **not** added to the converter's notes (Task 10 reports them from the tree).
  - `math` is never reported as an unknown tag.

- [ ] **Step 1:** Write failing tests: `test_inline_math_in_paragraph` (`<p>Area <math><mi>π</mi><msup><mi>r</mi><mn>2</mn></msup></math>.</p>`:
  one `Text` with three spans, the middle one math), `test_paragraph_with_only_math_kept`,
  `test_block_math_splits_paragraph` (`<p>a <math display="block">…</math> b</p>` → Text, Equation, Text),
  `test_word_prefixed_math`, `test_wiris_image_plain_and_safexml` (both become inline math, no image, no note),
  `test_wiris_image_without_mathml_stays_image`, `test_safexml_text_runs` (two formulas inside one text node, text
  around them kept), `test_french_quotes_untouched`, `test_mtable_note_reported_once` (convert, put in a Document,
  export with a collector: one note), `test_no_unknown_tag_note_for_math`.
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the core suite, the HTML samples test and `composer analyse`. Expected: PASS, goldens unchanged.
- [ ] **Step 5:** Commit `feat: MathML in converted HTML, including MathType's storage forms`.

### Task 12: Blade reads MathML strings

**Files:**
- Modify: `packages/laravel/src/Components/BuildsEquation.php`
- Test: `packages/laravel/tests/Feature/EquationComponentTest.php` (add cases)

**Interfaces:**
- Consumes: `Math::mathml()`.
- Rule: a string `math` attribute matching `/^\s*(<|«)([\w-]+:)?math[\s>»:]/iu` is read with `Math::mathml()`;
  any other string goes to `Equation::of()` as now. Block/inline is decided by the component, as today.

- [ ] **Step 1:** Write failing tests: `test_equation_with_mathml_string` (renders a `Fraction`),
  `test_inline_math_with_mathml_string_stays_inline_even_with_display_block`, `test_safexml_string`,
  `test_plain_string_still_tokenized` (`math="x² + 1"`), `test_mtable_note_reported_once` (export from a Blade view
  with an `onWarning` collector).
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the laravel suite. Expected: PASS.
- [ ] **Step 5:** Commit `feat(laravel): MathML strings in equation and math components`.

### Task 13: Docs, example, changelog

**Files:**
- Modify: `site/src/guide/equations.md`, `examples/core/documents/paper.php` (or a new short generic example if
  `paper.php` doesn't suit), `CHANGELOG.md`, the example's goldens

- [ ] **Step 1:** Equations page: a "MathML input" section (the converter, `Math::mathml()`, Blade strings; MathType
  storage forms; what's approximated and dropped and how it's reported; editors tested) and the new builder methods.
  Remove "MathML input" from any "not yet supported" list.
- [ ] **Step 2:** Example: add a sum with limits and one MathML formula read through `Markup::html()` to
  `paper.php`. Regenerate its goldens and report the change. Keep the example's paper size.
- [ ] **Step 3:** CHANGELOG: an `## [Unreleased]` section with Added entries for MathML input (converter, builder,
  Blade, Wiris forms), the new constructs and builder methods, and Changed: display-style large operators now draw at
  display size.
- [ ] **Step 4:** Run `npm run build` in `site/`. Expected: builds with no errors or dead links.
- [ ] **Step 5:** Commit `docs: MathML input, limits, accents and bars`.

### Task 14: Final verification and the user's visual check

- [ ] **Step 1:** Run every package suite and `composer analyse` in `math`, `core`, `docx`, `laravel`. Expected: all
  pass, clean.
- [ ] **Step 2:** Render a check document to the scratchpad: every editor fixture through the converter to PDF,
  DOCX and HTML. Open the DOCX in LibreOffice and run the ooxml validator on it; open the HTML in a browser.
- [ ] **Step 3:** Ask the user to look at the PDF and DOCX (they have no Word: LibreOffice and Google Docs are the
  checks). Fix what they find, each fix with a test.
