# Papercut Plan 2: Component Tree & Page-Aware Layout Engine

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the plain-PHP public API of `papercut/core`: immutable element nodes (`Document`, `Page`, `View`/`Row`/`Column`, `Text`, `Image`, `Table`, `Spacer`, `PageNumber`, `PageBreak`, `Link`), styles with inheritance and a theme, and a page-aware layout engine (text wrapping, flexbox, tables, pagination with break tokens). `Document::make(...)->save()` produces correct multi-page PDFs.

**Architecture:** Nodes are data. `ComputedStyle` resolves inherited text properties and theme defaults. Every node type has a layouter implementing one contract: `layout(node, style, constraints, ?BreakToken) → LayoutResult (Complete | Partial + token | DoesNotFit)`. Layouters produce `Fragment` trees in local coordinates. The `Paginator` drives the page loop and header/footer placement. The `Painter` flattens fragments into Plan 1's `PageDisplayList`s and resolves `{page}`/`{total}` placeholders. Rendering goes through Plan 1's `Backend`.

**Tech Stack:** PHP 8.4, PHPUnit ^13.4, PHPStan ^2.3 (level 8). Plan 1's `Papercut\Value\*`, `Papercut\Paint\*`, `Papercut\Backend\*`, `Papercut\Text\{FontRegistry,FontStyle,CachedFont}`.

**Spec:** `docs/superpowers/specs/2026-10-18-papercut-design.md` (§6.2–6.5, §7.1–7.3, §7.6, §8)

## Global Constraints

- Everything from Plan 1's Global Constraints still holds: PHP `^8.4`, `declare(strict_types=1)`, `Papercut\` namespace, only `src/Backend/PhpPdf/` may reference `Dskripchenko\`, exceptions extend `Papercut\PapercutException`, geometry in float points with top-left origin.
- Public element classes live directly in `Papercut\` (spec §6.2: `use Papercut\{Document, Page, Column, Row, Text, Image, Table, Spacer, PageNumber};`). Internals live in `Papercut\Style\`, `Papercut\Layout\`, `Papercut\Text\`, `Papercut\Paint\`.
- Nodes are immutable: every modifier returns a new instance (`clone` + set), and the original is unchanged.
- Tag vocabulary ↔ node vocabulary is 1:1 (spec §6.2 rule). Every style modifier has a string-accepting form via `Style::fromAttributes()` (Task 1). Plan 3's Blade components rely on it.
- Units: image intrinsic size uses 96 dpi (1px = 0.75pt), matching Plan 1's `px` unit.
- Theme defaults (exact): font family `Inter`, font size `10pt`, weight `400`, color `slate-900` (`#0f172a`), line height `1.4`, text align left. Headings: h1 24pt/700, h2 18pt/700, h3 14pt/700, h4 12pt/700; each heading has `margin-bottom` = 0.5 × its font size; `p` preset has `margin-bottom: 6pt`. Table cell default padding `4pt 6pt`. `min-lines` default `2`. Table `repeat-header` default on.
- Default overflow mode: `OverflowMode::Throw`.
- Determinism: same input + fixed `createdAt` → identical bytes.
- Run tests: `php -d xdebug.mode=off packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist`.

## Review Focus

1. **Blade/indentation whitespace inside text** (`"\n      Hello\n   world  "`) must collapse to `"Hello world"`, with no leading or trailing space drawn, exactly like CSS `white-space: normal`. Test: Task 4.
2. **A single word wider than its container** (a long URL in a narrow table cell) must break across lines by character, never overflow or loop forever. Test: Task 4.
3. **An empty container or empty text** (`Column::make()`, `Text::make('')`) must lay out with zero content height (plus padding) and not throw. Test: Task 6 (column), Task 5 (text).
4. **A table with more columns than tracks, or fewer** (3 cells, `columns("1fr 1fr")`) must not crash: missing tracks default to `1fr`, extra tracks are ignored, and the case is reported through the warning callback. Test: Task 9.
5. **Content that can never fit** (an image taller than the page body) must throw `LayoutOverflow` naming the node path in Throw mode, and must clip with a warning in Clip mode. It must never loop and never emit an infinite number of pages. Test: Task 10.

---

## File Structure

```
packages/core/src/
  Node.php                 interface Node (marker + debugName())
  Component.php            interface Component { render(): Node }
  Document.php  Page.php
  View.php  Row.php  Column.php            Row/Column are factories returning View
  Text.php  Span.php                       paragraph + inline spans
  Image.php  Spacer.php  PageNumber.php  PageBreak.php  Link.php
  Table.php  TableRow.php  TableCell.php
  RenderOptions.php  OverflowMode.php
  Concerns/HasStyle.php                    shared style modifiers (trait)
  Exception/LayoutOverflow.php
  Style/
    Style.php  ComputedStyle.php  Theme.php
    Direction.php Justify.php Align.php TextAlign.php BorderStyle.php ImageFit.php
  Text/
    InlineMarkup.php       "<b>x</b>" → list<Span>
    InlineItem.php         word/space/break items with resolved font + width
    Itemizer.php           spans → items (whitespace collapse, font fallback)
    LineBreaker.php        items + width → lines
    Line.php
  Layout/
    Constraints.php  LayoutResult.php  Status.php  BreakToken.php  Fragment.php
    LayoutContext.php      fonts, theme, images, warnings, node path, overflow mode
    LayoutEngine.php       dispatch by node type + Component expansion
    BoxGeometry.php        padding/border/margin/size resolution + decoration painting
    TextLayouter.php  BlockLayouter.php  FlexLayouter.php  TableLayouter.php
    ImageLayouter.php  LeafLayouter.php  LinkLayouter.php
    Intrinsic.php          min-content / max-content widths
    Paginator.php
  Paint/
    Painter.php            fragments → PageDisplayList, placeholder resolution
    PageNumberSlot.php     placeholder carried in fragments
packages/core/tests/
  Support/LayoutHarness.php   lay out a node with FakeFont at a given width/height
  Style/... Node/... Text/... Layout/... Feature/...
```

---

### Task 1: Style, enums, Theme, ComputedStyle

**Files:**
- Create: `src/Style/{Style,ComputedStyle,Theme,Direction,Justify,Align,TextAlign,BorderStyle,ImageFit}.php`
- Test: `tests/Style/StyleTest.php`, `tests/Style/ComputedStyleTest.php`, `tests/Style/ThemeTest.php`

**Interfaces:**
- Produces enums (string-backed, values are the Blade attribute spellings):
  - `Direction: Row='row', Column='column'`
  - `Justify: Start='start', End='end', Center='center', SpaceBetween='space-between', SpaceAround='space-around', SpaceEvenly='space-evenly'`
  - `Align: Start='start', End='end', Center='center', Stretch='stretch'`
  - `TextAlign: Left='left', Right='right', Center='center', Justify='justify'`
  - `BorderStyle: Solid='solid', Dashed='dashed'`
  - `ImageFit: Contain='contain', Cover='cover', Fill='fill'`
- Produces `final readonly class Style`. All properties are nullable (null = not set) and are constructor-promoted, named-arg friendly:
  - box: `Length|Percent|Auto|null $width, $height; Length|Percent|null $minWidth, $maxWidth, $minHeight, $maxHeight; ?Edges $padding, $margin, $borderWidth; ?Color $borderColor; ?BorderStyle $borderStyle; ?Length $radius; ?Color $background; ?float $opacity`
  - flex: `?Direction $direction; ?Length $gap; ?Justify $justify; ?Align $alignItems; ?Align $alignSelf; ?float $grow; ?float $shrink; Length|Percent|Auto|null $basis; ?bool $wrap`
  - text (inherited): `?string $fontFamily; ?Length $fontSize; ?int $fontWeight; ?bool $italic; ?Color $color; ?float $lineHeight; ?Length $letterSpacing; ?TextAlign $textAlign; ?bool $underline; ?bool $strike`
  - pagination: `?bool $keepTogether; ?bool $keepWithNext; ?bool $breakBefore; ?bool $breakAfter; ?int $minLines`
  - Methods: `static make(...)` (same signature as the constructor), `merge(Style $over): Style` (each non-null property of `$over` wins), `isEmpty(): bool`.
  - `static fromAttributes(array<string, string|int|float|bool|object> $attrs): Style`. Keys are kebab-case Blade attribute names: `width height min-width max-width min-height max-height padding margin border-width border-color border-style radius background opacity direction gap justify align-items align-self grow shrink basis wrap font font-size weight italic bold color line-height letter-spacing text-align underline strike keep-together keep-with-next break-before break-after min-lines`. `bold` maps to `fontWeight: 700` (`bold=false` maps to 400). `font` maps to `fontFamily`. String values are parsed with Plan 1 value parsers. Width/height/basis accept `auto` or a percentage. Booleans accept `true`, `"true"`, `"false"`, `""` (presence means true). Unknown key → `Papercut\Exception\UnknownAttribute` (new: `final class UnknownAttribute extends PapercutException`, built like `InvalidValue`: `static for(string $name, ?string $suggestion = null, string $noun = "attribute"): self`, readonly `$name`, `$suggestion`, `$context`, and `withContext(string $context): self` returning a new instance with the original as previous. Message: `Unknown <noun> "<name>"` + (context ? ` <context>` : "") + `.` + (suggestion ? ` Did you mean "<suggestion>"?` : ""), e.g. `Unknown attribute "paddng" on <x-pdf::column>. Did you mean "padding"?`. Suggestion via levenshtein ≤ 2.) Add `tests/Exception/UnknownAttributeTest.php` covering message with and without context and suggestion. An invalid value rethrows `InvalidValue::withContext('<key>')`. Already-typed values (Length, Color, enums …) pass through unchanged.
- Produces `final class Theme`:
  - `static default(): self` with the exact defaults from Global Constraints.
  - Readonly public props: `string $fontFamily; float $fontSize; int $fontWeight; Color $color; float $lineHeight; Edges $cellPadding; int $minLines`.
  - `heading(int $level): Style` (1–4, else `\InvalidArgumentException`); `paragraph(): Style`.
  - `withStyle(string $name, Style $style): self` (new instance); `named(string $name): Style`. Unknown name → `UnknownAttribute('Unknown style "<name>".')` with suggestion.
  - `with(...)` named-arg overrides for the readonly props (returns a new Theme).
- Produces `final readonly class ComputedStyle`. Every property is resolved and non-null except the size constraints:
  - box (not inherited): `Length|Percent|Auto $width, $height; Length|Percent|null $minWidth…$maxHeight; Edges $padding, $margin, $borderWidth; Color $borderColor (default black); BorderStyle $borderStyle (Solid); float $radius; ?Color $background; float $opacity (1.0)`
  - flex: `Direction $direction (Column); float $gap (0); Justify $justify (Start); Align $alignItems (Stretch); ?Align $alignSelf; float $grow (0); float $shrink (1); Length|Percent|Auto $basis (Auto); bool $wrap (false)`
  - text (inherited from parent, root from Theme): `string $fontFamily; float $fontSize; int $fontWeight; bool $italic; Color $color; float $lineHeight; float $letterSpacing; TextAlign $textAlign; bool $underline; bool $strike`
  - pagination (not inherited): `bool $keepTogether, $keepWithNext, $breakBefore, $breakAfter` (false); `int $minLines` (inherited; root from theme)
  - `static root(Theme $theme): self`; `child(Style $style): self` (inherit text props + minLines from `$this`; reset box/flex/pagination props to defaults, then apply `$style`).
  - `float lineHeightPoints()` = `lineHeight * fontSize`.

- [ ] **Step 1: Write failing tests**

