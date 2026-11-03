# Decorators

Decorators are ready-made components for the things most documents end up needing: a bordered card, a highlighted note, a numbered section, an answer box. You could build all of these from plain boxes, but then every app reinvents them with its own colours. Decorators take their look from the theme, so they match across all your documents and you can restyle them in one place.

There are twelve of them:

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
| [Stat](#stat) | `Stat` | `<x-doc::stat>` |
| [Totals](#totals) | `Totals` | `<x-doc::totals>` |
| [Signature](#signature) | `Signature` | `<x-doc::signature>` |
| [Code block](/guide/text.md#code-blocks) | `CodeBlock` | `<x-doc::pre language="…">` |

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

The title can be a string or any component, and it always stays on the same page as the start of the body. To make it a heading, see [Titles as headings](#titles-as-headings).

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

### Titles as headings

A section or card title is plain text unless you give it a heading level. `heading="1"` to `heading="4"` (`->heading(1)` in PHP) makes the title a real heading: it gets a PDF bookmark, an entry in the [table of contents](/guide/pages.md#tables-of-contents), an `h1` to `h4` tag in HTML and a Heading 1 to 4 style in Word. It keeps the section's or card's own title look.

::: code-tabs#style
@tab Blade
```blade
<x-doc::toc />
<x-doc::section number="1" title="Units" heading="1">
  …
</x-doc::section>
<x-doc::card heading="2">
  <x-slot:title>{{ $unit->name }}</x-slot:title>
  …
</x-doc::card>
```
@tab PHP
```php
use Papercut\{Document, Page, Text, Toc};
use Papercut\Decorator\{Card, Section};

$document = Document::make(Page::a4()->body(
    Toc::make(),
    Section::make(Text::p('Every unit in the course.'))->number('1')->title('Units')->heading(1),
    Card::make(Text::p('Reading and discussion.'))->title('Unit 1: Foundations')->heading(2),
));
```
:::

A numbered section's entry and bookmark include the number, such as "1 Units". To set different text, put `bookmark("…")` on the title `Text`, or in Blade build the title in PHP. The title has to be text. A title slot is joined into one line of text, so a heading tag inside the slot doesn't make a heading; use `heading` instead. In PHP, a title that is a box or other component can't be a heading and throws `InvalidStructure`.

To make any text a heading without the h1 to h4 look, use `Text::make('…')->asHeading(2)`.

## Field

A bordered box showing an answer or value, with a placeholder when it's empty. For a paper-form look, `variant="line"` keeps only a rule along the bottom.

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

The checkboxes and radio buttons are drawn as shapes, so they don't depend on your font having ☐ or ● characters, and they look the same in PDF and HTML. In Word they are written as the characters ☐, ▣, ○ and ◉, so they take the font's look there. They're sized to match the text and line up with the first line of each label. A long list can break across pages between options, but never in the middle of one.

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

The tones are `neutral` (the default), `note`, `tip`, `warning` and `danger`, matching the callout colours. A badge is a block that's only as wide as its text. It can't be placed in the middle of a line of text. For a lighter look, `variant="outline"` draws a border in the tone's colour instead of filling the badge.

## Stat

A figure on a tinted panel: a small label, the value in large type, and an optional note under it. Put several in a row for a summary strip.

::: code-tabs#style
@tab Blade
```blade
<x-doc::row gap="12pt">
  <x-doc::stat label="Revenue" note="Up 12% on Q2" grow="1">$48,200</x-doc::stat>
  <x-doc::stat label="Orders" note="38 refunded" grow="1">1,204</x-doc::stat>
  <x-doc::stat label="Rating" grow="1">4.7</x-doc::stat>
</x-doc::row>
```
@tab PHP
```php
use Papercut\{Document, Page, Row};
use Papercut\Decorator\Stat;
use function Papercut\pt;

$document = Document::make(Page::a4()->body(
    Row::make(
        Stat::make('$48,200')->label('Revenue')->note('Up 12% on Q2')->grow(1),
        Stat::make('1,204')->label('Orders')->note('38 refunded')->grow(1),
        Stat::make('4.7')->label('Rating')->grow(1),
    )->gap(pt(12)),
));
```
:::

The value is the tag's content, and it takes inline tags, so `<span color="emerald-600">+4.2%</span>` colours it. `label` and `note` also work as slots. On its own a stat spans the width like any box. In a row each one is as wide as its content, so give each `grow="1"` to share the width evenly.

## Totals

The rows under an invoice or quote: labels on the left, amounts right-aligned, and the total in bold above a rule.

::: code-tabs#style
@tab Blade
```blade
<x-doc::totals>
  <x-doc::kv label="Subtotal">$1,000.00</x-doc::kv>
  <x-doc::kv label="Tax (8.25%)">$82.50</x-doc::kv>
  <x-doc::kv label="Total due" total>$1,082.50</x-doc::kv>
</x-doc::totals>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\Totals;

$document = Document::make(Page::a4()->body(
    Totals::make()
        ->row('Subtotal', '$1,000.00')
        ->row('Tax (8.25%)', '$82.50')
        ->total('Total due', '$1,082.50'),
));
```
:::

The rows are the same `<x-doc::kv>` tags as a key-value list, and `total` marks the ones to emphasise. Any row can be a total, so a total followed by "Amount paid" and a second total for the balance works too. The block is 45% of the width and sits at the right; change that with `width`, for example `width="2.75in"` or `width="100%"`. Next to a paragraph with `grow="1"` in a row, such as payment notes, the totals keep their width and the paragraph takes the rest.

## Signature

A line to sign on, with the signer's name, role and date under it.

::: code-tabs#style
@tab Blade
```blade
<x-doc::row gap="36pt">
  <x-doc::signature name="Jane Smith" role="Director" date="12 March 2026">
    <x-doc::svg src="signature.svg" height="28pt" />
  </x-doc::signature>
  <x-doc::signature name="Witness" role="Name and date" />
</x-doc::row>
```
@tab PHP
```php
use Papercut\{Document, Page, Row};
use Papercut\Decorator\Signature;
use function Papercut\pt;

$document = Document::make(Page::a4()->body(
    Row::make(
        Signature::make()->name('Jane Smith')->role('Director')->date('12 March 2026'),
        Signature::make()->name('Witness')->role('Name and date'),
    )->gap(pt(36)),
));
```
:::

Whatever the tag holds sits on the line: a signature image, a typed name, or nothing, which leaves room to sign by hand. `name`, `role` and `date` are each optional and also work as slots. Papercut doesn't format the date, so pass it the way the document needs it. A signature is 3 inches wide and never splits across pages.

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

### Variants

A variant restyles every part of a decorator at once. `variant="compact"` makes the decorator also apply `<name>.compact` after each of its built-in names, whenever your theme defines that name. Define only the parts the variant changes:

```php
'styles' => [
    'callout.compact'       => ['padding' => '4pt 8pt', 'gap' => '2pt'],
    'callout.title.compact' => ['font-size' => '0.9em'],
],
```

```blade
<x-doc::callout type="tip" title="Shortcut" variant="compact">...</x-doc::callout>
```

```php
use Papercut\Decorator\Callout;

Callout::make('...')->tone('tip')->title('Shortcut')->variant('compact');
```

Several variants can be given, separated by spaces (`variant="flat compact"`), and apply in that order. A variant is a lowercase word of letters, digits and hyphens, and it cannot be a word that appears in one of the decorator's own part names (`selected` or `radio` on choices, `title` on a card), because the name it produces would be an existing part's. Papercut rejects such a word with an error naming the collision. `callout` and `badge` also reject their own tone words (`note`, `tip`, `warning`, `danger`, and `neutral` on a badge) as variants. Use `tone()` for those. Variants come after the tone (`callout.title.warning`, then `callout.title.compact`) and before your own `style=""` and style modifiers, which still win on the outer box.

In Blade, a variant for which the theme has no style on any part of that decorator throws an error listing the names it looked for. In PHP, a variant nothing styles does nothing, because the decorator has no theme to check against.

A few variants are built in, and your theme can restyle them like any other name:

| Variant | Look |
|---|---|
| `<x-doc::field variant="line">` | A form line: only a rule along the bottom, no box. |
| `<x-doc::badge variant="outline">` | A border in the tone's colour, with no fill. |
| `<x-doc::card variant="flat">` | A tinted panel with no border. |
| `<x-doc::section variant="large">` | A bigger number and title, with more room around them. |

The [code block](/guide/text.md#code-blocks) also has `strip` and `caption` built in.

### Your own decorator

A decorator is a class that implements `Papercut\Decorator\Decorator`. The interface needs `render()`, the style methods, `variant()`, `variants()` and `partNames()`. `use HasStyle` covers the style methods, and `use HasVariants` covers `variant()` and `variants()`. You write `partNames()` (every built-in style name the decorator applies, without tones) and `render()`. Build each name with the `part()` helper, so variants work, and apply the user's own style last:

```php
use Papercut\{Node, Text, View};
use Papercut\Concerns\{HasStyle, HasVariants};
use Papercut\Decorator\Decorator;

final class Stamp implements Decorator
{
    use HasStyle;
    use HasVariants;

    private function __construct(private string $label)
    {
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function partNames(): array
    {
        return ['stamp'];
    }

    public function render(): Node
    {
        return View::make(Text::make($this->label))
            ->apply(trim($this->part('stamp') . ' ' . ($this->styleName() ?? '')))
            ->apply($this->style());
    }
}
```

The theme has no `stamp` style, so the export throws until you add one to your theme's styles. The built-in decorators are final, immutable builders. Follow the same pattern: return clones from your setters.

## A full example

The `responses` example uses eight of the decorators to lay out a completed feedback form. It's available in [Blade](https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/responses.blade.php) and in [PHP](https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/responses.php).
