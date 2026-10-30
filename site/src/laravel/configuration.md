# Configuration

To customise Papercut, publish the config file to `config/papercut.php`:

```bash
php artisan vendor:publish --tag=papercut-config
```

## Documents

| Key | Default | Description |
|---|---|---|
| `prefix` | `'doc'` | The Blade tag prefix. Lowercase letters, digits and dashes only. Read when the app boots. |
| `paper` | `'a4'` | The page size when `<x-doc::page>` doesn't set one. A paper name, or a width and height like `'8.5in 11in'`. |
| `margin` | `'20mm'` | The page margin when `<x-doc::page>` doesn't set one. |
| `theme` | `[]` | Document defaults: `font_family`, `font_size` (in points), `color`, `line_height` and `mono_family` (the font the `monospace` keyword and code use; default `Cousine`). |
| `styles` | `[]` | [Named styles](/guide/styles.md#named-styles), using the same names as tag attributes. |
| `fonts` | `[]` | Extra font families. See [Fonts](/guide/fonts.md). |
| `fallback_fonts` | `[]` | Families to try, in order, for characters the main font doesn't have. |
| `font_word_names` | `[]` | The name Word uses for a family, as `'Family' => 'Word name'`. The family must be bundled or in `fonts`, and the match ignores case. Cousine is `Courier New` unless set here. See [Word font names](/guide/fonts.md#word-font-names). |
| `bookmarks` | `true` | Builds the PDF's [bookmarks panel](/guide/pages.md#bookmarks) from headings. `false` leaves it empty. |
| `image_path` | `null` | The base directory for relative `<x-doc::image src>` paths. `null` means `public_path()`. |
| `cache_dir` | `null` | Where to cache parsed fonts. `null` uses `storage/framework/cache/papercut`, and `false` turns the disk cache off. |

## Errors

| Key | Default | Description |
|---|---|---|
| `overflow` | `null` | `'throw'` or `'clip'`. `null` throws in `local` and `testing` and clips everywhere else. See [Content that doesn't fit](/guide/pages.md#content-that-doesn-t-fit). |
| `strict` | `false` | When `true`, an export throws instead of dropping content. Worth turning on in tests. See [Strict mode](/guide/rich-text.md#strict-mode). |

## HTML

| Key | Default | Description |
|---|---|---|
| `html.fonts` | `'none'` | `'none'`, `'embed'`, `'link'` or `'sources'`. See [Fonts in HTML](/guide/web-fonts.md). |
| `html.font_url` | `null` | A `FontUrlResolver` class. Required when `fonts` is `'link'`. |
| `html.image_url` | `null` | An `ImageUrlResolver` class. `null` embeds images as data URIs. |
| `html.font_sources` | `[GoogleFonts::class]` | The font sources to check, in order, when `fonts` is `'sources'`. |
| `html.google_fonts.host` | `'https://fonts.googleapis.com'` | Use `'https://fonts.bunny.net'` for Bunny Fonts. |
| `html.google_fonts.display` | `'swap'` | `font-display`: `auto`, `block`, `swap`, `fallback` or `optional`. |
| `html.google_fonts.families` | `null` | `null` for every family, or a list of the families to load. |
| `html.local_fonts.mode` | `'embed'` | `'embed'` or `'link'`. |
| `html.local_fonts.families` | `null` | `null` for every registered family, or a list. |

Resolvers are set as class names rather than closures, because closures can't survive `php artisan config:cache`. If you use a closure, you'll get an error naming the key. Each class is built by the container, so you can inject dependencies through the constructor.

```php
namespace App\Pdf;

use Papercut\Image;
use Papercut\Laravel\Html\ImageUrlResolver;

final class CdnImageUrl implements ImageUrlResolver
{
    public function __invoke(Image $image): ?string
    {
        ['kind' => $kind, 'value' => $value] = $image->source();

        return $kind === 'file' ? asset('img/' . basename($value)) : null; // null embeds
    }
}
```

## Markup

| Key | Default | Description |
|---|---|---|
| `markup.images` | `null` | An `ImageResolver` class. `null` treats every `<img>` as missing. |
| `markup.styles` | `'theme'` | `'theme'` or `'original'`. See [Rich text](/guide/rich-text.md#style-modes). |
| `markup.blank_paragraphs` | `'drop'` | `'drop'` or `'keep'`. |
| `markup.highlight` | `true` | `false` keeps `<pre><code class="language-x">` as plain `pre` blocks instead of [code blocks](/guide/text.md#code-blocks). |

### Markup images

Here's a resolver for a rich text editor that stores uploads on the default disk and serves resized copies from `/public/images/{size}/files/uploaded/editor/…`:

```php
namespace App\Markup;

use Illuminate\Support\Facades\Storage;
use Papercut\Laravel\Markup\ImageResolver;

final class EditorImages implements ImageResolver
{
    public function __invoke(string $src): ?string
    {
        if (preg_match('#^/public/images/[^/]+/(files/uploaded/editor/.+)$#', $src, $m) !== 1) {
            return null;
        }
        // Reject NUL and any `..` segment: the disk would normalise `editor/../../private/x` to `private/x`.
        if (str_contains($m[1], "\0") || in_array('..', preg_split('#[/\\\\]#', $m[1]) ?: [], true)) {
            return null;
        }
        $disk = Storage::disk();

        return $disk->exists($m[1]) ? $disk->get($m[1]) : null;
    }
}
```

```php
'markup' => ['images' => \App\Markup\EditorImages::class],
```

A single tag can use a different resolver with the `images` attribute: `<x-doc::markup :html="$body" images="App\Markup\LegacyImages" />`. The class must implement `Papercut\Laravel\Markup\ImageResolver`, like the config value. `images="none"` (lower case only) turns images off for that tag, so each image shows its alt text, as for a missing image.

Within one markup conversion the resolver is called once for each distinct `src` (trimmed of surrounding whitespace); later images with the same `src` reuse the answer, including a null.

Papercut builds a resolver once per request, queue job or Octane request, and every markup tag in it shares that instance, whether the class comes from the config or from a tag's `images` attribute. A cache in a property lasts that long; for anything longer use Laravel's cache.

## Code

Syntax highlighting for [code blocks](/guide/text.md#code-blocks) needs `phiki/phiki`.

| Key | Default | Description |
|---|---|---|
| `code.theme` | `null` | The default theme. `null` is `papercut-light`. A name from Phiki's bundled themes or `code.themes`. |
| `code.variant` | `null` | `null`, `'strip'` or `'caption'`: the look of blocks that don't set a `variant`. |
| `code.grammars` | `[]` | `'name' => path` to a TextMate grammar JSON file. |
| `code.themes` | `[]` | `'name' => path` to a TextMate theme JSON file. |
| `code.aliases` | `[]` | `'alias' => 'language'`. |
| `code.injections` | `[]` | A list of `['path' => …, 'into' => ['source.x', …]]`: a VS Code style injection grammar, merged into the grammars with those scope names. Grammars are registered first, so `into` can name one from `code.grammars`. |

A path that can't be read, or a grammar or theme that isn't valid, throws an error that names the key, such as `papercut.code.grammars.demo`.

## Preview

| Key | Default | Description |
|---|---|---|
| `preview.enabled` | `env('PAPERCUT_PREVIEW', false)` | Turns on the [preview routes](./preview.md). |
| `preview.path` | `'_papercut'` | The URL prefix for the preview routes. |

Mistakes in the `html.*`, `markup.*` and `code.*` settings are reported the first time they're used, not when the app boots.