`tests/Style/StyleTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Style;

use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Style\{Align, Direction, Justify, Style, TextAlign};
use Papercut\Value\{Auto, Color, Edges, Length, Percent};
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class StyleTest extends TestCase
{
    public function test_merge_prefers_non_null_override(): void
    {
        $base = Style::make(padding: Edges::all(pt(4)), color: Color::black(), grow: 1.0);
        $merged = $base->merge(Style::make(color: Color::white()));
        self::assertSame(4.0, $merged->padding?->top->points);
        self::assertSame('#ffffff', (string) $merged->color);
        self::assertSame(1.0, $merged->grow);
        self::assertTrue(Style::make()->isEmpty());
        self::assertFalse($base->isEmpty());
    }

    public function test_from_attributes_parses_strings(): void
    {
        $s = Style::fromAttributes([
            'padding' => '12pt 6pt',
            'width' => '50%',
            'height' => 'auto',
            'color' => 'slate-700',
            'direction' => 'row',
            'justify' => 'space-between',
            'align-items' => 'center',
            'text-align' => 'right',
            'gap' => '8pt',
            'grow' => '1',
            'bold' => '',
            'keep-together' => 'true',
            'italic' => 'false',
            'min-lines' => '3',
            'line-height' => '1.6',
            'font' => 'Inter',
            'font-size' => '12pt',
            'opacity' => '0.5',
        ]);
        self::assertSame(6.0, $s->padding?->right->points);
        self::assertInstanceOf(Percent::class, $s->width);
        self::assertInstanceOf(Auto::class, $s->height);
        self::assertSame('#334155', (string) $s->color);
        self::assertSame(Direction::Row, $s->direction);
        self::assertSame(Justify::SpaceBetween, $s->justify);
        self::assertSame(Align::Center, $s->alignItems);
        self::assertSame(TextAlign::Right, $s->textAlign);
        self::assertSame(1.0, $s->grow);
        self::assertSame(700, $s->fontWeight);
        self::assertTrue($s->keepTogether);
        self::assertFalse($s->italic);
        self::assertSame(3, $s->minLines);
        self::assertSame(1.6, $s->lineHeight);
        self::assertSame(12.0, $s->fontSize?->points);
        self::assertSame(0.5, $s->opacity);
    }

    public function test_typed_values_pass_through(): void
    {
        $len = pt(3);
        self::assertSame($len, Style::fromAttributes(['gap' => $len])->gap);
        self::assertSame(Direction::Column, Style::fromAttributes(['direction' => Direction::Column])->direction);
    }

    public function test_unknown_attribute_suggests(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "paddng". Did you mean "padding"?');
        Style::fromAttributes(['paddng' => '4pt']);
    }

    public function test_invalid_value_carries_attribute_context(): void
    {
        try {
            Style::fromAttributes(['margin' => '20 mm']);
            self::fail('expected');
        } catch (InvalidValue $e) {
            self::assertSame('Invalid edges "20 mm" for margin. Did you mean "20mm"?', $e->getMessage());
        }
    }

    public function test_invalid_enum_value(): void
    {
        $this->expectException(InvalidValue::class);
        Style::fromAttributes(['justify' => 'middle']);
    }

    public function test_grow_must_be_numeric_non_negative(): void
    {
        $this->expectException(InvalidValue::class);
        Style::fromAttributes(['grow' => '-1']);
    }

    public function test_length_or_auto_values(): void
    {
        self::assertInstanceOf(Length::class, Style::fromAttributes(['basis' => '20mm'])->basis);
    }
}
```

`tests/Style/ComputedStyleTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Style;

use Papercut\Style\{Align, ComputedStyle, Direction, Style, TextAlign, Theme};
use Papercut\Value\{Auto, Color, Edges};
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class ComputedStyleTest extends TestCase
{
    public function test_root_comes_from_theme(): void
    {
        $root = ComputedStyle::root(Theme::default());
        self::assertSame('Inter', $root->fontFamily);
        self::assertSame(10.0, $root->fontSize);
        self::assertSame(400, $root->fontWeight);
        self::assertSame('#0f172a', (string) $root->color);
        self::assertSame(1.4, $root->lineHeight);
        self::assertEqualsWithDelta(14.0, $root->lineHeightPoints(), 1e-9);
        self::assertSame(2, $root->minLines);
        self::assertSame(TextAlign::Left, $root->textAlign);
        self::assertSame(Direction::Column, $root->direction);
        self::assertSame(Align::Stretch, $root->alignItems);
        self::assertInstanceOf(Auto::class, $root->width);
    }

    public function test_text_props_inherit_box_props_do_not(): void
    {
        $parent = ComputedStyle::root(Theme::default())->child(Style::make(
            color: Color::white(),
            fontSize: pt(14),
            padding: Edges::all(pt(10)),
            background: Color::black(),
            keepTogether: true,
            minLines: 3,
        ));
        $child = $parent->child(Style::make());
        self::assertSame('#ffffff', (string) $child->color);
        self::assertSame(14.0, $child->fontSize);
        self::assertSame(3, $child->minLines);
        self::assertSame(0.0, $child->padding->top->points);
        self::assertNull($child->background);
        self::assertFalse($child->keepTogether);
    }

    public function test_letter_spacing_resolves_to_points(): void
    {
        $c = ComputedStyle::root(Theme::default())->child(Style::make(letterSpacing: pt(1.5)));
        self::assertSame(1.5, $c->letterSpacing);
    }
}
```

`tests/Style/ThemeTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Style;

use Papercut\Exception\UnknownAttribute;
use Papercut\Style\{Style, Theme};
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    public function test_heading_presets(): void
    {
        $t = Theme::default();
        $sizes = [1 => 24.0, 2 => 18.0, 3 => 14.0, 4 => 12.0];
        foreach ($sizes as $level => $size) {
            $h = $t->heading($level);
            self::assertSame($size, $h->fontSize?->points);
            self::assertSame(700, $h->fontWeight);
            self::assertSame($size * 0.5, $h->margin?->bottom->points);
        }
        self::assertSame(6.0, $t->paragraph()->margin?->bottom->points);
    }

    public function test_named_styles_are_immutable_additions(): void
    {
        $t = Theme::default();
        $t2 = $t->withStyle('card', Style::make(background: Color::white()));
        self::assertSame('#ffffff', (string) $t2->named('card')->background);
        $this->expectException(UnknownAttribute::class);
        $t->named('card');
    }

    public function test_unknown_style_suggestion(): void
    {
        $t = Theme::default()->withStyle('card', Style::make());
        $this->expectExceptionMessage('Unknown style "crad". Did you mean "card"?');
        $t->named('crad');
    }

    public function test_with_overrides(): void
    {
        $t = Theme::default()->with(fontSize: 11.0, fontFamily: 'Brand');
        self::assertSame(11.0, $t->fontSize);
        self::assertSame('Brand', $t->fontFamily);
        self::assertSame(10.0, Theme::default()->fontSize);
    }

    public function test_bad_heading_level(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Theme::default()->heading(5);
    }
}
```

- [ ] **Step 2: Run** → FAIL (classes missing).

- [ ] **Step 3: Implement.** Implementation notes:
  - `Style::merge`: `new self(...array_merge(get_object_vars($this), array_filter(get_object_vars($over), fn ($v) => $v !== null)))`.
  - `Style::fromAttributes`: a private const map from attribute key to `[property, parser]`, where the parser is one of `edges`, `length`, `lengthPercentAuto`, `lengthPercent`, `color`, `enum:<class>`, `float>=0`, `float01` (opacity), `int>=1`, `bool`, `string`. Kebab→camel mapping: `font`→`fontFamily`, `weight`→`fontWeight`, `bold`→special. Unknown keys: suggest using levenshtein ≤ 2 over the map keys.
  - Boolean parsing: `true`/`''`/`'true'`/`'1'` → true; `false`/`'false'`/`'0'` → false; anything else → `InvalidValue::for('boolean', …)`.
  - Enum parsing: `Enum::tryFrom($v)`; on failure `InvalidValue::for('<attr> value', $v, <closest case value, levenshtein ≤ 2, else null>)`, then `->withContext($key)`.
  - `ComputedStyle::child`: build defaults for non-inherited props, copy inherited ones from `$this`, then override with each non-null `$style` property, converting `Length` → float points for `fontSize`, `letterSpacing`, `gap`, `radius`.
- [ ] **Step 4: Run** → PASS. PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add Style, Theme and ComputedStyle with attribute parsing`

---

### Task 2: Element nodes and style modifiers

**Files:**
- Create: `src/Node.php`, `src/Component.php`, `src/Concerns/HasStyle.php`, `src/View.php`, `src/Row.php`, `src/Column.php`, `src/Text.php`, `src/Span.php`, `src/Image.php`, `src/Spacer.php`, `src/PageNumber.php`, `src/PageBreak.php`, `src/Link.php`
- Test: `tests/Node/NodesTest.php`

**Interfaces:**
- `interface Node { public function debugName(): string; public function style(): Style; }`. `debugName()` values: `view`, `row`, `column`, `text`, `image`, `spacer`, `page-number`, `page-break`, `link`, `table`, `tr`, `td`, `th`, `page`.
- `interface Component { public function render(): Node|Component; }`
- `trait HasStyle`: holds `private Style $style` and `private ?string $styleName = null`. Provides `style(): Style`, `styleName(): ?string`, and the protected `withStyle(Style $patch): static` (clone + merge). Public modifiers, each returning `static`:
  `width(Length|Percent|Auto)`, `height(...)`, `minWidth(Length|Percent)`, `maxWidth`, `minHeight`, `maxHeight`, `padding(Length|Edges ...$v)` (1–4 Lengths or one Edges), `margin(...)` (same), `border(Length $width, ?Color $color = null, ?BorderStyle $style = null)`, `borderWidth(Edges|Length)`, `borderColor(Color)`, `radius(Length)`, `background(Color|string)`, `opacity(float)`, `grow(float = 1)`, `shrink(float)`, `basis(Length|Percent|Auto)`, `alignSelf(Align)`, `font(string $family)`, `size(Length)`, `weight(int)`, `bold(bool = true)`, `italic(bool = true)`, `color(Color|string)`, `lineHeight(float)`, `letterSpacing(Length)`, `textAlign(TextAlign)`, `left()`, `center()`, `right()`, `justify()` (the last four set textAlign), `underline(bool = true)`, `strike(bool = true)`, `keepTogether(bool = true)`, `keepWithNext(bool = true)`, `breakBefore(bool = true)`, `breakAfter(bool = true)`, `minLines(int)`, `apply(Style|string $style)` (a string stores a theme style *name* resolved at layout time; a `Style` merges immediately), `attributes(array $attrs)` (merges `Style::fromAttributes($attrs)`; used by Blade). String color arguments go through `Color::parse`.
- `final class View implements Node`: `static make(Node|Component|string ...$children): self` (strings become `Text::make($s)`); `children(): list<Node|Component>`; `withChildren(Node|Component|string ...$c): self`; flex modifiers `direction(Direction)`, `gap(Length)`, `justify(Justify)`, `align(Align)` (sets alignItems), `wrap(bool = true)`. Constructed with style `direction: Column` by default; `debugName()` is `column` or `row` from the direction, unless it was built by `View::make` without an explicit direction (then `view`).
- `final class Row { static make(Node|Component|string ...$children): View }`: a View with `direction: Row`, `alignItems: Stretch`. `final class Column { static make(...): View }`: a View with `direction: Column`.
- `final readonly class Span { __construct(string $text, Style $style = new Style(), ?string $href = null, bool $lineBreak = false) }`: `static text(string, ?Style = null)`, `static break()`.
- `final class Text implements Node`:
  - `static make(string $text = '')`; `static spans(Span ...$spans)`; `static markup(string $markup)` (uses `Text\InlineMarkup::parse`, Task 4; until then this throws `\LogicException`, and Task 4 wires it).
  - `static h1(string)`, `h2`, `h3`, `h4`, `p(string)`: preset markers stored as `private ?string $preset` (`'h1'…'h4'`, `'p'`), resolved via the Theme at layout time; `preset(): ?string`; `withPreset(string $name): self` (accepts `h1`–`h4` and `p`, else `\InvalidArgumentException`) so markup text can take a preset: `Text::markup($m)->withPreset("h2")`.
  - `spanList(): list<Span>`; `append(string|Span ...$more): self`.
- `final class Image implements Node`: `static file(string $path)`, `static bytes(string $bytes)`, `static handle(ImageHandle $h)`; `fit(ImageFit)`; `source(): array{kind: 'file'|'bytes'|'handle', value: string|ImageHandle}`; `fitMode(): ImageFit` (default Contain).
- `final class Spacer implements Node`: `static size(Length $l)` (fixed main-axis size), `static flex(float $grow = 1)`; `fixedSize(): ?Length`.
- `final class PageNumber implements Node`: `static make(string $format = '{page}')`; `format(): string`.
- `final class PageBreak implements Node`: `static make()`.
- `final class Link implements Node`: `static to(string $uri, Node|Component|string ...$children)`; `uri(): string`; `children(): list<Node|Component>`. Lays out like a column View.

- [ ] **Step 1: Write failing tests** (`tests/Node/NodesTest.php`):

```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Node;

use Papercut\{Column, Component, Image, Link, Node, PageBreak, PageNumber, Row, Spacer, Span, Text, View};
use Papercut\Style\{Align, Direction, ImageFit, Justify, Style, TextAlign};
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

use function Papercut\{mm, pt};

final class NodesTest extends TestCase
{
    public function test_modifiers_return_new_instances(): void
    {
        $a = Text::make('Hi');
        $b = $a->bold()->color('red-500')->size(pt(14));
        self::assertNotSame($a, $b);
        self::assertNull($a->style()->fontWeight);
        self::assertSame(700, $b->style()->fontWeight);
        self::assertSame('#ef4444', (string) $b->style()->color);
        self::assertSame(14.0, $b->style()->fontSize?->points);
    }

