# Fonts

Fonts are always embedded in the PDF, keeping only the characters the document uses. The standard PDF fonts built into viewers are never used, so the document looks the same everywhere.

Inter comes bundled in regular, bold, italic and bold italic, and it's the default font. Cousine, a monospace face, is bundled the same way.

## The monospace keyword

A font family of `monospace` (any case) stands for the theme's mono family, which is Cousine unless you change it. Set `mono_family` under `quoin.theme` in Laravel, or call `Theme::default()->with(monoFamily: 'Brand Mono')` in PHP, and every use of `monospace` follows. You can register a family named "monospace", but it is never selected: the keyword always means the theme's mono family. The mono family itself can't be empty or `monospace`.

## Adding a font family

::: code-tabs#style
@tab Laravel config
```php
// config/quoin.php
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
use Quoin\{Column, Document, Page, Text};
use Quoin\Export\PdfOptions;
use Quoin\Text\{FontFace, FontSet};
use function Quoin\{in, pt};

// The bundled Inter files, registered again under another name.
$inter = dirname((new ReflectionClass(FontSet::class))->getFileName(), 3) . '/resources/fonts/Inter';
$fonts = (new FontSet())
    ->with(new FontFace('Brand', $inter . '/Inter-Regular.ttf', 400), new FontFace('Brand', $inter . '/Inter-Bold.ttf', 700))
    ->withFallbacks('Inter');

$document = Document::make(Page::letter()->margin(in(0.75))->body(
    Column::make(
        Text::make('Set in Brand')->font('Brand')->size(pt(18)),
        Text::make('Brand bold')->font('Brand')->bold(),
    )->gap(pt(8)),
));
$options = new PdfOptions(fonts: $fonts);
```
:::

In the Laravel config, relative paths are resolved from `base_path()`. To use a family, set it on an element with `font()` or `font="…"`, or for the whole document in the theme.

If you ask for a family, weight or style that isn't registered, the export throws `FontNotFound`.

## Fonts from Google Fonts

In Laravel, one command downloads a family from Google Fonts:

```bash
php artisan quoin:fonts:install "Merriweather" --weights=400,700 --italic
```

This saves the font files to `resources/fonts/Merriweather/`, along with the family's license (`OFL.txt` for the SIL Open Font License, or `LICENSE.txt` or `UFL.txt`), and prints the entry to paste into `fonts` in your config:

```php
'Merriweather' => [
    400 => ['normal' => 'resources/fonts/Merriweather/Merriweather-Regular.ttf', 'italic' => 'resources/fonts/Merriweather/Merriweather-Italic.ttf'],
    700 => ['normal' => 'resources/fonts/Merriweather/Merriweather-Bold.ttf', 'italic' => 'resources/fonts/Merriweather/Merriweather-BoldItalic.ttf'],
],
```

The fonts are downloaded once and then live in your app, so commit them with your code, license included: the licenses ask for a copy to go wherever the fonts do. If the license can't be downloaded, the command says so and installs the fonts anyway; get it from the family's page on fonts.google.com. Rendering never goes to the network.

| Option | Default | What it does |
|---|---|---|
| `--weights` | `400,700` | The weights to download, separated by commas. |
| `--italic` | off | Also downloads the italic of each weight. |
| `--path` | `resources/fonts` | Where to save the files, relative to your app. |
| `--force` | off | Replaces files that are already there. |

Use the family name exactly as Google Fonts spells it, including capitals and spaces: `"Open Sans"`, not `open-sans`. Weights a family doesn't come in are skipped with a warning. Most Google fonts are under the SIL Open Font License, but check the family's page before you ship it.

