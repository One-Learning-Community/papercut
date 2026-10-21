# Decorators

Decorators are ready-made components for the things most documents end up needing: a bordered card, a highlighted note, a numbered section, an answer box. You could build all of these from plain boxes, but then every app reinvents them with its own colours. Decorators take their look from the theme, so they match across all your documents and you can restyle them in one place.

There are eight of them:

| Decorator | PHP | Blade |
|---|---|---|
| [Card](#card) | `Card` | `<x-doc::card>` |
| [Callout](#callout) | `Callout` | `<x-doc::callout>` |
| [Blockquote](#blockquote) | `Blockquote` | `<x-doc::blockquote>` |
| [Section](#section) | `Section` | `<x-doc::section>` |
| [Field](#field) | `Field` | `<x-doc::field>` |
| [Choice list](#choice-list) | `ChoiceList` | `<x-doc::choices>` |
| [Key-value list](#key-value-list) | `KeyValueList` | `<x-doc::key-value>` |
| [Badge](#badge) | `Badge` | `<x-doc::badge>` |

The PHP classes are in the `Papercut\Decorator` namespace. Under the hood, each one is built from ordinary boxes, text and tables, so it breaks across pages like anything else and looks the same in PDF and HTML. Style methods and attributes apply to the outer box, so `Card::make(...)->padding(pt(12))` changes the padding for that one card only.

## Card

A bordered box, with an optional title bar.

::: code-tabs#style
@tab Blade
```blade
<x-doc::card title="Respondent">
  <x-doc::text>Jane Smith</x-doc::text>
</x-doc::card>

<x-doc::card>
  <x-slot:title><x-doc::text>Rich <b>title</b></x-doc::text></x-slot:title>
  …
</x-doc::card>
```
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Decorator\Card;

$document = Document::make(Page::a4()->body(
    Card::make(Text::make('Jane Smith'))->title('Respondent'),
));
```
:::

The title can be a string or any component, and it always stays on the same page as the start of the body.

## Callout

A tinted box with a coloured bar down the left side, for notes and warnings.

::: code-tabs#style
@tab Blade
```blade
<x-doc::callout type="warning" title="Before you start">
  Answers cannot be changed once submitted.
</x-doc::callout>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\Callout;

$document = Document::make(Page::a4()->body(
    Callout::make('Answers cannot be changed once submitted.')->tone('warning')->title('Before you start'),
));
```
:::

The tones are `note` (the default), `tip`, `warning` and `danger`. Papercut doesn't add a label like "Warning" on its own, so the wording and any translation stay in your hands. In HTML, a callout becomes an `<aside>`.

## Blockquote

A quotation with a bar down the side and an optional attribution.

::: code-tabs#style
@tab Blade
```blade
<x-doc::blockquote cite="Jane Smith, closing round">
  I came for the PDFs and stayed for the pagination.
</x-doc::blockquote>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\Blockquote;

$document = Document::make(Page::a4()->body(
    Blockquote::make('I came for the PDFs and stayed for the pagination.')->cite('Jane Smith, closing round'),
));
```
:::

The citation appears below the quote, after an em dash. Long quotes can split across pages. For a short one, add `keep-together` so the quote and its citation stay on the same page.

## Section

A heading with its content, an optional number, and an optional line under the heading.

::: code-tabs#style
@tab Blade
```blade
<x-doc::section number="3" title="Search terms" rule keep-together>
  …
</x-doc::section>

<x-doc::section number="3">
  <x-slot:title><x-doc::markup :text="$prompt" /></x-slot:title>
  …
</x-doc::section>
```
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Decorator\Section;

$document = Document::make(Page::a4()->body(
    Section::make(Text::p('Library catalogue, then the open web.'))
        ->number('3')
        ->title('Search terms')
        ->rule()
        ->keepTogether(),
));
```
:::

The number is a string, so `3`, `3.2` and `C` all work. Papercut doesn't count sections for you or add punctuation, and the theme controls how the number looks. With `keep-together`, a section that fits on one page is never split. A longer one still breaks, but its heading always stays with the start of its content.

## Field

A bordered box showing an answer or value, with a placeholder when it's empty.

::: code-tabs#style
@tab Blade
```blade
<x-doc::field label="Comments" placeholder="No response" lines="3">{{ $comments }}</x-doc::field>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\Field;

$comments = '';

$document = Document::make(Page::a4()->body(
    Field::make($comments)->label('Comments')->placeholder('No response')->lines(3),
));
```
:::

A field counts as empty if it has no content or only whitespace. `lines` sets a minimum height in lines of text, so the box scales with the theme's font size and line height. If those lines won't fit in the space left on the page, the field moves to the next page.

Without a label, style attributes apply to the box. With a label, they apply to the label and box together. To style only the box, use the theme's `field` style.

## Choice list

A list of options with checkboxes or radio buttons, some of them ticked.

::: code-tabs#style
@tab Blade
```blade
<x-doc::choices type="checkbox" emphasize-selected>
  <x-doc::choice selected>Library catalogue</x-doc::choice>
  <x-doc::choice selected>Web search</x-doc::choice>
  <x-doc::choice>Asked a colleague</x-doc::choice>
</x-doc::choices>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\ChoiceList;

$document = Document::make(Page::a4()->body(
    ChoiceList::checkbox()
        ->option('Library catalogue', selected: true)
        ->option('Web search', selected: true)
        ->option('Asked a colleague')
        ->emphasizeSelected(),
));
```
:::

`type` is `checkbox` (the default) or `radio`. In PHP, use `ChoiceList::checkbox()` or `ChoiceList::radio()`. `emphasize-selected` makes the selected labels bold.

The checkboxes and radio buttons are drawn as shapes, so they don't depend on your font having ☐ or ● characters, and they look the same in PDF and HTML. They're sized to match the text and line up with the first line of each label. A long list can break across pages between options, but never in the middle of one.

## Key-value list

Labels and values in two neatly aligned columns.

::: code-tabs#style
@tab Blade
```blade
<x-doc::key-value label-width="30%">
  <x-doc::kv label="Respondent">Jane Smith</x-doc::kv>
  <x-doc::kv label="Submitted">2026-03-02 14:30</x-doc::kv>
  <x-doc::kv label="Overall"><x-doc::badge type="tip">Agree</x-doc::badge></x-doc::kv>
</x-doc::key-value>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\{Badge, KeyValueList};
use function Papercut\percent;

$document = Document::make(Page::a4()->body(
    KeyValueList::make()
        ->row('Respondent', 'Jane Smith')
        ->row('Submitted', '2026-03-02 14:30')
        ->row('Overall', Badge::make('Agree')->tone('tip'))
        ->labelWidth(percent(30)),
));
```
:::

Under the hood it's a table without borders, so the labels line up and a long list breaks cleanly between rows. Without `label-width`, the label column is as wide as the longest label. If your labels are long, set a width so they wrap instead of squeezing the values.

## Badge

A small rounded label, for statuses and tags.

::: code-tabs#style
@tab Blade
```blade
<x-doc::badge type="warning">Overdue</x-doc::badge>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\Badge;

$document = Document::make(Page::a4()->body(
    Badge::make('Overdue')->tone('warning'),
));
```
:::

The tones are `neutral` (the default), `note`, `tip`, `warning` and `danger`, matching the callout colours. A badge is a block that's only as wide as its text. It can't be placed in the middle of a line of text.

## Restyling

Each decorator gets its look from named styles in the theme, such as `card`, `card.title` and `callout.warning`. To change one, define a style with the same name and it replaces the built-in version.

::: code-tabs#style
@tab Laravel config
```php
// config/papercut.php
'styles' => [
    'callout.warning' => ['border-color' => 'orange-500', 'background' => 'orange-50'],
],
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\Callout;
use Papercut\Export\PdfOptions;
use Papercut\Style\{Style, Theme};
use Papercut\Value\Color;

$theme = Theme::default()->withStyle('callout.warning', Style::make(
    borderColor: Color::parse('orange-500'),
    background: Color::parse('orange-50'),
));

$document = Document::make(Page::a4()->body(Callout::make('Mind the gap.')->tone('warning')));
$options = new PdfOptions(theme: $theme);
// $document->toPdf($options)->save('out.pdf');
```
:::

Your style replaces the built-in one entirely rather than merging with it, so include every property you want to keep. The [decorator styles reference](/reference/decorator-styles.md) lists every name and its defaults.

### Adding a tone

You're not limited to the built-in tones. Define the styles for a new one and use its name:

```php
'styles' => [
    'callout.success'       => ['border-color' => 'green-600', 'background' => 'green-50'],
    'callout.title.success' => ['color' => 'green-800'],
    'badge.success'         => ['background' => 'green-100', 'color' => 'green-800'],
],
```

```blade
<x-doc::callout type="success" title="Submitted">Thank you.</x-doc::callout>
<x-doc::badge type="success">Passed</x-doc::badge>
```

## A full example

The `responses` example uses all eight decorators to lay out a completed feedback form. It's available in [Blade](https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/responses.blade.php) and in [PHP](https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/responses.php).