    public function test_padding_shorthand(): void
    {
        $v = View::make()->padding(pt(1), pt(2));
        self::assertSame([1.0, 2.0, 1.0, 2.0], [
            $v->style()->padding?->top->points, $v->style()->padding?->right->points,
            $v->style()->padding?->bottom->points, $v->style()->padding?->left->points,
        ]);
    }

    public function test_row_and_column_presets_and_string_children(): void
    {
        $row = Row::make('a', Text::make('b'))->gap(pt(4))->justify(Justify::SpaceBetween)->align(Align::Center);
        self::assertSame(Direction::Row, $row->style()->direction);
        self::assertSame('row', $row->debugName());
        self::assertInstanceOf(Text::class, $row->children()[0]);
        self::assertSame(Align::Center, $row->style()->alignItems);
        self::assertSame('column', Column::make()->debugName());
        self::assertSame('view', View::make()->debugName());
    }

    public function test_text_presets_and_alignment(): void
    {
        self::assertSame('h2', Text::h2('Title')->preset());
        self::assertSame('h3', Text::make('x')->withPreset('h3')->preset());
        self::assertNull(Text::make('x')->preset());
        self::assertSame(TextAlign::Right, Text::make('x')->right()->style()->textAlign);
        $t = Text::make('Hello ')->append(Span::text('world', Style::make(fontWeight: 700)));
        self::assertCount(2, $t->spanList());
    }

    public function test_apply_named_and_object_styles(): void
    {
        $v = View::make()->apply('card')->apply(Style::make(background: Color::white()));
        self::assertSame('card', $v->styleName());
        self::assertSame('#ffffff', (string) $v->style()->background);
    }

    public function test_attributes_bridge(): void
    {
        $v = View::make()->attributes(['padding' => '4pt', 'direction' => 'row']);
        self::assertSame(Direction::Row, $v->style()->direction);
        self::assertSame(4.0, $v->style()->padding?->left->points);
    }

    public function test_leaf_nodes(): void
    {
        self::assertSame(ImageFit::Cover, Image::file('x.png')->fit(ImageFit::Cover)->fitMode());
        self::assertSame(ImageFit::Contain, Image::bytes('...')->fitMode());
        self::assertSame(['kind' => 'file', 'value' => 'x.png'], Image::file('x.png')->source());
        self::assertSame(10.0, Spacer::size(pt(10))->fixedSize()?->points);
        self::assertSame(2.0, Spacer::flex(2)->style()->grow);
        self::assertSame('Page {page} of {total}', PageNumber::make('Page {page} of {total}')->format());
        self::assertSame('page-break', PageBreak::make()->debugName());
        self::assertSame('https://x.test', Link::to('https://x.test', 'Go')->uri());
    }

    public function test_components_are_accepted_as_children(): void
    {
        $c = new class implements Component {
            public function render(): Node { return Text::make('from component'); }
        };
        self::assertSame($c, Column::make($c)->children()[0]);
    }

    public function test_image_width_with_mm(): void
    {
        self::assertEqualsWithDelta(mm(10)->points, Image::file('a.png')->width(mm(10))->style()->width?->points ?? 0, 1e-9);
    }
}
```
Note: `Style::$width` is `Length|Percent|Auto|null`. Accessing `->points` in the last assertion is guarded by the `?? 0`; PHPStan may need an `assert($w instanceof Length)`. Adjust the test accordingly if analysis complains.

- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add element nodes with immutable style modifiers`

---

### Task 3: Table, TableRow, TableCell, Page, Document skeleton

**Files:**
- Create: `src/Table.php`, `src/TableRow.php`, `src/TableCell.php`, `src/Page.php`, `src/Document.php` (no rendering yet; `save/toString/stream` arrive in Task 11), `src/RenderOptions.php`, `src/OverflowMode.php`
- Test: `tests/Node/TableTest.php`, `tests/Node/PageDocumentTest.php`

**Interfaces:**
- `final class TableCell implements Node`: `static make(Node|Component|string ...$children)`; `colspan(int)` (≥ 1); `span(): int`; `children()`; `header(): bool` (true for `th`); `static header(...)` creates a th; `align(TextAlign)` sets textAlign. `debugName()` is `td` or `th`.
- `final class TableRow implements Node`: `static make(TableCell|Node|Component|string ...$cells)` (non-cells are wrapped in `TableCell::make`); `cells(): list<TableCell>`; `debugName()` `tr`.
- `final class Table implements Node`:
  - `static make()`; `columns(TrackList|string|Length|Percent|Fr|Auto ...$tracks)` (one string → `TrackList::parse`); `trackList(): ?TrackList`.
  - `head(TableCell|Node|Component|string ...$cells)`: adds one header row whose plain cells become `th`. `headerRow(TableRow $r)`.
  - `row(TableCell|Node|Component|string ...$cells)`; `rows(iterable $items, callable $map)`, where `$map(item, index)` returns a `list<TableCell|Node|string|int|float>` (scalars cast to string).
  - `repeatHeader(bool = true)`; `repeatsHeader(): bool` (default true).
  - `headerRows(): list<TableRow>`; `bodyRows(): list<TableRow>`.
- `final class Page implements Node`:
  - `static a3()`, `a4()`, `a5()`, `a6()`, `letter()`, `legal()`, and `static size(Length $w, Length $h)`. `landscape()`, `portrait()`. `static named(string $name)` takes `a3|a4|a5|a6|letter|legal`, case-insensitive (else `InvalidValue::for('page size', …)`).
  - Dimensions in pt (portrait): A3 841.89×1190.55, A4 595.28×841.89, A5 419.53×595.28, A6 297.64×419.53, Letter 612×792, Legal 612×1008.
  - `margin(Length|Edges ...$v)` (default `Edges::all(mm(20))`); `header(Node|Component|string ...)`, `footer(...)`, `background(...)`, `body(Node|Component|string ...)` (multiple calls append); `width(): float`, `height(): float` (orientation applied); `margins(): Edges`; `headerNodes()`, `footerNodes()`, `backgroundNodes()`, `bodyNodes()`: each `list<Node|Component>`.
  - `headerGap(Length)` / `footerGap(Length)`: space between header/footer and body, default `mm(4)`.
- `final class Document`:
  - `static make(Page ...$pages)`; `title(string)`, `author()`, `subject()`, `keywords()`, `lang()`, `createdAt(\DateTimeImmutable)`; `pages(): list<Page>`; `meta(): Paint\DocumentMeta`.
- `enum OverflowMode { case Throw; case Clip; }`
- `final readonly class RenderOptions`: `__construct(?Backend $backend = null, ?FontRegistry $fonts = null, ?Theme $theme = null, OverflowMode $overflow = OverflowMode::Throw, ?\Closure $onWarning = null)`. Accessors: `backend()`, which creates a `PhpPdfBackend` lazily (memoize it in a private static per options instance via `WeakMap`, or simply create it in `Document::render`); `fonts(Backend $b)`, which returns the given registry or `FontRegistry::withDefaults($b)`; `theme()`, which returns the given theme or `Theme::default()`.

- [ ] **Step 1: Write failing tests**

`tests/Node/TableTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Node;

use Papercut\{Table, TableCell, TableRow, Text};
use Papercut\Value\{Fr, Length};
use PHPUnit\Framework\TestCase;

use function Papercut\mm;

final class TableTest extends TestCase
{
    public function test_convenience_api_builds_rows(): void
    {
        $courses = [['Math', 4, 'A'], ['Art', 2, 'B+']];
        $t = Table::make()
            ->columns('3fr 25mm 20mm')
            ->head('Course', 'Credits', 'Grade')
            ->rows($courses, fn (array $c) => [$c[0], $c[1], Text::make($c[2])->right()]);
        self::assertCount(3, $t->trackList()?->tracks ?? []);
        self::assertInstanceOf(Fr::class, $t->trackList()?->tracks[0]);
        self::assertCount(1, $t->headerRows());
        self::assertTrue($t->headerRows()[0]->cells()[0]->header());
        self::assertSame('th', $t->headerRows()[0]->cells()[0]->debugName());
        self::assertCount(2, $t->bodyRows());
        self::assertSame('td', $t->bodyRows()[1]->cells()[1]->debugName());
        self::assertTrue($t->repeatsHeader());
        self::assertFalse($t->repeatHeader(false)->repeatsHeader());
    }

    public function test_columns_variadic_typed(): void
    {
        $t = Table::make()->columns(mm(10), \Papercut\fr(1));
        self::assertInstanceOf(Length::class, $t->trackList()?->tracks[0]);
    }

    public function test_colspan_validation(): void
    {
        self::assertSame(2, TableCell::make('x')->colspan(2)->span());
        $this->expectException(\InvalidArgumentException::class);
        TableCell::make('x')->colspan(0);
    }

    public function test_row_wraps_non_cells(): void
    {
        $r = TableRow::make('a', TableCell::make('b'));
        self::assertCount(2, $r->cells());
    }
}
```

`tests/Node/PageDocumentTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Node;

use Papercut\{Document, Page, Text};
use Papercut\Exception\InvalidValue;
use PHPUnit\Framework\TestCase;

use function Papercut\{mm, pt};

final class PageDocumentTest extends TestCase
{
    public function test_page_sizes_and_orientation(): void
    {
        self::assertEqualsWithDelta([595.28, 841.89], [Page::a4()->width(), Page::a4()->height()], 0.01);
        self::assertEqualsWithDelta([841.89, 595.28], [Page::a4()->landscape()->width(), Page::a4()->landscape()->height()], 0.01);
        self::assertEqualsWithDelta([612.0, 792.0], [Page::named('LETTER')->width(), Page::named('letter')->height()], 0.01);
        self::assertSame(100.0, Page::size(pt(100), pt(200))->width());
        $this->expectException(InvalidValue::class);
        Page::named('b5');
    }

    public function test_page_slots_and_margins(): void
    {
        $p = Page::a4()->margin(mm(10), mm(15))->header('H')->footer('F')->body('A')->body(Text::make('B'));
        self::assertEqualsWithDelta(mm(15)->points, $p->margins()->left->points, 1e-9);
        self::assertCount(1, $p->headerNodes());
        self::assertCount(1, $p->footerNodes());
        self::assertCount(2, $p->bodyNodes());
        self::assertEqualsWithDelta(mm(20)->points, Page::a4()->margins()->top->points, 1e-9);
    }

    public function test_document_meta(): void
    {
        $d = Document::make(Page::a4())->title('T')->author('A')->lang('en')->createdAt(new \DateTimeImmutable('2026-01-01'));
        self::assertSame('T', $d->meta()->title);
        self::assertSame('en', $d->meta()->lang);
        self::assertCount(1, $d->pages());
    }
}
```

- [ ] **Step 2–4:** run → FAIL, implement, run → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add table, page and document nodes`

---

### Task 4: Inline markup, itemizer, line breaker

**Files:**
- Create: `src/Text/InlineMarkup.php`, `src/Text/InlineItem.php`, `src/Text/Itemizer.php`, `src/Text/LineBreaker.php`, `src/Text/Line.php`
- Modify: `src/Text.php` (wire `Text::markup()`)
- Test: `tests/Text/InlineMarkupTest.php`, `tests/Text/ItemizerTest.php`, `tests/Text/LineBreakerTest.php`

**Interfaces:**
- `final class InlineMarkup { /** @return list<Span> */ static parse(string $markup): array }`. Supported tags: `<b>`, `<strong>`, `<i>`, `<em>`, `<u>`, `<s>`, `<a href="…">`, `<br>` / `<br/>`, `<span …attrs>` (attributes go through `Style::fromAttributes`, text properties only: `color font font-size weight bold italic underline strike letter-spacing`). Nesting is allowed. A `<` starts a tag only when immediately followed by a letter or `/`; otherwise it is literal text (`a < b` stays `a < b`). Tags are parsed first; entities (`&amp; &lt; &gt; &quot; &#39; &#NNN; &#xHH;`) are decoded only inside text segments afterwards, so `&lt;b&gt;` is literal text. Any other tag, a mismatched close, or an unclosed tag throws `InvalidValue::for('inline markup', $markup)` naming the tag in a `withContext("<tag> is not allowed")`. Adjacent text with the same style merges into one span.
- `final readonly class InlineItem`: `kind` (`'word'|'space'|'break'`), `string $text`, `CachedFont $font`, `float $size`, `float $width`, `ComputedStyle $style`, `?string $href`. A word item is an unbreakable run of non-space chars. It can carry a soft-break opportunity after it: `bool $breakAfter` (true after `-`, `–`, `—`, `/` and between CJK ideographs).
- `final class Itemizer`:
  `__construct(FontRegistry $fonts, ?\Closure $onWarning = null, list<string> $fallbackFamilies = [])`
  `/** @return list<InlineItem> */ itemize(list<Span> $spans, ComputedStyle $paragraph): array`
  Rules:
  1. Per span, compute the style: `$paragraph->child($span->style)` with text-prop inheritance. Use `paragraph` as the parent so spans inherit from the paragraph, not reset (in `ComputedStyle::child`, box props of spans are ignored).
  2. Whitespace collapse across span boundaries: any run of `[ \t\n\r\f]` becomes a single space. Leading space at paragraph start and trailing space at paragraph end are dropped. A space adjacent to a `break` is dropped.
  3. Font = `fonts->resolve(style.fontFamily, style.fontWeight, italic ? Italic : Normal)`.
  4. Font fallback: if a word is not `covers()`-ed by the primary font, split it into maximal runs that the primary covers. For each uncovered run, try `fallbackFamilies` in order. If none covers it, keep the primary font and call `onWarning("Missing glyphs for \"<run>\" in font family <family>")` once per distinct run.
  5. width = `font->advance(text, size, letterSpacing)`; space width = `font->advance(' ', size, letterSpacing)`.
  6. Break opportunities: split words after `-`, `–`, `—`, `/` (the char stays with the left part, `breakAfter: true`). Each CJK ideograph (U+3040–U+30FF, U+3400–U+9FFF, U+F900–U+FAFF) becomes its own word with `breakAfter: true`.
