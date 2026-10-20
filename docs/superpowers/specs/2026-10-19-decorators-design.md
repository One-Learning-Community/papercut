# Papercut: Decorator Containers — Design

**Status:** scope agreed with the maintainer, 2026-10-19; they asked the designer to proceed without further
questions. Items marked *(my call)* were decided by the designer and are open to review.

## 1. Intent

Papercut has boxes, text, lists and tables, but no ready-made pieces that give content a look. Every app that wants a
bordered answer box, a tinted note or an aligned "Label: value" row builds it by hand from `View` and hex colours.

The first consumer is the question-set export in the platform (`spike/papercut-question-sets`,
`packages/olc/course-designer/resources/views/pdf/`). Its PDFs record how one person answered a survey or quiz. The
reader checks what was asked and what the person said. Reviewing the dogfood PDFs (sets 1, 4, 6, 13, 133) found:

1. **Instructions look like questions.** Informational slides ("Now, let's refine your search…") use the same bold
   paragraph style as question prompts. Nothing is numbered.
2. **Answers look like prompts.** A true/false answer is bold text under the prompt and reads as another prompt.
3. **Unanswered questions show nothing.** Some types say "No response"; others leave a gap.
4. **Hand-built widgets with hardcoded colours.** `#999999`, `#808080`, `#666666`, `#dedede` across eight templates;
   a choice list built from a 10pt column holding "×"; a grey "Response: 7" box.
5. **Likert rows read awkwardly:** "…initiatives.: **Agree**".
6. **No header** saying whose response this is or when.

The maintainer wants the export to be nicer **without** decorators that are specific to it. So Papercut ships
general-purpose decorators, and the app builds its own `question` Blade component from them. Words like "question",
"quiz" and "response" never appear in Papercut.

## 2. Goals, non-goals, anti-goals

**Goals**
- Eight decorators: **Card**, **Callout**, **Blockquote**, **Section**, **Field**, **ChoiceList**, **KeyValueList**,
  **Badge**. Each has a core PHP API and a Blade tag.
- **Highlight**: a background colour on an inline run, so `Markup::html()` can map `<mark>`.
- Defaults come from the theme and can be overridden there, so an app restyles every callout in one place.
- Each decorator works in PDF and HTML export, and in DOCX later with no extra work (§4.1).
- The question-set templates can be rewritten with these pieces and no hex colours (§9 sketches it).

**Non-goals (this version)**
- Grid, media object, figure, watermark, divider, keep-together group. They were on the candidate list but the
  consumer doesn't need them. Follow-up work.
- Automatic numbering (counters). Section numbers are passed in explicitly (§5.4).
- Inline boxes. A Badge is a block-level box and can't sit inside a line of text.
- Icons in callouts. Fonts don't reliably have the glyphs; callouts get a coloured rule and an optional title.

**Anti-goals** (failures even if it works)
- Any quiz, survey or question vocabulary in Papercut's API, class names, tags or theme style names.
- Any change to existing PDF golden bytes or HTML goldens.
- A decorator that only looks right in one export format.
- A theme that throws `UnknownAttribute` because it was built with `new Theme(...)` and doesn't list decorator styles.

## 3. Constraints

- PHP `^8.4`, strict types, PHPStan level 8 in both packages, no new runtime dependencies.
- PDF golden bytes and HTML goldens unchanged for documents that don't use the new features.
- Blade tags follow the existing component conventions (`PapercutComponent`, `ownAttributes()`, style attributes pass
  through, errors name the tag with `Tag::of()`).
- Markup converter changes keep the untrusted-input rules from `2026-10-19-html-input-design.md`.

## 4. Architecture

### 4.1 Decorators are Components

Each decorator is a `Papercut\Component` in a new `Papercut\Decorator` namespace. `render()` builds a tree from
existing nodes (`View`, `Text`, `Table`, `ListNode`). Consequences:

- **No layout changes.** Pagination, splitting, keep-together and borders on split boxes already work for `View`.
- **No exporter changes** beyond the small hint in §4.3. DOCX inherits every decorator once it handles the base nodes.
- Decorators are immutable builders like the existing nodes (`with…()` returns a clone), and expose the common style
  modifiers on their **outer box** by delegating to `HasStyle`. `$card->padding(pt(12))` overrides the theme padding.

*(my call)* Highlight is the exception: it is a span style, not a container (§5.9), and needs a layout change.

### 4.2 Theme-driven defaults: multi-name styles and built-in decorator styles

Two small changes to `Theme`:

