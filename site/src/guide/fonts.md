# Fonts

Papercut always embeds its fonts in the PDF, keeping only the characters the document actually uses. It never relies on the standard PDF fonts built into viewers, so your document looks the same everywhere.

Inter comes bundled in regular, bold, italic and bold italic, and it's the default font.

## Adding a font family

::: code-tabs#style
@tab Laravel config
```php
// config/papercut.php
'fonts' => [
    'Merriweather' => [
        400 => ['normal' => 'resources/fonts/Merriweather-Regular.ttf', 'italic' => 'resources/fonts/Merriweather-Italic.ttf'],
        700 => ['normal' => 'resources/fonts/Merriweather-Bold.ttf'],
    ],
],
'fallback_fonts' => ['Noto Sans'],
'theme' => ['font_family' => 'Merriweather'],
```
@tab PHP
```php
use Papercut\{Column, Document, Page, Text};
use Papercut\Export\PdfOptions;
use Papercut\Text\{FontFace, FontRegistry, FontSet};
use function Papercut\{mm, pt};

// The bundled Inter files, registered again under another name.
$inter = dirname((new ReflectionClass(FontRegistry::class))->getFileName(), 3) . '/resources/fonts/Inter';
$fonts = (new FontSet())
    ->with(new FontFace('Brand', $inter . '/Inter-Regular.ttf', 400), new FontFace('Brand', $inter . '/Inter-Bold.ttf', 700))
    ->withFallbacks('Inter');

$document = Document::make(Page::a4()->margin(mm(20))->body(
    Column::make(
        Text::make('Set in Brand')->font('Brand')->size(pt(18)),
        Text::make('Brand bold')->font('Brand')->bold(),
    )->gap(pt(8)),
));
$options = new PdfOptions(fonts: $fonts);
```
:::

In the Laravel config, relative paths are resolved from `base_path()`. To use a family, set it on an element with `font()` or `font="…"`, or for the whole document in the theme.

If you ask for a family, weight or style that isn't registered, Papercut throws `FontNotFound`.

## Fonts from Google Fonts

In Laravel, you can download a family from Google Fonts with one command:

```bash
php artisan papercut:fonts:install "Merriweather" --weights=400,700 --italic
```

This saves the font files to `resources/fonts/Merriweather/` and prints the entry to paste into `fonts` in your config:

```php
'Merriweather' => [
    400 => ['normal' => 'resources/fonts/Merriweather/Merriweather-Regular.ttf', 'italic' => 'resources/fonts/Merriweather/Merriweather-Italic.ttf'],
    700 => ['normal' => 'resources/fonts/Merriweather/Merriweather-Bold.ttf', 'italic' => 'resources/fonts/Merriweather/Merriweather-BoldItalic.ttf'],
],
```

The fonts are downloaded once and then live in your app like any other font, so commit them with your code. Rendering never goes to the network.

| Option | Default | What it does |
|---|---|---|
| `--weights` | `400,700` | The weights to download, separated by commas. |
| `--italic` | off | Also downloads the italic of each weight. |
| `--path` | `resources/fonts` | Where to save the files, relative to your app. |
| `--force` | off | Replaces files that are already there. |

Use the family name exactly as Google Fonts spells it, including capitals and spaces: `"Open Sans"`, not `open-sans`. Weights a family doesn't come in are skipped with a warning. Most Google fonts are under the SIL Open Font License, but check the family's page before you ship it.

Outside Laravel, download the family from [fonts.google.com](https://fonts.google.com) and register the `.ttf` files with a `FontSet` as shown above.

## Fallback fonts

When the main font doesn't have a character, Papercut tries each fallback family in order. If none of them have it, Papercut logs a warning. In Laravel, `php artisan papercut:fonts` shows every registered font file and whether it's `ok`, `missing` or `invalid`.

## Kerning and ligatures

Kerning and ligatures are applied within each run of text. Complex shaping (HarfBuzz) and right-to-left text aren't supported yet.

## The kerning cache

Reading a font's kerning table takes tens of milliseconds, so Papercut caches it on disk. Each font gets its own file of about 5 to 6 MB, stored in `sys_get_temp_dir()/papercut-cache-<uid>`. Updating a font or the library creates a fresh entry, and entries that haven't been used for 30 days are deleted. If the cache directory isn't writable, Papercut reads the font in memory instead. The PDF is identical either way, only slower to produce.

```php
use Papercut\Export\PdfOptions;

$options = new PdfOptions(cacheDir: '/var/cache/myapp/papercut'); // your own directory
$options = new PdfOptions(cacheDir: false);                       // no disk cache
```

In Laravel, set `cache_dir` in the config.