- `final readonly class Line`: `list<InlineItem> $items` (no leading/trailing spaces), `float $width` (sum of item widths), `bool $endsWithBreak`, `bool $isLast`.
- `final class LineBreaker { /** @return list<Line> */ static break(list<InlineItem> $items, float $maxWidth): array }`: greedy fill. A space or a `breakAfter` word is a break opportunity. A word wider than `$maxWidth` alone on a line is split by characters (graphemes via `mb_str_split`) into pieces that fit, with at least one char per line so it always makes progress; the piece widths are re-measured with the item's font. A trailing space is never counted in the line width. `break` items force a new line (an empty line is allowed, e.g. `<br><br>`). Zero items → zero lines. `$maxWidth <= 0` is treated as 1pt (still progresses).

- [ ] **Step 1: Write failing tests**

`tests/Text/InlineMarkupTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use Papercut\Exception\InvalidValue;
use Papercut\Text\InlineMarkup;
use PHPUnit\Framework\TestCase;

final class InlineMarkupTest extends TestCase
{
    public function test_plain_text_is_one_span(): void
    {
        $s = InlineMarkup::parse('Hello world');
        self::assertCount(1, $s);
        self::assertSame('Hello world', $s[0]->text);
    }

    public function test_nested_tags(): void
    {
        $s = InlineMarkup::parse('a <b>b <i>c</i></b> d');
        self::assertSame(['a ', 'b ', 'c', ' d'], array_map(fn ($x) => $x->text, $s));
        self::assertSame(700, $s[1]->style->fontWeight);
        self::assertSame(700, $s[2]->style->fontWeight);
        self::assertTrue($s[2]->style->italic);
        self::assertNull($s[3]->style->fontWeight);
    }

    public function test_link_break_and_span_attrs(): void
    {
        $s = InlineMarkup::parse('<a href="https://x.test">go</a><br/><span color="red-500" font-size="14pt">r</span>');
        self::assertSame('https://x.test', $s[0]->href);
        self::assertTrue($s[1]->lineBreak);
        self::assertSame('#ef4444', (string) $s[2]->style->color);
        self::assertSame(14.0, $s[2]->style->fontSize?->points);
    }

    public function test_entities_decoded_after_tag_parsing(): void
    {
        $s = InlineMarkup::parse('&lt;b&gt; &amp; <u>x</u>');
        self::assertSame('<b> & ', $s[0]->text);
        self::assertTrue($s[1]->style->underline);
    }

    public function test_rejects_unknown_and_unbalanced(): void
    {
        foreach (['<script>x</script>', '<b>x', 'x</b>', '<b><i>x</b></i>'] as $bad) {
            try {
                InlineMarkup::parse($bad);
                self::fail("accepted $bad");
            } catch (InvalidValue) {
                self::addToAssertionCount(1);
            }
        }
    }
}
```

`tests/Text/ItemizerTest.php` needs two test helpers, created in this task:
- Add `tests/Support/FakeBackend.php` implementing `Backend`: `loadFont($path)` → `new FakeFont('fake:' . basename($path))` (cached per path), `loadImageFile`/`loadImageBytes` → an `ImageHandle` of 100×50 px with id `sha1` of the input, and `render()` returns `serialize(array_map(fn ($p) => [$p->width, $p->height, count($p->ops)], $pages))` so layout tests never need a PDF.
- Modify `tests/Support/FakeFont.php` (from Plan 1) to take an optional constructor arg `string $id = 'fake'` that `id()` returns. Existing tests keep passing.
- `FontRegistry::withDefaults(new FakeBackend())` registers the real bundled Inter paths (the registry checks the files exist) but measures with FakeFont, so the font ids reveal which face was resolved (`fake:Inter-Bold.ttf`).
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use Papercut\Span;
use Papercut\Style\{ComputedStyle, Style, Theme};
use Papercut\Tests\Support\FakeBackend;
use Papercut\Text\{FontRegistry, Itemizer};
use PHPUnit\Framework\TestCase;

final class ItemizerTest extends TestCase
{
    private function itemizer(?\Closure $warn = null): Itemizer
    {
        return new Itemizer(FontRegistry::withDefaults(new FakeBackend()), $warn);
    }

    private function root(): ComputedStyle
    {
        return ComputedStyle::root(Theme::default());
    }

    public function test_collapses_blade_style_whitespace(): void
    {
        $items = $this->itemizer()->itemize([Span::text("\n      Hello\n   world  ")], $this->root());
        self::assertSame(['word:Hello', 'space: ', 'word:world'], array_map(fn ($i) => $i->kind . ':' . $i->text, $items));
    }

    public function test_whitespace_collapses_across_spans_and_widths_use_font(): void
    {
        $items = $this->itemizer()->itemize([Span::text('a '), Span::text(' b', Style::make(fontWeight: 700))], $this->root());
        self::assertSame(['word:a', 'space: ', 'word:b'], array_map(fn ($i) => $i->kind . ':' . $i->text, $items));
        self::assertSame(5.0, $items[0]->width); // FakeFont: 0.5 * 10pt per char
        self::assertSame(700, $items[2]->style->fontWeight);
    }

    public function test_break_opportunities(): void
    {
        $items = $this->itemizer()->itemize([Span::text('well-known and/or 漢字')], $this->root());
        $words = array_values(array_filter($items, fn ($i) => $i->kind === 'word'));
        self::assertSame('well-', $words[0]->text);
        self::assertSame('known', $words[1]->text);
        self::assertSame('and/', $words[2]->text);
        self::assertTrue($words[2]->breakAfter);
        self::assertTrue($words[0]->breakAfter);
        self::assertSame('漢', $words[count($words) - 2]->text);
        self::assertTrue($words[count($words) - 2]->breakAfter);
    }

    public function test_breaks_drop_adjacent_spaces(): void
    {
        $items = $this->itemizer()->itemize([Span::text('a '), Span::break(), Span::text(' b')], $this->root());
        self::assertSame(['word', 'break', 'word'], array_map(fn ($i) => $i->kind, $items));
    }

    public function test_missing_glyph_warns_once(): void
    {
        $warnings = [];
        $this->itemizer(function (string $w) use (&$warnings) { $warnings[] = $w; })
            ->itemize([Span::text('ok 漢 漢')], $this->root());
        self::assertCount(1, $warnings);
        self::assertStringContainsString('漢', $warnings[0]);
    }

    public function test_empty_input(): void
    {
        self::assertSame([], $this->itemizer()->itemize([Span::text("  \n ")], $this->root()));
    }
}
```

`tests/Text/LineBreakerTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use Papercut\Span;
use Papercut\Style\{ComputedStyle, Theme};
use Papercut\Tests\Support\FakeBackend;
use Papercut\Text\{FontRegistry, Itemizer, Line, LineBreaker};
use PHPUnit\Framework\TestCase;

final class LineBreakerTest extends TestCase
{
    /** @return list<Line> */
    private function lines(string $text, float $width): array
    {
        $items = (new Itemizer(FontRegistry::withDefaults(new FakeBackend())))->itemize([Span::text($text)], ComputedStyle::root(Theme::default()));
        return LineBreaker::break($items, $width);
    }

    /** @param list<Line> $lines @return list<string> */
    private function texts(array $lines): array
    {
        return array_map(fn (Line $l) => implode('', array_map(fn ($i) => $i->text, $l->items)), $lines);
    }

    public function test_greedy_wrap(): void
    {
        // each char = 5pt at 10pt FakeFont; "aaa bbb ccc" -> words 15pt, space 5pt
        self::assertSame(['aaa bbb', 'ccc'], $this->texts($this->lines('aaa bbb ccc', 35)));
        self::assertSame(['aaa', 'bbb', 'ccc'], $this->texts($this->lines('aaa bbb ccc', 34)));
    }

    public function test_line_width_excludes_trailing_space(): void
    {
        $lines = $this->lines('aaa bbb ccc', 35);
        self::assertSame(35.0, $lines[0]->width);
        self::assertFalse($lines[0]->isLast);
        self::assertTrue($lines[1]->isLast);
    }

    public function test_overlong_word_breaks_by_character(): void
    {
        self::assertSame(['abcd', 'efgh', 'ij'], $this->texts($this->lines('abcdefghij', 20)));
    }

    public function test_tiny_width_still_progresses(): void
    {
        self::assertCount(3, $this->lines('abc', 0));
    }

    public function test_hyphen_break_opportunity(): void
    {
        self::assertSame(['well-', 'known'], $this->texts($this->lines('well-known', 30)));
    }