1. **Space-separated style names.** `apply('callout callout.warning')` merges the named styles left to right, like CSS
   classes. `Theme::named()` splits on whitespace, merges each one and reports an unknown name with a suggestion as
   today. `StyleResolver`, `TableLayouter` and the Blade `style=` attribute keep calling `named()` and gain this for
   free.
2. **Built-in decorator styles.** `Theme::named()` looks in the theme's own styles first, then in a built-in map of
   decorator defaults (`Papercut\Decorator\DefaultStyles`). Any theme gets the defaults, including one built with
   `new Theme(...)`, and `withStyle('callout.warning', …)` replaces one entry. Existing documents never name these
   styles, so their output is unchanged.

All built-in names are prefixed by the decorator (`card`, `card.title`, `callout.warning`, …), listed in §6. Tones are
open-ended: `Callout::make()->tone('success')` resolves `callout.success`, so an app adds a tone by adding a style.

### 4.3 HTML element hint on View

`View::htmlTag(string $tag)` sets the element the HTML exporter writes instead of `<div>`. The allowed set is
`div`, `section`, `aside`, `blockquote`, `header`, `footer`, `figure`; anything else throws `InvalidValue` with a
suggestion. PDF ignores it. The HTML writer resets the element's default margins (`margin: 0` before the box CSS) so
`<blockquote>` and `<figure>` don't pick up browser indentation. Uses: Section → `section`, Callout → `aside`,
Blockquote → `blockquote`.

*(my call)* A hint on `View`, not a new node type, keeps one code path for layout and export.

## 5. The decorators

Each section gives the core API, the Blade tag and what it renders to. Lengths below are defaults from §6.

### 5.1 Card

A box with padding, border and radius, plus an optional title strip across the top.

```php
Card::make(...$children)->title('Respondent')        // string or Node
```
```blade
<x-doc::card title="Respondent"> … </x-doc::card>
<x-doc::card><x-slot:title><x-doc::text>Rich <b>title</b></x-doc::text></x-slot:title> … </x-doc::card>
```
Renders: outer `View` (`card`) → optional title `View` (`card.title`, keep-with-next) → body `View` (`card.body`).
The outer box carries the border and radius; the title strip has a background and a bottom border.

### 5.2 Callout

A tinted box with a coloured side rule and an optional title, in a tone.

```php
Callout::make(...$children)->tone('warning')->title('Before you start')
```
```blade
<x-doc::callout type="warning" title="Before you start"> … </x-doc::callout>
```
Tones built in: `note` (default), `tip`, `warning`, `danger`. Renders: `View` styled
`callout callout.<tone>`, `htmlTag('aside')`, children in a column; title as `Text` styled
`callout.title callout.title.<tone>`, keep-with-next. *(my call)* No automatic "Warning" label: the app supplies the
title, which keeps wording and translation in the app.

### 5.3 Blockquote

Quoted content with a side rule and an optional attribution line.

```php
Blockquote::make(...$children)->cite('Jane Smith, 2024')
```
```blade
<x-doc::blockquote cite="Jane Smith, 2024"> … </x-doc::blockquote>
```
Renders: `View` (`blockquote`, `htmlTag('blockquote')`), children, then the cite as `Text` (`blockquote.cite`) with a
leading em dash. `Markup::html()` maps `<blockquote>` to this (§7).

### 5.4 Section

A heading kept with its content, with an optional number and rule.

```php
Section::make(...$children)->title('Search terms')->number('3')->rule()
```
```blade
<x-doc::section title="Search terms" number="3" rule> … </x-doc::section>
<x-doc::section number="3"><x-slot:title><x-doc::markup :text="$prompt" /></x-slot:title> … </x-doc::section>
```
Renders: `View` (`section`, `htmlTag('section')`) → heading `View` (row: number `Text` `section.number`, title
`section.title`, keep-with-next; with `rule()`, a bottom border from `section.rule`) → body column (`section.body`).
`keepTogether()` on a Section keeps the whole section on one page when it fits; content taller than a page still
splits, as today. The number is a string, so apps can pass `3`, `3.2` or `C`; *(my call)* Papercut adds no
punctuation, and the theme decides how the number looks.

### 5.5 Field

A bordered box holding a value, with a placeholder when it's empty and an optional label above.