Outside Laravel, download the family from [fonts.google.com](https://fonts.google.com) and register the `.ttf` files with a `FontSet` as shown above.

## Word font names

The Word export writes each family's own name by default. To give a family another name in Word, use `FontSet::withWordName()`:

```php
$fonts = (new FontSet())->withWordName('Cousine', 'Consolas');
echo $fonts->wordName('Cousine'); // Consolas
```

The family match is case-insensitive, and the last name set for a family wins. A family without a Word name uses its own name, except Cousine, which is `Courier New` unless you set another. That holds for any family named "Cousine", including one you register yourself with your own faces; call `withWordName` (`font_word_names` in Laravel) to give it a different Word name. The name only changes what Word is told: the PDF still embeds the real font, and HTML keeps the real family. Lines are still measured with the real font, and Word lays them out in the named one, so use a Word name for a metric-compatible pair (Cousine and Courier New, for instance), not for an unrelated font.

`$fonts->info('Inter')` reports what a family's font file says about its kind: a `FontClass` (`Sans`, `Serif`, `Mono`, `Script`, `Decorative` or `Other`), its PANOSE classification as 20 hex characters, and whether it is fixed pitch. It reads the family's regular face, or its first face when it has no regular one, and returns `null` for a family that isn't registered. HTML export uses the class to end each font stack with `monospace`, `serif` or `system-ui, sans-serif` (Script, Decorative and Other fonts get the sans fallback, as do unregistered families).

## Fallback fonts

When the main font doesn't have a character, each fallback family is tried in order. If none of them have it, the character prints as an empty box and a `dropped` note that lists the missing characters, at the text's place in the document. In [strict mode](./rich-text.md#strict-mode), the export throws instead. Only the PDF reports this: Word and browsers pick another font themselves. The replacement character U+FFFD (put in for invalid UTF-8) is drawn from the bundled math font when none of your fonts have it, so it never counts as missing. In Laravel, `php artisan quoin:fonts` shows every registered font file and whether it's `ok`, `missing` or `invalid`.

### Names in other scripts

Inter covers Latin with accents, Cyrillic, Greek and Vietnamese. It has no Chinese, Japanese, Korean, Arabic, Hebrew, Thai or Devanagari. If your documents print names or addresses your users typed, add fallback families for the scripts they use. The Noto fonts cover them, and only the characters you use are embedded:

::: code-tabs#style
@tab Laravel
```bash
php artisan quoin:fonts:install "Noto Sans SC" --weights=400,700
php artisan quoin:fonts:install "Noto Sans Arabic" --weights=400,700
```

```php
// config/quoin.php
'fallback_fonts' => ['Noto Sans SC', 'Noto Sans Arabic'],
```
@tab PHP
```php
use Quoin\Export\PdfOptions;
use Quoin\Text\{FontFace, FontSet};

$fonts = (new FontSet())
    ->with(new FontFace('Noto Sans SC', __DIR__ . '/fonts/NotoSansSC-Regular.ttf', 400))
    ->with(new FontFace('Noto Sans Arabic', __DIR__ . '/fonts/NotoSansArabic-Regular.ttf', 400))
    ->withFallbacks('Noto Sans SC', 'Noto Sans Arabic');

$options = new PdfOptions(fonts: $fonts);
```
:::

A short right-to-left run, such as an Arabic or Hebrew name in an English document, is shaped and ordered correctly. Whole right-to-left paragraphs aren't supported yet: they lay out left to right.

## The math font

[Equations](./equations.md) are set in a math font, STIX Two Math, which is bundled. To use another, call `withMathFont()` on the font set:

```php
$fonts = (new FontSet())->withMathFont('/fonts/MyMath-Regular.ttf');
```

In Laravel, set `math_font` in `config/quoin.php`. The font must be an OpenType math font, with TrueType or CFF outlines. It applies to PDF and HTML. Word equations always use Cambria Math.

In plain PHP, `withMathFont()` only stores the path. A path that doesn't exist throws `FontNotFound`, and a font that can't be used as a math font (no `MATH` table, CFF2, CID-keyed CFF, a `FontMatrix` that disagrees with the units per em, a font collection, or not a font) throws `UnsupportedFont`, both when a document is exported. In Laravel, either failure is reported as an error about the `quoin.math_font` config key.

A PDF embeds a CFF math font whole, where it subsets a TrueType one, so a large font makes a large file. One equation adds about 60 KB to a PDF in Fira Math (a 175 KB font) and about 390 KB in Latin Modern Math (716 KB). Where PDF size matters, keep the bundled font.

## Kerning and ligatures

Kerning and ligatures are applied within each run of text. Complex shaping (HarfBuzz) and right-to-left paragraphs aren't supported yet.

## The font cache

Each font that has been read is kept in a cache file, so the next request doesn't read the font again. This matters most under PHP-FPM, where every request starts from scratch. Each font gets its own file of a few hundred KB, stored in `sys_get_temp_dir()/quoin-cache-<uid>` by default. In Laravel, `'cache_dir' => null` (the default) uses `storage/framework/cache/quoin` instead. Updating a font or the library creates a fresh entry, and entries that haven't been used for 30 days are deleted. If the cache directory isn't writable, the font is read from scratch instead. The PDF is identical either way, only slightly slower to produce.

```php
use Quoin\Export\PdfOptions;

$options = new PdfOptions(cacheDir: '/var/cache/myapp/quoin'); // your own directory
$options = new PdfOptions(cacheDir: false);                       // no disk cache
```

In Laravel, set `cache_dir` in the config.

By default each request that needs a font reads its cache file. If you render PDFs all day and run opcache, store the cache as PHP files instead, and opcache holds the fonts in shared memory for every worker: the fastest way to restore a font. Pass the format to the backend. A backend you build yourself takes its own `cacheDir`, and `PdfOptions(cacheDir:)` is ignored:

```php
use Quoin\Backend\PhpPdf\{FontStateCache, PhpPdfBackend};
use Quoin\Export\PdfOptions;

$options = new PdfOptions(backend: new PhpPdfBackend(
    cacheDir: '/var/cache/myapp/quoin',
    fontCacheFormat: FontStateCache::PHP,
));
```

Without opcache, PHP files are compiled on every read, so leave the default. Laravel has no setting for it.
