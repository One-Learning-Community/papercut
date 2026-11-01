# Styles and themes

## Style methods

Every component has the same set of style methods in PHP, and the same attributes in Blade. They fall into three groups:

- **Box:** `width`, `height`, `minWidth`, `maxWidth`, `minHeight`, `maxHeight`, `padding`, `margin`, `border`, `borderWidth`, `borderColor`, `radius`, `background`, `opacity`, `grow`, `shrink`, `basis`, `alignSelf`.
- **Text:** `font`, `size`, `weight`, `bold`, `italic`, `color`, `lineHeight`, `letterSpacing`, `left`, `center`, `right`, `justify`, `underline`, `strike`.
- **Pagination:** `keepTogether`, `keepWithNext`, `breakBefore`, `breakAfter`, `minLines`.

Text properties are inherited by everything inside a box, as in CSS. Box properties only apply to the box you set them on.

A PHP setter that takes an option also accepts the option's string value, the same one a Blade attribute uses. The two lines below do the same thing, and the string is matched without regard to case or surrounding spaces:

```php
$row->align(Align::Center);
$row->align('center');
```

This covers `direction`, `justify` and `align` on views, rows and columns, `alignSelf`, `textAlign` and the style argument of `border`, plus `align` on a table cell, `fit` on an image, `marker` on a list, `anchor` on a `Place` and the `level` argument of `QrCode::make()`, with `errorCorrection`, `shape`, `eyes` and the type of `gradient` on a QR code. Where the value is a letter, as for a QR level, the case name works too: `'H'` and `'high'` are the same. A string that isn't one of the values throws an `InvalidValue` error that suggests the closest match, such as `Did you mean "center"?`. The enum still works, and gives your editor the list of values.

The [style attributes reference](/reference/style-attributes.md) lists every Blade attribute and the values it accepts.

## The theme

The theme sets the document's default font, size, colour and line height, and holds your named styles. Out of the box, text is Inter at 10pt, weight 400, in `slate-900` with a line height of 1.4.

```php
use Papercut\{Document, Page, Text};
use Papercut\Export\PdfOptions;
use Papercut\Style\Theme;
use Papercut\Value\Color;

$theme = Theme::default()->with(fontSize: 11.0, lineHeight: 1.5, color: Color::parse('slate-800'));

$document = Document::make(Page::letter()->body(Text::p('Set in 11pt.')));
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

$document = Document::make(Page::letter()->body(
    Column::make(Text::make('Inside a panel'))->apply('panel'),
));
$options = new PdfOptions(theme: $theme);
```
:::

Anything you set directly on the component, as an attribute or a method, overrides the same property from the named style.

You can apply several names at once, like CSS classes: `style="panel panel.wide"` applies `panel` and then `panel.wide`, and later names win.

If you use a name that doesn't exist, Papercut throws an error and suggests the closest match.

::: tip Reserved names
The decorators use these style names: `card`, `callout`, `blockquote`, `section`, `field`, `choice`, `key-value`, `badge`, `pre`, `code`, `code-block`, `toc`, `markup.table`, `markup.th`, `markup.td`, `markup.hr`, and anything starting with them. If you define a style with one of those names, it replaces the built-in one. That's how you [restyle a decorator](./decorators.md#restyling), but it also means you can restyle one by accident.
:::

## Changing the theme for one document or page

The theme above is shared by every document. To change it for one document, or for one page of a document, give that document or page an override. It names only the values it changes. The rest comes from the level above.

The levels, from the bottom: the base theme (`PdfOptions(theme:)` or the config), the document, then the page. A later level wins for each value. Named styles merge: overriding `callout.warning` with only a background keeps its border and padding.