    public function test_empty(): void
    {
        self::assertSame([], $this->lines('', 100));
    }
}
```

- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement.** Greedy algorithm sketch:
```php
$lines = []; $cur = []; $w = 0.0; $pendingSpace = null;
foreach ($items as $it) {
    if ($it->kind === 'break') { $lines[] = [$cur, $w, true]; $cur = []; $w = 0.0; $pendingSpace = null; continue; }
    if ($it->kind === 'space') { if ($cur !== []) { $pendingSpace = $it; } continue; }
    $add = ($pendingSpace?->width ?? 0.0) + $it->width;
    if ($cur !== [] && $w + $add > $maxWidth + 1e-9) { $lines[] = [$cur, $w, false]; $cur = []; $w = 0.0; $pendingSpace = null; $add = $it->width; }
    if ($cur === [] && $it->width > $maxWidth) { /* split by chars: emit full lines, keep remainder in $cur */ }
    if ($pendingSpace !== null) { $cur[] = $pendingSpace; $pendingSpace = null; }
    $cur[] = $it; $w += $add;
}
```
`breakAfter` words allow a break before the next word even with no space between them. Treat the gap as a zero-width space opportunity. Wire `Text::markup()` to `InlineMarkup::parse`.
- [ ] **Step 4: Run** → PASS. **Step 5: Commit** `feat(core): add inline markup parser, itemizer and greedy line breaker`

---

### Task 5: Layout primitives + TextLayouter

**Files:**
- Create: `src/Layout/{Constraints,LayoutResult,Status,BreakToken,Fragment,LayoutContext,LayoutEngine}.php`, `src/Layout/TextLayouter.php`, `src/Paint/PageNumberSlot.php`, `src/Exception/LayoutOverflow.php`
- Create: `tests/Support/LayoutHarness.php`
- Test: `tests/Layout/TextLayouterTest.php`

**Interfaces:**
- `final readonly class Constraints { __construct(public float $width, public ?float $height, public bool $fresh = true, public ?float $definiteHeight = null) }`.
  - `$height`: space left on the current page (null = unbounded).
  - `$fresh`: nothing has been placed on this page above this node at any ancestor level.
  - `$definiteHeight`: a stretch or explicit height the node must fill.
  - Helpers: `withWidth(float)`, `withHeight(?float)`, `withFresh(bool)`, `unbounded()`.
- `enum Status { case Complete; case Partial; case DoesNotFit; }`
- `final readonly class BreakToken { __construct(public int $index = 0, public ?BreakToken $child = null, /** @var array<int, ?BreakToken> */ public array $parallel = [], public bool $headerPending = false) }`.
  - `$index` is the next line for text, or the next child/row index for containers and tables.
  - `$parallel` holds per-child tokens for rows and table cells split in parallel; a null entry means that child has finished.
- `final class Fragment`:
  - `__construct(public float $width, public float $height)`.
  - Mutable during construction, then treated as immutable: `addOp(Op $op): void` (local coords), `addChild(float $x, float $y, Fragment $f): void`, `addSlot(PageNumberSlot $s): void`.
  - Readers: `ops(): list<Op>`, `children(): list<array{x: float, y: float, fragment: Fragment}>`, `slots(): list<PageNumberSlot>`.
  - Optional wrappers: `?float $opacity`; `?array{float,float,float,float} $clip` (local x, y, w, h).
- `final readonly class LayoutResult`:
  - `static complete(Fragment)`, `static partial(Fragment, BreakToken)`, `static doesNotFit()`.
  - Props: `Status $status`, `?Fragment $fragment`, `?BreakToken $token`.
- `final readonly class PageNumberSlot { __construct(float $x, float $baseline, float $width, string $format, CachedFont $font, float $size, Color $color, TextAlign $align, float $letterSpacing) }`.
  - The Painter replaces `{page}` and `{total}`, then aligns the text within `$width`.
- `final class LayoutContext`:
  - `__construct(FontRegistry $fonts, Theme $theme, Backend $backend, OverflowMode $overflow, ?\Closure $onWarning)`.
  - `itemizer(): Itemizer`; `warn(string $msg): void` (prefixes the current node path: `"[page > column > text] msg"`).
  - `pushPath(string $name): void` / `popPath(): void`; `path(): string` (joins with ` > `, adding a `[n]` sibling index when the caller passes one, e.g. `pushPath('view[2]')`).
  - `image(Image $node): ImageHandle` (loads through the backend, cached by source).
  - `resolveStyle(Node $node, ComputedStyle $parent): ComputedStyle`: applies, in order, the theme preset (Text h1–h4/p), the named style (`styleName()` → `theme->named()`), then the node's own `style()`.
- `final class LayoutEngine`:
  - `__construct(LayoutContext $ctx)`.
  - `layout(Node|Component $node, ComputedStyle $parent, Constraints $c, ?BreakToken $token = null): LayoutResult`.
    It expands Components recursively (`render()` until a Node is reached, max depth 100 else `\LogicException`), resolves style, pushes the path, and dispatches by class to the layouter registered for it. In this task only `Text` is registered; later tasks register theirs in the constructor.
  - `intrinsic(Node|Component $node, ComputedStyle $parent): array{min: float, max: float}`: delegates to the layouter's `intrinsic()`.
- `interface Layouter { layout(Node $node, ComputedStyle $style, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult; /** @return array{min: float, max: float} */ intrinsic(Node $node, ComputedStyle $style, LayoutEngine $engine): array; }` (file `src/Layout/Layouter.php`).
- `final class LayoutOverflow extends PapercutException { __construct(string $path, string $reason) }`, message: `Content at "<path>" cannot fit on an empty page: <reason>.`
- `TextLayouter` rules:
  - Lines from `LineBreaker` at `c.width` (cache by `spl_object_id(node) . width` within the layouter instance).
  - Line box per line: for each item with style s and font f, `half = (s.lineHeight * s.size - (f.ascent + f.descent)) / 2`; `baseline = max(half + f.ascent(size))`; `lineHeight = max(baseline + f.descent(size) + half)` over items. An empty line (from a `<br>`) uses the paragraph's computed font and size.
  - Alignment: Left x=0; Right x=c.width−line.width; Center (c.width−line.width)/2; Justify: extra = c.width−line.width distributed equally over the line's space items, except on the last line and on lines ending in a break.
  - Ops: adjacent word+space items with identical style (same font id, size, color, letterSpacing, decorations, href) on non-justified lines merge into one `TextRun` whose text is the concatenation. On justified lines each word is its own `TextRun`. Underline: `Line(x, baseline + 0.12*size, x+w, …, color, max(0.5, 0.06*size))`; strike at `baseline − 0.3*size`. Links: a `Paint\Link` per contiguous same-href run per line, covering `[x, lineTop, w, lineHeight]`.
  - Fragmentation with `minLines = m` (`min(m, lineCount)` applies as both orphans and widows):
    - lines fit if their cumulative height ≤ c.height (null height → all fit).
    - Let `n` = number of lines that fit, starting from `token.index`. If all remaining fit → Complete.
    - Else if `n < m` → `DoesNotFit` if not fresh. If fresh and `n ≥ 1`, place `n` (orphans can't be honored on an empty page); if fresh and `n == 0` → place 1 line (it overflows by design; Paginator decides). Return Partial.
    - Else apply widows: remaining after break = total − (start + n); if `remaining < m`, reduce `n` by `m − remaining`; if that makes `n < m`, then DoesNotFit (not fresh) or place `max(1, n)` (fresh).
    - Partial token: `new BreakToken(index: start + n)`.
  - Empty text → Complete with a 0-height fragment of width c.width.
  - `intrinsic()`: min = widest single word (or CJK char), max = width of all items on one line (sum).

`tests/Support/LayoutHarness.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Support;

use Papercut\{Component, Node, OverflowMode};
use Papercut\Layout\{BreakToken, Constraints, LayoutContext, LayoutEngine, LayoutResult};
use Papercut\Style\{ComputedStyle, Theme};
use Papercut\Text\FontRegistry;

final class LayoutHarness
{
    /** @var list<string> */
    public array $warnings = [];
    public LayoutEngine $engine;

    public function __construct(?Theme $theme = null, OverflowMode $overflow = OverflowMode::Throw)
    {
        $backend = new FakeBackend();
        $ctx = new LayoutContext(FontRegistry::withDefaults($backend), $theme ?? Theme::default(), $backend, $overflow, function (string $w): void { $this->warnings[] = $w; });
        $this->engine = new LayoutEngine($ctx);
    }

    public function layout(Node|Component $node, float $width, ?float $height = null, ?BreakToken $token = null, bool $fresh = true): LayoutResult
    {
        return $this->engine->layout($node, ComputedStyle::root(Theme::default()), new Constraints($width, $height, $fresh), $token);
    }

    /** All TextRun texts in a fragment tree, depth-first, with absolute positions. @return list<array{text:string,x:float,baseline:float}> */
    public static function runs(\Papercut\Layout\Fragment $f, float $ox = 0, float $oy = 0): array
    {
        $out = [];
        foreach ($f->ops() as $op) {
            if ($op instanceof \Papercut\Paint\TextRun) {
                $out[] = ['text' => $op->text, 'x' => $ox + $op->x, 'baseline' => $oy + $op->baseline];
            }
        }
        foreach ($f->children() as $c) {
            array_push($out, ...self::runs($c['fragment'], $ox + $c['x'], $oy + $c['y']));
        }
        return $out;
    }
}
```

- [ ] **Step 1: Write failing tests** (`tests/Layout/TextLayouterTest.php`). FakeFont at 10pt: char = 5pt, ascent 8, descent 2; line height 1.4 → 14pt; half-leading = (14 − 10)/2 = 2; baseline = 10; line box = 14.
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Span, Text};
use Papercut\Layout\{BreakToken, Status};
use Papercut\Paint\{Line as LineOp, Link as LinkOp, TextRun};
use Papercut\Style\Style;
use Papercut\Tests\Support\LayoutHarness;
use PHPUnit\Framework\TestCase;

final class TextLayouterTest extends TestCase
{
    public function test_single_line_geometry(): void
    {
        $r = (new LayoutHarness())->layout(Text::make('Hello world'), 200);
        self::assertSame(Status::Complete, $r->status);
        self::assertSame(14.0, $r->fragment?->height);
        $runs = LayoutHarness::runs($r->fragment);
        self::assertSame([['text' => 'Hello world', 'x' => 0.0, 'baseline' => 10.0]], $runs);
    }

    public function test_alignment(): void
    {
        $h = new LayoutHarness();
        self::assertSame(145.0, LayoutHarness::runs($h->layout(Text::make('Hello world')->right(), 200)->fragment)[0]['x']);
        self::assertSame(72.5, LayoutHarness::runs($h->layout(Text::make('Hello world')->center(), 200)->fragment)[0]['x']);
    }

    public function test_justify_spreads_words_except_last_line(): void
    {
        $runs = LayoutHarness::runs((new LayoutHarness())->layout(Text::make('aa bb cc dd')->justify(), 40)->fragment);
        // line 1 "aa bb" (25pt) justified to 40 -> "bb" ends at 40; line 2 "cc dd" left-aligned merged
        self::assertSame('aa', $runs[0]['text']);
        self::assertSame(30.0, $runs[1]['x']);
        self::assertSame('cc dd', $runs[2]['text']);
    }

    public function test_wraps_and_height(): void
    {
        $r = (new LayoutHarness())->layout(Text::make('aaa bbb ccc'), 35);
        self::assertSame(28.0, $r->fragment?->height);
        self::assertSame(24.0, LayoutHarness::runs($r->fragment)[1]['baseline']);
    }

    public function test_empty_text_is_zero_height(): void
    {
        $r = (new LayoutHarness())->layout(Text::make(''), 100);
        self::assertSame(Status::Complete, $r->status);
        self::assertSame(0.0, $r->fragment?->height);
    }

    public function test_fragmentation_respects_orphans_and_widows(): void
    {
        $text = Text::make('a b c d e f'); // width 5 -> 6 lines of 14pt
        $h = new LayoutHarness();
        // 3 lines fit (42pt): break after 3, 3 remain -> fine
        $r = $h->layout($text, 5, 42, fresh: false);
        self::assertSame(Status::Partial, $r->status);
        self::assertSame(3, $r->token?->index);
        // 5 lines fit (70pt): widows would leave 1 -> pull back to 4
        $r = $h->layout($text, 5, 70, fresh: false);
        self::assertSame(4, $r->token?->index);
        // 1 line fits, not fresh -> DoesNotFit (orphan)
        self::assertSame(Status::DoesNotFit, $h->layout($text, 5, 14, fresh: false)->status);
        // 1 line fits, fresh -> place 1 anyway
        self::assertSame(1, $h->layout($text, 5, 14, fresh: true)->token?->index);
        // resume from token
        $rest = $h->layout($text, 5, null, new BreakToken(index: 4));
        self::assertSame(Status::Complete, $rest->status);
        self::assertCount(2, LayoutHarness::runs($rest->fragment));
    }

    public function test_min_lines_one_allows_any_break(): void
    {
        $r = (new LayoutHarness())->layout(Text::make('a b c')->minLines(1), 5, 14, fresh: false);
        self::assertSame(1, $r->token?->index);
    }

    public function test_decorations_and_links(): void
    {
        $t = Text::spans(Span::text('go', Style::make(underline: true), href: 'https://x.test'));
        $f = (new LayoutHarness())->layout($t, 100)->fragment;
        $ops = $f?->ops() ?? [];
        self::assertNotEmpty(array_filter($ops, fn ($o) => $o instanceof LineOp));
        $links = array_values(array_filter($ops, fn ($o) => $o instanceof LinkOp));
        self::assertSame('https://x.test', $links[0]->uri);
        self::assertSame(10.0, $links[0]->width);
    }

    public function test_heading_preset_from_theme(): void
    {
        $r = (new LayoutHarness())->layout(Text::h1('T'), 100);
        // 24pt * 1.4 line height; margin is applied by the parent block (not inside text)
        self::assertEqualsWithDelta(33.6, $r->fragment?->height ?? 0, 1e-9);
    }

    public function test_intrinsic_sizes(): void
    {
        $h = new LayoutHarness();
        $s = $h->engine->intrinsic(Text::make('aa bbbb c'), \Papercut\Style\ComputedStyle::root(\Papercut\Style\Theme::default()));
        self::assertSame(20.0, $s['min']);
        self::assertSame(45.0, $s['max']);
    }
}
```

- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add layout primitives and text layouter with orphan/widow control`

---

### Task 6: Box geometry + BlockLayouter (column-direction views, links, page breaks, spacers)

**Files:**
- Create: `src/Layout/BoxGeometry.php`, `src/Layout/BlockLayouter.php`, `src/Layout/LeafLayouter.php` (Spacer, PageBreak), `src/Layout/LinkLayouter.php`
- Modify: `src/Layout/LayoutEngine.php` (register View with direction Column, Link, Spacer, PageBreak)
- Test: `tests/Layout/BlockLayouterTest.php`

**Interfaces / rules:**
- `BoxGeometry` (static helpers):
  - `contentWidth(ComputedStyle $s, float $available): float`. Resolves width (`Auto` → available − horizontal margin; `Length`; `Percent` of available), clamps to min/max, subtracts padding + border.
  - `outerHeight(ComputedStyle $s, float $contentHeight, bool $first, bool $last): float`. Uses slice semantics: top padding/border only on the first fragment, bottom only on the last.
  - `decorate(Fragment $f, ComputedStyle $s, bool $first, bool $last): void`. Adds the background `Rect` (with radius) and borders. A uniform border on a complete box becomes a single stroked `Rect` with radius. Otherwise it emits one `Line` per present side, skipping the top when `!$first` and the bottom when `!$last`. Dashed → `Line::$dashed`. Opacity < 1 sets `$f->opacity`.
  - Margins are applied by the parent when positioning (no collapsing). Horizontal margin reduces the child's available width; vertical margins add space before and after the child within the parent.
- `BlockLayouter` (a View whose computed direction is Column, plus Link):
  - Children are laid out top to bottom at the content width, `gap` between them. Each child gets `Constraints(width = contentWidth − child margins, height = remaining − margin-top, fresh = parentFresh && nothing placed yet on this page)`.
  - Child `alignSelf`/parent `alignItems` in a column: Stretch (default) gives children the full width. Start/Center/End size children at `min(max-content, contentWidth)` and position them horizontally (Text, Image and View all support intrinsic sizing via `engine->intrinsic`).
  - Explicit `height` (Length or Percent of `c.definiteHeight`) → content height is fixed. Children overflowing the fixed height are still laid out (the box is not clipped in v0.1). Free space distributes to `grow` children, then `justify` (Start/End/Center/Space*) positions the children when they all fit.
  - Fragmentation:
    - Resume from `token.index` / `token.child`.
    - Child Partial → this returns Partial with `BreakToken(index: i, child: childToken)`.
    - Child DoesNotFit:
      - if any content was already placed in this fragment → Partial with `index: i, child: null` (break before child i).
      - if nothing was placed yet → return DoesNotFit. When not fresh, the parent breaks before this block. When fresh, this is the overflow case: the Paginator owns the overflow policy.
      - `keepWithNext`: if child i−1 has keepWithNext and child i DoesNotFit, then break before i−1, unless i−1 was the first child placed on this page.
    - `keepTogether` on this block: if the result would be Partial and `!c.fresh` → DoesNotFit.
    - `breakBefore` on child i (i not first on page) → Partial before i. `breakAfter` on child i (not last) → Partial after i. `PageBreak` child → same as `breakAfter` of the previous child (a PageBreak as the first child on a fresh page is ignored).
    - Vertical padding/border/margins count against `c.height`.
  - Heading/paragraph presets: their `margin-bottom` comes from `ComputedStyle` (Task 1 `Theme::heading()` sets `margin`), so the block applies it like any margin.
  - `intrinsic()`: min = max over children of child min + horizontal margins + own padding/border; max = same with child max. An explicit Length width overrides both.
- `LeafLayouter`:
  - Spacer: in a column, height = fixedSize (or 0 if flex; flex growth is handled by the parent). It is Complete, or DoesNotFit when there is not enough height and not fresh; on a fresh page it is truncated to the remaining height.
  - PageBreak: Complete with zero size. Breaking is handled by the parent.
  - Intrinsic: Spacer fixedSize in a row → {size, size}; else {0,0}.
- `LinkLayouter`: lays out like a column block, then adds a `Paint\Link` op covering each fragment.

- [ ] **Step 1: Write failing tests** (`tests/Layout/BlockLayouterTest.php`):
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Column, Link, PageBreak, Spacer, Text, View};
use Papercut\Layout\{BreakToken, Status};
use Papercut\Paint\{Line, Link as LinkOp, Rect};
use Papercut\Style\Align;
use Papercut\Tests\Support\LayoutHarness;
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

use function Papercut\{percent, pt};

final class BlockLayouterTest extends TestCase
{
    public function test_stacks_children_with_gap_padding_and_margin(): void
    {
        $col = Column::make(Text::make('a'), Text::make('b')->margin(pt(3), pt(0)))->gap(pt(4))->padding(pt(10));
        $r = (new LayoutHarness())->layout($col, 100);
        // 10 + 14 + 4 + 3 + 14 + 3 + 10
        self::assertSame(58.0, $r->fragment?->height);
        $runs = LayoutHarness::runs($r->fragment);
        self::assertSame([10.0, 20.0], [$runs[0]['x'], $runs[0]['baseline']]);
        self::assertSame(41.0, $runs[1]['baseline']); // 10 + 14 + 4 + 3 + 10
    }

    public function test_background_border_and_width(): void
    {
        $v = View::make('x')->width(percent(50))->background(Color::white())->border(pt(1), Color::black())->radius(pt(4));
        $f = (new LayoutHarness())->layout($v, 200)->fragment;
        self::assertSame(100.0, $f?->width);
        $rects = array_values(array_filter($f?->ops() ?? [], fn ($o) => $o instanceof Rect));
        self::assertCount(2, $rects); // background + stroke
        self::assertSame(4.0, $rects[0]->radius);
    }

    public function test_empty_column(): void
    {
        $r = (new LayoutHarness())->layout(Column::make()->padding(pt(5)), 100);
        self::assertSame(Status::Complete, $r->status);
        self::assertSame(10.0, $r->fragment?->height);
    }

    public function test_breaks_between_children_and_resumes(): void
    {
        $col = Column::make(Text::make('one'), Text::make('two'), Text::make('three'));
        $h = new LayoutHarness();
        $r = $h->layout($col, 100, 30);
        self::assertSame(Status::Partial, $r->status);
        self::assertSame(2, $r->token?->index);
        $rest = $h->layout($col, 100, null, $r->token);
        self::assertSame(['three'], array_column(LayoutHarness::runs($rest->fragment), 'text'));
    }

    public function test_child_partial_propagates_token(): void
    {
        $col = Column::make(Text::make('a b c d e f')->minLines(1));
        $r = (new LayoutHarness())->layout($col, 5, 28);
        self::assertSame(0, $r->token?->index);
        self::assertSame(2, $r->token?->child?->index);
    }

    public function test_keep_together_moves_whole_block(): void
    {
        $block = Column::make(Text::make('a'), Text::make('b'))->keepTogether();
        self::assertSame(Status::DoesNotFit, (new LayoutHarness())->layout($block, 100, 20, fresh: false)->status);
        self::assertSame(Status::Partial, (new LayoutHarness())->layout($block, 100, 20, fresh: true)->status);
    }

    public function test_keep_with_next_breaks_before_heading(): void
    {
        $col = Column::make(Text::make('intro'), Text::make('Heading')->keepWithNext(), Column::make(Text::make('x'), Text::make('y'))->keepTogether());
        $r = (new LayoutHarness())->layout($col, 100, 40);
        self::assertSame(1, $r->token?->index);
    }

    public function test_page_break_and_break_before(): void
    {
        $h = new LayoutHarness();
        $r = $h->layout(Column::make(Text::make('a'), PageBreak::make(), Text::make('b')), 100, 500);
        self::assertSame(Status::Partial, $r->status);
        self::assertSame(['a'], array_column(LayoutHarness::runs($r->fragment), 'text'));
        $r2 = $h->layout(Column::make(Text::make('a'), Text::make('b')->breakBefore()), 100, 500);
        self::assertSame(1, $r2->token?->index);
        $r3 = $h->layout(Column::make(PageBreak::make(), Text::make('a')), 100, 500);
        self::assertSame(Status::Complete, $r3->status);
    }

    public function test_sliced_borders_on_split_box(): void
    {
        $box = Column::make(Text::make('a'), Text::make('b'))->borderWidth(pt(1))->borderColor(Color::black());
        $r = (new LayoutHarness())->layout($box, 100, 20);
        $lines = array_filter($r->fragment?->ops() ?? [], fn ($o) => $o instanceof Line);
        self::assertCount(3, $lines); // top, left, right; no bottom on first slice
    }

    public function test_align_items_center_sizes_child_to_content(): void
    {
        $col = Column::make(Text::make('ab'))->align(Align::Center);
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($col, 100)->fragment);
        self::assertSame(45.0, $runs[0]['x']);
    }

    public function test_link_wraps_content(): void
    {
        $f = (new LayoutHarness())->layout(Link::to('https://x.test', 'go'), 100)->fragment;
        $links = array_values(array_filter($f?->ops() ?? [], fn ($o) => $o instanceof LinkOp));
        self::assertSame('https://x.test', $links[0]->uri);
    }

    public function test_fixed_height_grow_and_justify(): void
    {
        $col = Column::make(Text::make('a'), Spacer::flex(), Text::make('b'))->height(pt(100));
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($col, 100)->fragment);
        self::assertSame(96.0, $runs[1]['baseline']); // pushed to bottom: 100 - 14 + 10
    }
}
```

- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add block layout with box model and pagination rules`

---

### Task 7: Flex row layout

**Files:**
- Create: `src/Layout/FlexLayouter.php`
- Modify: `src/Layout/LayoutEngine.php` (View with direction Row → FlexLayouter)
- Test: `tests/Layout/FlexLayouterTest.php`

**Rules (CSS Flexbox §9, simplified):**
1. Content box width W from `BoxGeometry`. Items = children (Components expanded).
2. Flex base size per item: `basis` Length/Percent (of W) if set; otherwise `width` if Length/Percent; otherwise intrinsic `max` (Spacer fixed → its size; Spacer flex → 0). Hypothetical main size = base clamped by min/max width, and never below the intrinsic `min` unless an explicit width/basis is set (CSS automatic minimum size).
3. Lines: no wrap → one line. Wrap → break into a new line when the sum of hypothetical outer sizes plus gaps exceeds W (at least one item per line).
4. Resolve flexible lengths per line: free = W − Σ outer hypothetical − gaps. If free > 0 and Σgrow > 0, distribute free proportionally to grow. If free < 0, shrink proportionally to `shrink × base` and clamp at the min. One clamp pass is enough for v0.1: freeze clamped items and redistribute once.
5. Main-axis positioning with leftover free space (only if no grow consumed it): `justify` Start/End/Center/SpaceBetween/SpaceAround/SpaceEvenly; `gap` is added between items.
6. Cross axis: lay out each item at its main size with unbounded height to get its height; line cross size = max outer height. `alignSelf ?? alignItems`: Stretch → re-layout the item with `definiteHeight = lineHeight − vertical margins` when its height is Auto (the item fills it: its block background extends; the BlockLayouter uses `definiteHeight` as the content height when its own height is Auto and it is stretched). Start/Center/End position it vertically.
7. Fragmentation (spec §7.3: rows fragment as a unit):
   - Each flex line is a unit. If line k doesn't fit in the remaining height: break before line k if k > first-on-page (Partial with `index: k`); if it's the first line and `!fresh` → DoesNotFit; if fresh → **parallel split**: lay out each item with `height = remaining`, collect each item's result and token into `BreakToken(index: k, parallel: [itemIndex => token|null])`, and return Partial (items that Complete get null). Resuming with parallel tokens lays out only unfinished items (finished ones keep their main-axis slot, empty). The line is done when every parallel entry is null.
   - If any item returns DoesNotFit during the fresh parallel split, that item is the overflow case: return DoesNotFit from the row; the Paginator applies the overflow policy.
8. `intrinsic()`: min = max of item min (wrap) or Σ item min + gaps (nowrap); max = Σ item max + gaps; plus own padding/border.

- [ ] **Step 1: Write failing tests** (`tests/Layout/FlexLayouterTest.php`), FakeFont char = 5pt:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Column, Row, Spacer, Text, View};
use Papercut\Layout\{Fragment, Status};
use Papercut\Paint\Rect;
use Papercut\Style\{Align, Justify};
use Papercut\Tests\Support\LayoutHarness;
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

use function Papercut\{percent, pt};

final class FlexLayouterTest extends TestCase
{
    /** @return list<float> x of each run */
    private function xs(Fragment $f): array
    {
        return array_column(LayoutHarness::runs($f), 'x');
    }

    public function test_items_sit_side_by_side_at_content_width(): void
    {
        $f = (new LayoutHarness())->layout(Row::make('aa', 'bbb'), 200)->fragment;
        self::assertSame([0.0, 10.0], $this->xs($f));
        self::assertSame(14.0, $f?->height);
    }

    public function test_gap_and_justify(): void
    {
        $h = new LayoutHarness();
        self::assertSame([0.0, 14.0], $this->xs($h->layout(Row::make('aa', 'bbb')->gap(pt(4)), 200)->fragment));
        self::assertSame([0.0, 185.0], $this->xs($h->layout(Row::make('aa', 'bbb')->justify(Justify::SpaceBetween), 200)->fragment));
        self::assertSame([175.0, 185.0], $this->xs($h->layout(Row::make('aa', 'bbb')->justify(Justify::End), 200)->fragment));
        self::assertSame([87.5, 97.5], $this->xs($h->layout(Row::make('aa', 'bbb')->justify(Justify::Center), 200)->fragment));
    }

    public function test_spacer_flex_pushes_right(): void
    {
        self::assertSame([0.0, 185.0], $this->xs((new LayoutHarness())->layout(Row::make('aa', Spacer::flex(), 'bbb'), 200)->fragment));
    }

    public function test_grow_distributes_proportionally(): void
    {
        // CSS semantics: basis auto = max-content (5pt each); free 190 split 1:3 => a = 5 + 47.5
        $row = Row::make(View::make('a')->grow(1), View::make('b')->grow(3));
        self::assertSame([0.0, 52.5], $this->xs((new LayoutHarness())->layout($row, 200)->fragment));
        // basis 0 gives exact 1:3 shares
        $row0 = Row::make(View::make('a')->grow(1)->basis(pt(0)), View::make('b')->grow(3)->basis(pt(0)));
        self::assertSame([0.0, 50.0], $this->xs((new LayoutHarness())->layout($row0, 200)->fragment));
    }

    public function test_percentage_widths(): void
    {
        $row = Row::make(View::make('a')->width(percent(25)), View::make('b')->width(percent(75)));
        self::assertSame([0.0, 50.0], $this->xs((new LayoutHarness())->layout($row, 200)->fragment));
    }

    public function test_shrink_wraps_text_in_narrow_row(): void
    {
        $row = Row::make(View::make('aaaa bbbb'), View::make('cccc dddd'));
        $f = (new LayoutHarness())->layout($row, 60)->fragment;
        self::assertSame(28.0, $f?->height); // each item shrinks to 30 and wraps to 2 lines
    }

    public function test_align_items_center_and_stretch_backgrounds(): void
    {
        $tall = View::make('a', 'b');
        $short = View::make('c')->background(Color::white());
        $f = (new LayoutHarness())->layout(Row::make($tall, $short), 100)->fragment;
        $bg = null;
        foreach ($f?->children() ?? [] as $c) {
            foreach ($c['fragment']->ops() as $op) {
                if ($op instanceof Rect) { $bg = $op; }
            }
        }
        self::assertSame(28.0, $bg?->height); // stretched to line height
        $runs = LayoutHarness::runs((new LayoutHarness())->layout(Row::make($tall, View::make('c'))->align(Align::Center), 100)->fragment);
        self::assertSame(17.0, $runs[2]['baseline']); // (28 - 14) / 2 + 10
    }

    public function test_wrap_breaks_into_lines(): void
    {
        $row = Row::make(View::make('aaaa')->width(pt(60)), View::make('bbbb')->width(pt(60)))->wrap()->gap(pt(10));
        $f = (new LayoutHarness())->layout($row, 100)->fragment;
        self::assertSame(38.0, $f?->height); // 14 + gap 10 + 14
    }

    public function test_row_moves_as_unit_when_not_fresh(): void
    {
        self::assertSame(Status::DoesNotFit, (new LayoutHarness())->layout(Row::make(View::make('a', 'b')), 100, 20, fresh: false)->status);
    }

    public function test_parallel_split_when_fresh(): void
    {
        $row = Row::make(Column::make('a', 'b', 'c')->minLines(1), Column::make('x')->minLines(1));
        $h = new LayoutHarness();
        $r = $h->layout($row, 100, 30, fresh: true);
        self::assertSame(Status::Partial, $r->status);
        self::assertNotNull($r->token?->parallel[0] ?? null);
        self::assertNull($r->token?->parallel[1] ?? 'missing');
        $rest = $h->layout($row, 100, null, $r->token);
        self::assertSame(['c'], array_column(LayoutHarness::runs($rest->fragment), 'text'));
    }

    public function test_intrinsic(): void
    {
        $s = (new LayoutHarness())->engine->intrinsic(Row::make('aa', 'bbbb')->gap(pt(5)), \Papercut\Style\ComputedStyle::root(\Papercut\Style\Theme::default()));
        self::assertSame(35.0, $s['max']);
    }
}
```
Note on `test_parallel_split_when_fresh`: the token at index of `parallel[1]` must be `null` because the second column completed. Use `array_key_exists(1, $r->token->parallel)` plus `assertNull` if PHPStan flags the `?? 'missing'` idiom.

- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add flexbox row layout with wrap and parallel fragmentation`

---

### Task 8: Image layout

**Files:**
- Create: `src/Layout/ImageLayouter.php`
- Modify: `src/Layout/LayoutEngine.php`
- Test: `tests/Layout/ImageLayouterTest.php`

**Rules:**
- Intrinsic size: px × 0.75 (96 dpi).
- Size resolution:
  - both width and height set → use them;
  - only one set → derive the other from the aspect ratio;
  - neither → intrinsic, scaled down to `c.width` if wider.
  - Percent width is relative to `c.width`; percent height to `c.definiteHeight` (null → treated as Auto).
- Fit within the resolved box:
  - Contain: scale to fit, center, keep aspect.
  - Cover: scale to fill, center, add a `Clip` op around the `DrawImage`.
  - Fill: stretch.
- Fragmentation: the image is monolithic. Height > c.height → `DoesNotFit` (even on a fresh page; the Paginator applies the overflow policy).
- Padding/border/background apply via `BoxGeometry`.
- `intrinsic()`: min = max = resolved width when the width is a Length, else the intrinsic width.
- The image handle loads via `LayoutContext::image()` (cached). `ImageUnreadable` propagates.

- [ ] **Step 1: Write failing tests:**
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\Image;
use Papercut\Layout\Status;
use Papercut\Paint\{Clip, DrawImage};
use Papercut\Style\ImageFit;
use Papercut\Tests\Support\LayoutHarness;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class ImageLayouterTest extends TestCase
{
    // FakeBackend images are 100x50 px => 75x37.5 pt intrinsic
    private function draw(\Papercut\Layout\Fragment $f): DrawImage
    {
        foreach ($f->ops() as $op) {
            if ($op instanceof DrawImage) { return $op; }
            if ($op instanceof Clip) { return $op->ops[0]; }
        }
        self::fail('no image op');
    }

    public function test_intrinsic_size(): void
    {
        $f = (new LayoutHarness())->layout(Image::bytes('x'), 500)->fragment;
        self::assertSame([75.0, 37.5], [$f?->width, $f?->height]);
    }

    public function test_scales_down_to_available_width(): void
    {
        $f = (new LayoutHarness())->layout(Image::bytes('x'), 30)->fragment;
        self::assertSame([30.0, 15.0], [$f?->width, $f?->height]);
    }

    public function test_one_dimension_keeps_aspect(): void
    {
        $f = (new LayoutHarness())->layout(Image::bytes('x')->height(pt(10)), 500)->fragment;
        self::assertSame([20.0, 10.0], [$f?->width, $f?->height]);
    }

    public function test_contain_and_cover(): void
    {
        $h = new LayoutHarness();
        $contain = $this->draw($h->layout(Image::bytes('x')->width(pt(40))->height(pt(40)), 500)->fragment);
        self::assertSame([0.0, 10.0, 40.0, 20.0], [$contain->x, $contain->y, $contain->width, $contain->height]);
        $f = $h->layout(Image::bytes('x')->width(pt(40))->height(pt(40))->fit(ImageFit::Cover), 500)->fragment;
        self::assertInstanceOf(Clip::class, $f?->ops()[0]);
        $cover = $this->draw($f);
        self::assertSame([-20.0, 0.0, 80.0, 40.0], [$cover->x, $cover->y, $cover->width, $cover->height]);
    }

    public function test_does_not_fit(): void
    {
        self::assertSame(Status::DoesNotFit, (new LayoutHarness())->layout(Image::bytes('x'), 500, 10)->status);
    }
}
```
- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add image layout with contain/cover/fill`

---

### Task 9: Table layout

**Files:**
- Create: `src/Layout/TableLayouter.php`
- Modify: `src/Layout/LayoutEngine.php`
- Test: `tests/Layout/TableLayouterTest.php`

**Rules:**
- Column count N = max over rows of Σ cell spans. Tracks: given TrackList; missing tracks → `1fr`; extra tracks ignored. Either case calls `ctx->warn("Table has N columns but M tracks")`.
- Column widths, in order:
  1. Length tracks fixed.
  2. Percent tracks of the table content width.
  3. Auto tracks: max-content of the cells in that column (span-1 cells only), capped so the total ≤ W, using min-content if the max-contents overflow.
  4. Remaining space split among Fr tracks proportionally (an Fr track with no remaining space gets its column's min-content; a warning on overflow).
- A cell with colspan k gets the sum of its k column widths.
- Cells are block containers: they lay out like a Column with `ComputedStyle` padding defaulting to `theme->cellPadding` when the cell has no own padding. `th` cells default to `fontWeight 700`.
- Rows: each cell is laid out at its width (unbounded height); row height = max cell outer height; every cell is stretched to the row height (Stretch semantics for backgrounds/borders).
- Default borders: none (users style cells: `TableCell->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))` etc.).
- Fragmentation:
  - Header rows = `table->headerRows()`, always placed at the top of the first fragment and, when `repeatsHeader()`, of every continuation fragment.
  - A body row that doesn't fit → break before it (Partial with `index` = row index) if at least one body row was placed in this fragment; else, if `!fresh` → DoesNotFit; if fresh and the row is taller than the remaining space after the header → parallel split across its cells (`parallel` tokens per cell, like flex rows). A continuation repeats the header and then resumes the split cells.
  - `keepTogether` on the table → DoesNotFit unless fresh, like the BlockLayouter.
- `intrinsic()`: min/max = Σ over columns of the per-column min/max (fixed tracks count as their length).

- [ ] **Step 1: Write failing tests:**
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Table, TableCell};
use Papercut\Layout\Status;
use Papercut\Tests\Support\LayoutHarness;
use PHPUnit\Framework\TestCase;

final class TableLayouterTest extends TestCase
{
    // cell padding 4pt 6pt => cell height 14 + 8 = 22
    public function test_column_widths_fixed_fr_auto(): void
    {
        $t = Table::make()->columns('100pt 1fr auto')->row('a', 'b', 'cc');
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($t, 400)->fragment);
        // auto col = 10 + 12 padding = 22 => fr col = 400 - 100 - 22 = 278
        self::assertSame([6.0, 106.0, 384.0], array_column($runs, 'x'));
    }

    public function test_colspan(): void
    {
        $t = Table::make()->columns('1fr 1fr')->row(TableCell::make('wide')->colspan(2)->right());
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($t, 200)->fragment);
        self::assertSame(174.0, $runs[0]['x']); // 200 - 6 - 20
    }

    public function test_header_repeats_on_continuation(): void
    {
        $t = Table::make()->columns('1fr')->head('H')->rows(range(1, 5), fn (int $i) => ["r$i"]);
        $h = new LayoutHarness();
        $first = $h->layout($t, 100, 66); // header 22 + 2 rows
        self::assertSame(Status::Partial, $first->status);
        self::assertSame(['H', 'r1', 'r2'], array_column(LayoutHarness::runs($first->fragment), 'text'));
        $second = $h->layout($t, 100, null, $first->token);
        self::assertSame(['H', 'r3', 'r4', 'r5'], array_column(LayoutHarness::runs($second->fragment), 'text'));
        $noRepeat = $h->layout($t->repeatHeader(false), 100, null, $first->token);
        self::assertSame(['r3', 'r4', 'r5'], array_column(LayoutHarness::runs($noRepeat->fragment), 'text'));
    }

    public function test_th_is_bold_by_default(): void
    {
        $f = (new LayoutHarness())->layout(Table::make()->head('H')->row('b'), 100)->fragment;
        self::assertNotNull($f);
        $fonts = [];
        $walk = function (\Papercut\Layout\Fragment $fr) use (&$walk, &$fonts): void {
            foreach ($fr->ops() as $op) {
                if ($op instanceof \Papercut\Paint\TextRun) { $fonts[$op->text] = $op->font->id(); }
            }
            foreach ($fr->children() as $c) { $walk($c['fragment']); }
        };
        $walk($f);
        self::assertSame('fake:Inter-Bold.ttf', $fonts['H']);
        self::assertSame('fake:Inter-Regular.ttf', $fonts['b']);
    }

    public function test_track_mismatch_warns_and_defaults_to_fr(): void
    {
        $h = new LayoutHarness();
        $r = $h->layout(Table::make()->columns('1fr 1fr')->row('a', 'b', 'c'), 300);
        self::assertSame(Status::Complete, $r->status);
        self::assertCount(1, $h->warnings);
        self::assertSame([6.0, 106.0, 206.0], array_column(LayoutHarness::runs($r->fragment), 'x'));
    }

    public function test_not_fresh_first_row_does_not_fit(): void
    {
        $t = Table::make()->columns('1fr')->row('a');
        self::assertSame(Status::DoesNotFit, (new LayoutHarness())->layout($t, 100, 10, fresh: false)->status);
    }
}
```

- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add table layout with column sizing and repeated headers`

---

### Task 10: Paginator, overflow policy, Painter

**Files:**
- Create: `src/Layout/Paginator.php`, `src/Paint/Painter.php`
- Modify: `src/Layout/LayoutContext.php` (add `?string $lastFailurePath` + setter), `src/Layout/LayoutEngine.php` (record the path when a layouter returns DoesNotFit under fresh constraints), `src/Layout/LeafLayouter.php` (PageNumber)
- Test: `tests/Layout/PaginatorTest.php`, `tests/Paint/PainterTest.php`

**Interfaces:**
- `final class Paginator { __construct(LayoutEngine $engine, LayoutContext $ctx); /** @return list<PlacedPage> */ paginate(Document $doc): array }` with `final readonly class PlacedPage { float $width; float $height; list<array{x: float, y: float, fragment: Fragment}> $layers }` (background, header, body, footer, in paint order).
- Algorithm per `Page` template, in document order (each template starts on a new physical page):
  1. Root style = `ComputedStyle::root(theme)`. Content box = page minus margins.
  2. Header: lay out `Column::make(...headerNodes)` once at content width, unbounded → `hH` (0 if no header). Footer the same → `hF`. Gaps apply only when the slot exists.
  3. Body box: x = left margin, y = top + hH + headerGap, width = content width, height = content height − hH − hF − gaps. If that height ≤ 0 → `LayoutOverflow('page', 'header and footer leave no room for content')`.
  4. Body node = `Column::make(...bodyNodes)`. Loop: `res = engine.layout(body, root, Constraints(width, bodyHeight, fresh: true), token)`.
     - Complete → place, done.
     - Partial → place, token = res.token, next physical page. Guard: if the token equals the previous token (no progress), treat it as overflow.
     - DoesNotFit on a fresh page → overflow:
       - Throw mode → `throw new LayoutOverflow(ctx.pathOfLastFailure(), 'content is taller than the page body')`. LayoutEngine records the path of the deepest node that returned DoesNotFit on a fresh constraint in `LayoutContext::$lastFailurePath`.
       - Clip mode → re-lay out with `height: null` (unbounded), wrap the fragment in a clip to the body box, `ctx.warn('Content clipped: …')`, finish this template.
  5. Background: `Column::make(...backgroundNodes)` laid out at the full page size (`definiteHeight` = page height) on every physical page, behind everything else.
  6. Safety cap: more than 10,000 physical pages → `LayoutOverflow('document', 'more than 10000 pages')`.
- `final class Painter { /** @param list<PlacedPage> $pages @return list<PageDisplayList> */ static paint(array $pages): array }`:
  - Flattens fragment trees to absolute coordinates.
  - Fragments with `opacity` wrap their ops (and their descendants' ops) in `Opacity`; fragments with `clip` wrap them in `Clip`.
  - Resolves `PageNumberSlot`s: text = `strtr(format, ['{page}' => n, '{total}' => count])`, x per align within the slot width using `font->advance()`, emitting a `TextRun`.
- `PageNumber` layout (add it to `LeafLayouter` in this task): a single-line box with height = line-height box of its computed style and width = c.width. It emits a `PageNumberSlot` (not a TextRun). Its intrinsic max is the format with `{page}`/`{total}` replaced by `"999"`.

- [ ] **Step 1: Write failing tests**

`tests/Layout/PaginatorTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Document, Image, OverflowMode, Page, PageNumber, Text};
use Papercut\Exception\LayoutOverflow;
use Papercut\Layout\{LayoutContext, LayoutEngine, Paginator};
use Papercut\Paint\Painter;
use Papercut\Paint\TextRun;
use Papercut\Style\Theme;
use Papercut\Tests\Support\FakeBackend;
use Papercut\Text\FontRegistry;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class PaginatorTest extends TestCase
{
    /** @var list<string> */
    private array $warnings = [];

    private function paginate(Document $doc, OverflowMode $mode = OverflowMode::Throw): array
    {
        $b = new FakeBackend();
        $ctx = new LayoutContext(FontRegistry::withDefaults($b), Theme::default(), $b, $mode, function (string $w): void { $this->warnings[] = $w; });
        return Painter::paint((new Paginator(new LayoutEngine($ctx), $ctx))->paginate($doc));
    }

    /** @return list<string> */
    private function texts(\Papercut\Paint\PageDisplayList $p): array
    {
        return array_values(array_map(fn (TextRun $r) => $r->text, array_filter($p->ops, fn ($o) => $o instanceof TextRun)));
    }

    private function small(): Page
    {
        // 200x100 page, 10pt margins -> body 180x80 minus header/footer
        return Page::size(pt(200), pt(100))->margin(pt(10))->headerGap(pt(0))->footerGap(pt(0));
    }

    public function test_flows_body_across_pages_with_header_footer_and_page_numbers(): void
    {
        $lines = array_map(fn (int $i) => Text::make("L$i"), range(1, 6));
        $doc = Document::make($this->small()->header('Head')->footer(PageNumber::make('{page}/{total}'))->body(...$lines));
        $pages = $this->paginate($doc);
        // body height = 80 - 14 - 14 = 52 => 3 lines (42) per page
        self::assertCount(2, $pages);
        self::assertSame(['Head', 'L1', 'L2', 'L3', '1/2'], $this->texts($pages[0]));
        self::assertSame(['Head', 'L4', 'L5', 'L6', '2/2'], $this->texts($pages[1]));
    }

    public function test_each_page_template_starts_new_page(): void
    {
        $pages = $this->paginate(Document::make($this->small()->body('A'), $this->small()->body('B')));
        self::assertCount(2, $pages);
    }

    public function test_overflow_throws_with_path(): void
    {
        $this->expectException(LayoutOverflow::class);
        $this->expectExceptionMessageMatches('/image/');
        $this->paginate(Document::make($this->small()->body(Image::bytes('x')->height(pt(500)))));
    }

    public function test_overflow_clips_in_clip_mode(): void
    {
        $pages = $this->paginate(Document::make($this->small()->body(Image::bytes('x')->height(pt(500)))), OverflowMode::Clip);
        self::assertCount(1, $pages);
        self::assertNotEmpty($this->warnings);
    }

    public function test_header_footer_too_big(): void
    {
        $this->expectException(LayoutOverflow::class);
        $tall = Text::make("a\nb")->size(pt(40));
        $this->paginate(Document::make($this->small()->header($tall)->footer($tall)->body('x')));
    }

    public function test_page_size_carried_to_display_list(): void
    {
        $pages = $this->paginate(Document::make(Page::a4()->body('x')));
        self::assertEqualsWithDelta([595.28, 841.89], [$pages[0]->width, $pages[0]->height], 0.01);
    }
}
```

`tests/Paint/PainterTest.php`: build a `PlacedPage` by hand with nested fragments (child at (10, 20) containing a TextRun at (1, 2); a fragment with opacity 0.5; a fragment with a clip; a `PageNumberSlot` with align Right in a 100pt slot, FakeFont 10pt, format `"{page} of {total}"` on page 1 of 1, text `"1 of 1"` = 30pt wide, so x = 70). Assert the absolute coordinates `(11, 22)`, an `Opacity` op wrapping the child op, a `Clip` op, and the TextRun at x = 70.

- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): add paginator with header/footer slots, overflow policy and painter`