```php
Field::make(...$children)->label('Comments')->placeholder('No response')->lines(3)
```
```blade
<x-doc::field label="Comments" placeholder="No response" lines="3">{{ $text }}</x-doc::field>
```
Renders: optional label `Text` (`field.label`, keep-with-next) → box `View` (`field`). **Empty** means no children,
or only `Text` children whose spans are all whitespace (`Markup::text('')` counts). An empty field shows the
placeholder as `Text` (`field.placeholder`); with no placeholder set it is an empty box. `lines(n)` sets
`min-height` to n × 14pt, one line of the default theme (10pt × 1.4). Components render without the theme, so this
is fixed; an app with a bigger base size sets `min-height` itself. *(my call)* Text in a Field wraps and splits across pages like any `View`.

### 5.6 ChoiceList

A list of options with drawn radio or checkbox markers, some marked as selected.

```php
ChoiceList::radio()                                   // or ::checkbox()
    ->option('Activity 1', selected: true)            // label is string|Node
    ->option('Activity 2')
    ->emphasizeSelected()                             // selected labels use choice.label.selected
```
```blade
<x-doc::choices type="checkbox" emphasize-selected>
    <x-doc::choice selected><x-doc::markup :html="$label" /></x-doc::choice>
    <x-doc::choice>Activity 3</x-doc::choice>
</x-doc::choices>
```
Markers are **drawn boxes, not glyphs** *(my call)*: a bordered square (checkbox) or circle (radio, radius half the
size) with a filled inner square or dot when selected. This avoids depending on ☐/☒/○/● being in the document font,
and looks the same in PDF and HTML. Each option is a row: marker `View` (`choice.marker` or
`choice.marker.radio`, plus `choice.marker.selected`), label column (grows). The marker is nudged down so it lines up
with the first line of the label by the theme's `choice.marker` margin-top (2.5pt centres it on a 14pt line); an app with
bigger label text overrides that style. Rows keep each option whole;
the list may split between options. Blade: `<x-doc::choice>` is only valid inside `<x-doc::choices>` (same parent
check as `<x-doc::li>`).

### 5.7 KeyValueList

Aligned "label: value" rows.

```php
KeyValueList::make()->row('Respondent', 'Jane Smith')->row('Submitted', $date)   // value: string|Node
```
```blade
<x-doc::key-value>
    <x-doc::kv label="Respondent">Jane Smith</x-doc::kv>
    <x-doc::kv label="I can connect today's learning…"><x-doc::badge>Agree</x-doc::badge></x-doc::kv>
</x-doc::key-value>
```
Renders: a two-column `Table` (`auto`, `1fr`), no borders, one row per pair; label cell `key-value.label`, value
cell `key-value.value`. *(my call)* A table, not a row of views, so labels line up and long lists split between rows.
`->labelWidth(Length|Percent)` fixes the first column, so long labels (Likert statements) wrap instead of squeezing
the values. HTML export writes a `<table>`.

### 5.8 Badge

A small rounded label, in a tone.

```php
Badge::make('Agree')->tone('success')                 // tones: neutral (default), note, tip, warning, danger
```
```blade
<x-doc::badge type="tip">Agree</x-doc::badge>
```
Renders: `View` (`badge badge.<tone>`, `align-self: start` so it hugs its text) holding one `Text`. Block-level
(non-goal: inline badges). Tones match Callout's names so an app can pair them.

### 5.9 Highlight

A background colour on an inline run.