An override can set the font family, font size, colour, line height, mono family, the [code theme](./text.md#themes-and-colours) and named styles. In PHP it can also set font weight, cell padding and minimum lines, as `Theme::with()` does.

`Merriweather` is not bundled: a font family in an override must be bundled or registered (see [Fonts](./fonts.md)). Swap in `Inter` or a family you have registered to try this. The report sets the body at 11pt, puts its cover page in another font, and shrinks the appendix:

::: code-tabs#style
@tab Blade
```blade
<x-doc::document :theme="['font_size' => 11, 'color' => 'slate-800']">
    <x-doc::page :theme="['font_family' => 'Merriweather', 'styles' => ['h1' => ['font-size' => '36pt']]]">
        <x-doc::h1>Annual report</x-doc::h1>
    </x-doc::page>

    <x-doc::page>…</x-doc::page>

    <x-doc::page :theme="['font_size' => 8]">
        <x-doc::h1>Appendix</x-doc::h1>
        …
    </x-doc::page>
</x-doc::document>
```
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Style\{Style, ThemeOverride};
use Papercut\Value\Color;
use function Papercut\pt;

$cover = Page::a4()
    ->theme(new ThemeOverride(
        fontFamily: 'Merriweather',
        styles: ['h1' => Style::make(fontSize: pt(36))],
    ))
    ->body(Text::h1('Annual report'));

$appendix = Page::a4()
    ->theme(new ThemeOverride(fontSize: pt(8)))
    ->body(Text::h1('Appendix'));

$document = Document::make($cover, Page::a4()->body(Text::p('…')), $appendix)
    ->theme(new ThemeOverride(fontSize: pt(11), color: Color::parse('slate-800')));
```
:::

Calling `theme()` again on the same document or page merges with the earlier override, and the later call wins.

A page's override applies to its body, headers, footers, background and overlay. In the PDF each page is laid out with its own theme. In HTML the page's section carries the differences. In Word the document's values become Word's defaults, and a page's values are written on that page's paragraphs.

In Laravel, call `->theme()` on the pending document to change the document from a controller, such as a brand colour per customer:

```php
Papercut::view('pdf.invoice', $data)
    ->theme(['color' => $customer->brand_color])
    ->pdf();
```

The keys are `font_family`, `mono_family`, `font_size` (points, a number), `color`, `line_height`, `code_theme` and `styles` (in the shape of `papercut.styles`); the first five work as in `papercut.theme`. The order is: config, the view's `<x-doc::document :theme>`, the controller's `->theme()`, then the page's `:theme`. The controller has the last word for the document, and a page override still applies after it.

Unlike `papercut.styles`, which replaces a built-in style of the same name, a named style in an override merges onto the existing one. A bad value, such as an unknown colour, throws an error. In Laravel, so does a font family that isn't bundled or registered, and the error names the tag or call it came from. In PHP the PDF export fails with `FontNotFound` without naming the override, and HTML and Word accept the name.

::: warning New style names
Blade checks style names while it renders the view, before the controller's `->theme()` runs. A controller's `->theme()` can change values and restyle names that already exist. A new style name that the view uses must be defined in `papercut.styles` or in a `:theme` in the view.
:::

## Heading styles

Headings take their look from the theme's `h1` to `h4` styles. Without those styles the built-in look applies, as before: 24, 18, 14 and 12pt, bold.

A heading's style is layered:

1. The built-in heading look.
2. The theme's style for its level, `h1` to `h4`, if you defined one.
3. The heading's own style name, such as `style="chapter"`.
4. The heading's own properties.

`h1` only applies to level 1 headings, and so on. In markup, `<h5>` and `<h6>` become level 4 headings and use `h4`. Using `style="h1"` on other nodes still works as with any named style. A table of contents still finds headings by level.

::: code-tabs#style
@tab Laravel
```php
// config/papercut.php
'styles' => [
    'h1' => ['color' => 'indigo-700', 'font-size' => '28pt'],
],
```
@tab PHP
```php
use Papercut\Style\{Style, Theme};
use Papercut\Value\Color;
use function Papercut\pt;

$theme = Theme::default()->withStyle('h1', Style::make(
    color: Color::parse('indigo-700'),
    fontSize: pt(28),
));
```
:::

Because of this, an existing style called `h1` to `h4` now styles the headings of that level.