---

### Task 11: Document rendering facade + end-to-end PDFs

**Files:**
- Modify: `src/Document.php` (add `render(?RenderOptions): string`, `toString(?RenderOptions)`, `save(string $path, ?RenderOptions)`, `stream(?RenderOptions)` → writes to `php://output` and returns the byte count)
- Test: `tests/Feature/EndToEndTest.php`

**Rules:**
- `render()`: backend = `options->backend ?? new PhpPdfBackend()`; fonts = `options->fonts ?? FontRegistry::withDefaults($backend)`. The registry must be built on the same backend instance that renders: if a custom registry was given with a different backend, the PhpPdf backend throws `LogicException` on foreign fonts, which is acceptable and documented. Then theme; then `LayoutContext` → `LayoutEngine` → `Paginator` → `Painter::paint` → `backend->render(meta, pages)`.
- `save()` creates parent directories? No: if the dir is missing → `\RuntimeException("Directory does not exist: …")`.

- [ ] **Step 1: Write failing tests** (`tests/Feature/EndToEndTest.php`; uses the real backend + `PdfInspector`):
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\{Column, Document, Page, PageNumber, Row, Spacer, Table, Text};
use Papercut\Tests\Support\PdfInspector;
use PHPUnit\Framework\TestCase;

use function Papercut\{mm, pt};