- **Core:** `Span` styles honour `background` (today it's parsed and ignored for spans). Inline markup gains
  `<mark>` → span with background `yellow-200`. *(my call)* Spans have no theme style name, so the colour is fixed;
  `<span style="background-color: …">` in `Markup::html()` sets any other colour. `Text::markup('<mark>key</mark>')`.
- **Layout:** `TextLayouter` paints a rectangle behind each run that has a background, one per line fragment, from
  the run's x-extent and the line box's ascent/descent. It's painted before the text. Runs with no background
  produce no extra ops, so existing PDFs are byte-identical.
- **HTML:** span CSS includes `background-color` when the span sets one.
- **Blade:** `<mark>` inside text tags (the inline markup parser) and `<x-doc::markup>` both work.

## 6. Built-in styles

Names and starting values *(my call; the maintainer can tune them by eye)*. Colours are Tailwind palette names.

| Name | Style |
|---|---|
| `card` | border 1pt `slate-300`, radius 4pt, gap 0 |
| `card.title` | padding 6pt 10pt, background `slate-100`, radius 3pt (the card radius less its border), bottom border 1pt `slate-300`, bold |
| `card.body` | padding 10pt, gap 6pt |
| `callout` | padding 8pt 10pt, left border 3pt, radius 2pt, gap 4pt |
| `callout.note` / `.tip` / `.warning` / `.danger` | border `sky-500` / `emerald-500` / `amber-500` / `red-500`; background the `-50` shade |
| `callout.title` | bold |
| `callout.title.<tone>` | colour the `-800` shade |
| `blockquote` | padding 2pt 0 2pt 12pt, left border 3pt `slate-300`, colour `slate-700`, italic, gap 4pt |
| `blockquote.cite` | not italic, font size 9pt, colour `slate-500` |
| `section` | gap 6pt |
| `section.heading` | row, gap 4pt |
| `section.body` | gap 6pt |
| `section.title` | bold |
| `section.number` | bold, colour `slate-500`, min-width 18pt |
| `section.rule` | bottom border 0.75pt `slate-200`, padding-bottom 3pt |
| `field` | border 1pt `slate-300`, radius 3pt, padding 5pt 7pt |
| `field.label` | font size 9pt, colour `slate-600` |
| `field.placeholder` | italic, colour `slate-400` |
| `choice` | gap 4pt (list) |
| `choice.option` | gap 6pt (each option row: marker and label) |
| `choice.marker` | 9pt × 9pt, border 1pt `slate-400`, radius 1.5pt, margin-top 2.5pt (centres it on a 14pt line), centres its mark |
| `choice.marker.radio` | radius 4.5pt |
| `choice.marker.selected` | border `slate-800` |
| `choice.mark` / `choice.mark.radio` | 5pt × 5pt, background `slate-800`, radius 1pt / 2.5pt |
| `choice.label.selected` | bold |
| `key-value` | no borders, cell padding 2pt 12pt 2pt 0 |
| `key-value.label` | colour `slate-600` |
| `key-value.value` | padding 2pt 0 |
| `badge` | align-self start, padding 1.5pt 6pt, radius 8pt, font size 9pt, bold |
| `badge.neutral` / `.note` / `.tip` / `.warning` / `.danger` | background `slate-100` / `sky-100` / `emerald-100` / `amber-100` / `red-100`; colour the `-800` shade |

`font-size` only takes absolute lengths, so "smaller" text uses 9pt against the default theme's 10pt. An app with a
larger base size overrides those styles.

## 7. Markup converter

- `<blockquote>` → `Blockquote` with the converted children (today it's an unknown block tag converted as a plain
  column; see `HtmlConverter::BLOCKS`). A `<cite>` or `<footer>` that is the last child of a blockquote becomes its
  attribution. *(my call)*
- `<mark>` → highlight span.
- No other mappings change. `<aside>` stays a plain block; it's editor output, not a callout.
- Existing HTML-input tests and goldens stay green; new fixtures cover both tags.

## 8. Testing

- Unit tests per decorator: the rendered tree (node types, style names, keep flags, html tag hints, empty-field
  detection, marker shapes).
- Theme tests: multi-name merge order, unknown name in a list (suggestion), built-in fallback, override via
  `withStyle`, a `new Theme(...)` theme resolving decorator styles.
- Visual snapshots (`tests/Visual`): one fixture per decorator plus one that splits a Card, a Callout and a long
  Field across a page break.
- HTML goldens: one per decorator; check the `aside`/`section`/`blockquote` hints and margin reset.
- Highlight: a PDF test that a span with no background yields byte-identical output, and that a highlighted span
  produces one rect per line fragment.
- Laravel: a feature test per tag, the `choice`-outside-`choices` error, named `title` slots, and that style
  attributes on a decorator tag override the theme.
- Full existing suites green; PDF golden bytes and HTML goldens unchanged.

## 9. The consumer (out of Papercut's scope, for orientation)

In the platform, a `question` Blade component replaces the per-question column:

```blade
<x-doc::section :number="$n" keep-together>
    <x-slot:title><x-doc::markup :text="$question->prompt" /></x-slot:title>
    @if ($question->instructions)
        <x-doc::text style="field.label"><x-doc::markup :text="$question->instructions" /></x-doc::text>
    @endif
    @include($view, $data)          {{-- field / choices / key-value / badge --}}
</x-doc::section>
```

Informational slides become `<x-doc::callout>`. A `<x-doc::key-value>` header at the top lists respondent, submitted
date and course. Short and long text use `<x-doc::field placeholder="No response">`. Updating the platform branch is a
separate follow-up after this ships.

## 10. Left to the builder

- Exact marker vertical offset and drawing (inner mark size).
- Whether decorators delegate style modifiers through a shared trait or a base class.
- Blade attribute names beyond those shown, following existing conventions (kebab-case, booleans as bare attributes).
- Documentation: the Laravel README gains a "Decorators" section; the root README feature list and CHANGELOG
  `[Unreleased]` are updated; an example (`examples/*/`) shows each decorator.
