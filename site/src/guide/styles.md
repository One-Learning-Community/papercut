# Styles and themes

## Style methods

Every component has the same set of style methods in PHP, and the same attributes in Blade. They fall into three groups:

- **Box:** `width`, `height`, `minWidth`, `maxWidth`, `minHeight`, `maxHeight`, `padding`, `margin`, `border`, `borderWidth`, `borderColor`, `radius`, `background`, `opacity`, `grow`, `shrink`, `basis`, `alignSelf`.
- **Text:** `font`, `size`, `weight`, `bold`, `italic`, `color`, `lineHeight`, `letterSpacing`, `left`, `center`, `right`, `justify`, `underline`, `strike`.
- **Pagination:** `keepTogether`, `keepWithNext`, `breakBefore`, `breakAfter`, `minLines`.

Text properties are inherited by everything inside a box, as in CSS. Box properties only apply to the box you set them on.

The [style attributes reference](/reference/style-attributes.md) lists every Blade attribute and the values it accepts.

## The theme

The theme sets the document's default font, size, colour and line height, and holds your named styles. Out of the box, text is Inter at 10pt, weight 400, in `slate-900` with a line height of 1.4.

```php
use Papercut\{Document, Page, Text};
use Papercut\Export\PdfOptions;
use Papercut\Style\Theme;
use Papercut\Value\Color;

$theme = Theme::default()->with(fontSize: 11.0, lineHeight: 1.5, color: Color::parse('slate-800'));

$document = Document::make(Page::a4()->body(Text::p('Set in 11pt.')));
$options = new PdfOptions(theme: $theme);
// $document->toPdf($options)->save('out.pdf');
```

In Laravel, you set the same defaults in `config/papercut.php`:

```php
'theme' => ['font_family' => 'Inter', 'font_size' => 11, 'line_height' => 1.5, 'color' => 'slate-800'],
```

## Named styles

A named style is a set of properties you define once and reuse by name, a bit like a CSS class.

::: code-tabs#style
@tab Blade
```php
// config/papercut.php
'styles' => [
    'panel' => ['padding' => '12pt', 'background' => 'slate-50', 'radius' => '8pt'],
],
```

```blade
<x-doc::column style="panel">…</x-doc::column>
```
@tab PHP
```php
use Papercut\{Column, Document, Page, Text};
use Papercut\Export\PdfOptions;
use Papercut\Style\{Style, Theme};
use Papercut\Value\{Color, Edges};
use function Papercut\pt;

$theme = Theme::default()->withStyle('panel', Style::make(
    padding: Edges::all(pt(12)),
    background: Color::parse('slate-50'),
    radius: pt(8),
));

$document = Document::make(Page::a4()->body(
    Column::make(Text::make('Inside a panel'))->apply('panel'),
));
$options = new PdfOptions(theme: $theme);
```
:::

Anything you set directly on the component, as an attribute or a method, overrides the same property from the named style.

You can apply several names at once, like CSS classes: `style="panel panel.wide"` applies `panel` and then `panel.wide`, and later names win.

If you use a name that doesn't exist, Papercut throws an error and suggests the closest match.

::: tip Reserved names
The decorators use these style names: `card`, `callout`, `blockquote`, `section`, `field`, `choice`, `key-value`, `badge`, `pre`, `code`, `code-block`, `markup.table`, `markup.th`, `markup.td`, `markup.hr`, and anything starting with them. If you define a style with one of those names, it replaces the built-in one. That's how you [restyle a decorator](./decorators.md#restyling), but it also means you can restyle one by accident.
:::