final class EndToEndTest extends TestCase
{
    private function transcript(int $rows): Document
    {
        $courses = array_map(fn (int $i) => ["Course $i", (string) ($i % 4 + 1), ['A', 'B', 'C'][$i % 3]], range(1, $rows));
        return Document::make(
            Page::a4()->margin(mm(20))
                ->header(Row::make(Text::make('Official Transcript')->bold()->size(pt(16)), Spacer::flex(), Text::make('OLC')))
                ->body(Column::make(
                    Text::h2('Student: Ada Lovelace'),
                    Table::make()->columns('3fr 25mm 20mm')->head('Course', 'Credits', 'Grade')
                        ->rows($courses, fn (array $c) => [$c[0], $c[1], Text::make($c[2])->right()]),
                )->gap(pt(8)))
                ->footer(PageNumber::make('Page {page} of {total}')->center()),
        )->title('Transcript')->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function test_multi_page_transcript(): void
    {
        $pdf = $this->transcript(120)->toString();
        PdfInspector::check($pdf);
        $pages = (int) PdfInspector::info($pdf)['Pages'];
        self::assertGreaterThanOrEqual(2, $pages);
        $text = PdfInspector::text($pdf);
        self::assertStringContainsString("Page 1 of $pages", $text);
        self::assertStringContainsString("Page $pages of $pages", $text);
        // header row repeats on page 2
        $page2 = array_column(PdfInspector::words($pdf, 2), 'text');
        self::assertContains('Course', $page2);
        self::assertContains('Credits', $page2);
        self::assertStringContainsString('Course 120', $text);
    }

    public function test_deterministic(): void
    {
        self::assertSame($this->transcript(30)->toString(), $this->transcript(30)->toString());
    }

    public function test_save(): void
    {
        $path = sys_get_temp_dir() . '/papercut-e2e-' . getmypid() . '.pdf';
        $this->transcript(5)->save($path);
        self::assertFileExists($path);
        PdfInspector::check((string) file_get_contents($path));
        unlink($path);
    }
}
```
- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(core): render documents end to end through the backend`

---

### Task 12: Plain-PHP examples

**Files:**
- Create: `examples/core/invoice.php`, `examples/core/transcript.php`, `examples/core/report.php`, `examples/core/README.md`
- Test: `packages/core/tests/Feature/ExamplesTest.php` (runs each example in a subprocess, asserts exit 0 and that the output PDF passes `PdfInspector::check`)

**Requirements:**
- Each example writes `examples/core/<name>.pdf` (git-ignored) and prints the path.
- `invoice.php`: logo-less letterhead Row (company block left; "INVOICE" + number/date right), bill-to block, a line-item table (`columns('1fr 20mm 25mm 25mm')`, header row background `slate-100`, right-aligned numbers, bottom borders `slate-200`), a totals block right-aligned (subtotal/tax/total, total bold), and a footer with payment terms + `PageNumber`.
- `transcript.php`: the same shape as `EndToEndTest::transcript()` with a student card (rounded background `slate-50`, padding 12pt, `keepTogether`), 60 course rows, and a summary row (GPA) that uses `keepWithNext` on its heading.
- `report.php`: a multi-page report with h1/h2 headings, justified paragraphs (lorem-free: use real prose about the library's design, at least 8 paragraphs), a two-column Row of "stat cards" (`grow(1)`, `gap(8pt)`), one 80-row table, and a `PageBreak` before an appendix section.
- Data is inline arrays (no Faker dependency).

- [ ] **Step 1:** Write `ExamplesTest` (data provider over the three names; `exec(PHP_BINARY . ' examples/core/<name>.php')` from the repo root; check the output) → FAIL.
- [ ] **Step 2:** Write the three examples → PASS.
- [ ] **Step 3:** Open each PDF image for a visual sanity check: `pdftoppm -png -r 60 -f 1 -l 1 examples/core/invoice.pdf /tmp/invoice` and read the PNG. Fix any glaring layout bugs, adding a regression test in the relevant layouter test file for each.
- [ ] **Step 4: Commit** `docs(core): add invoice, transcript and report examples`
