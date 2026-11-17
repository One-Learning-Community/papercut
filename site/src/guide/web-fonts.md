# Fonts in HTML

A PDF carries its fonts inside the file. An HTML export relies on the browser to find them. You can leave that to the browser, embed the fonts in the page, link to font files you host, or load them from a service such as Google Fonts.

::: tip Google Fonts in PDFs
This page is about HTML exports. To use a Google font in your PDFs, download it with [`quoin:fonts:install`](./fonts.md#fonts-from-google-fonts).
:::

## Choosing a mode

| Mode | Laravel `html.fonts` | What the browser gets |
|---|---|---|
| `FontMode::None` | `'none'` | A `font-family` list only, so it uses whatever fonts it has. This is the default, and gives the smallest pages. The list ends in `monospace`, `serif` or `system-ui, sans-serif`, depending on the font's class; a family that isn't registered (say, one only a web font source serves) gets the sans fallback. |
| `FontMode::Embed` | `'embed'` | Your registered font files, embedded in the page as `data:` URIs. Self-contained, but large. |
| `FontMode::Link` | `'link'` | `@font-face` rules pointing at font files you host. |
| `FontMode::Sources` | `'sources'` | Each font family from the first source that has it, such as Google Fonts, Bunny Fonts or your own files. |

## Google Fonts

The browser can load fonts from Google Fonts. The families, weights and styles the document uses are worked out, and the stylesheet URL is built.

::: code-tabs#style
@tab Laravel config
```php
// config/quoin.php
'html' => [
    'fonts' => 'sources',
    'font_sources' => [\Quoin\Html\GoogleFonts::class],
],
```
@tab PHP
```php
use Quoin\{Document, Page, Text};
use Quoin\Export\{FontMode, HtmlOptions};
use Quoin\Html\GoogleFonts;

$document = Document::make(Page::a4()->body(Text::make('Hello')));

$html = $document->toHtml(new HtmlOptions(
    fullPage: true,
    fontMode: FontMode::Sources,
    fontSources: [new GoogleFonts()],
))->bytes();
```
:::

`GoogleFonts` sets `font-display: swap` by default, so text shows straight away in a fallback font and switches once the web font arrives. Change it with the `display` argument (`auto`, `block`, `swap`, `fallback` or `optional`), or `html.google_fonts.display` in Laravel.

The stylesheet is never fetched, so it can't tell whether Google has a family. For a family or weight Google doesn't offer, the browser falls back to the next font in the `font-family` list.

## Bunny Fonts

[Bunny Fonts](https://fonts.bunny.net) serves the same fonts with the same API, and says it doesn't log visitors' IP addresses. To use it, change the host:

::: code-tabs#style
@tab Laravel config
```php
'html' => [
    'fonts' => 'sources',
    'google_fonts' => ['host' => 'https://fonts.bunny.net'],
],
```
@tab PHP
```php
use Quoin\Export\{FontMode, HtmlOptions};
use Quoin\Html\GoogleFonts;

$options = new HtmlOptions(
    fullPage: true,
    fontMode: FontMode::Sources,
    fontSources: [new GoogleFonts(host: 'https://fonts.bunny.net')],
);
```
:::

## Your own fonts

`LocalFonts` serves the font files you've [registered](./fonts.md#adding-a-font-family). It embeds them in the page or links to copies you host:

```php
use Quoin\Html\LocalFonts;
use Quoin\Text\FontFace;

LocalFonts::embed();                                  // embed every registered family
LocalFonts::embed(['Acme']);                          // embed only Acme
LocalFonts::link(fn (FontFace $face) => '/fonts/' . basename($face->path));
```

In Laravel, add `LocalFonts` to `font_sources` and set `html.local_fonts.mode` to `'embed'` or `'link'`. Linking uses the `html.font_url` resolver described below.

## Mixing sources

For each family, the sources are asked in order and the first that has it wins. A common setup serves your brand font yourself and loads everything else from Google:

::: code-tabs#style
@tab Laravel config
```php
'html' => [
    'fonts' => 'sources',
    'font_sources' => [\Quoin\Html\LocalFonts::class, \Quoin\Html\GoogleFonts::class],
    'local_fonts' => ['mode' => 'embed', 'families' => ['Acme']],
],
```
@tab PHP
```php
use Quoin\Export\{FontMode, HtmlOptions};
use Quoin\Html\{GoogleFonts, LocalFonts};

$options = new HtmlOptions(
    fullPage: true,
    fontMode: FontMode::Sources,
    fontSources: [LocalFonts::embed(['Acme']), new GoogleFonts()],
);
```
:::

`GoogleFonts` accepts every family unless you limit it, so put it last; placed first, it would claim your own fonts too. To limit it, pass a list of families:

```php
new GoogleFonts(families: ['Inter', 'Merriweather']);
```

In Laravel, that's `html.google_fonts.families`.

If no source has a family, a warning is reported and the browser uses the `font-family` fallbacks. If a source returns an unsafe URL, it's skipped with a warning and the next source is asked.

## Linking to fonts you host

`FontMode::Link` links every registered font to a URL you provide. Return `null` to leave a font out.

::: code-tabs#style
@tab Laravel
```php
// app/Pdf/FontUrls.php
namespace App\Pdf;

use Quoin\Laravel\Html\FontUrlResolver;
use Quoin\Text\FontFace;

final class FontUrls implements FontUrlResolver
{
    public function __invoke(FontFace $face): ?string
    {
        return asset('fonts/' . basename($face->path));
    }
}
```

```php
// config/quoin.php
'html' => [
    'fonts' => 'link',
    'font_url' => \App\Pdf\FontUrls::class,
],
```
@tab PHP
```php
use Quoin\Export\{FontMode, HtmlOptions};
use Quoin\Text\FontFace;

$options = new HtmlOptions(
    fullPage: true,
    fontMode: FontMode::Link,
    fontUrl: fn (FontFace $face) => 'https://cdn.example.com/fonts/' . basename($face->path),
);
```
:::

## Writing your own source

Any other font service can be a source. A source receives a `FontRequest` with the family name and the weights and styles the document uses. It returns a stylesheet URL, a list of font files, or `null` to pass the family on to the next source.

```php
use Quoin\Export\{FontMode, HtmlOptions};
use Quoin\Html\{FontAnswer, FontRequest, GoogleFonts};

$adobe = fn (FontRequest $request): ?FontAnswer => $request->family === 'Acme Sans'
    ? FontAnswer::stylesheet('https://use.typekit.net/abc1234.css')
    : null;

$options = new HtmlOptions(fullPage: true, fontMode: FontMode::Sources, fontSources: [$adobe, new GoogleFonts()]);
```

To return font files instead of a stylesheet, use `FontAnswer::faces()` with a `FontFaceSrc` for each weight and style.

In Laravel, sources must be classes, because closures can't survive `php artisan config:cache`. Implement `Quoin\Html\FontSource` and add the class name to `font_sources`. The container builds each class, so you can inject dependencies.

## Privacy

With Google Fonts or Bunny Fonts, every visitor's browser connects to that host, which sees their IP address. A German court has ruled that doing this without consent can breach data protection law (LG München I, 3 O 17493/20). Bunny says it doesn't log IP addresses, but the request still goes to a third party.

If that's a concern, serve the fonts yourself: `LocalFonts`, `FontMode::Embed` and `FontMode::Link` keep every request on your own server.

## Content Security Policy

If your site sends a Content-Security-Policy header, it must allow the font hosts:

| Source | `style-src` | `font-src` |
|---|---|---|
| Google Fonts | `fonts.googleapis.com` | `fonts.gstatic.com` |
| Bunny Fonts | `fonts.bunny.net` | `fonts.bunny.net` |
| Embedded fonts | | `data:` |
