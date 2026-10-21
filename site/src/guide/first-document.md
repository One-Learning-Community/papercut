# Your first document

Every document has the same basic shape: a document, one or more pages, and some content on each page. Here's the same PDF built both ways:

::: code-tabs#style
@tab Blade
```blade
{{-- resources/views/pdf/hello.blade.php --}}
<x-doc::document title="Hello">
  <x-doc::page size="a4" margin="20mm">
    <x-doc::column gap="8pt">
      <x-doc::h1>Hello, {{ $name }}</x-doc::h1>
      <x-doc::p>A column of text. Inter is bundled, so this embeds a real font.</x-doc::p>
    </x-doc::column>
  </x-doc::page>
</x-doc::document>
```

```php
use Papercut\Laravel\Facades\Papercut;

Route::get('/hello', fn () => Papercut::view('pdf.hello', ['name' => 'Ada'])->pdf()->download('hello.pdf'));
```
@tab PHP
```php
use Papercut\{Column, Document, Page, Text};
use function Papercut\{mm, pt};

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Text::h1('Hello, Ada'),
            Text::p('A column of text. Inter is bundled, so this embeds a real font.'),
        )->gap(pt(8)),
    ),
)->title('Hello');

$document->toPdf()->save('hello.pdf');
```
:::

A Blade view needs exactly one `<x-doc::document>` at the root. If you put content directly inside the document without a `<x-doc::page>`, Papercut wraps it in a page for you, using the paper size and margin from your config.

## Getting the PDF out

In plain PHP, `toPdf()` returns an `Output` with three methods:

| Method | What it does |
|---|---|
| `save($path)` | Writes the PDF to a file. |
| `bytes()` | Returns the PDF as a string. |
| `stream()` | Writes the PDF to the output buffer and returns the number of bytes. |

In Laravel, `Papercut::view($view, $data)` returns a pending document, and calling `->pdf()` on it gives you a `PendingOutput`:

| Method | What it does |
|---|---|
| `download($filename)` | Returns a response that downloads the file. |
| `inline($filename)` | Returns a response that opens the PDF in the browser. |
| `save($path)` | Writes the PDF to a local file. |
| `store($path, $disk)` | Writes the PDF to a [storage disk](https://laravel.com/docs/filesystem). Leave out `$disk` to use the default one. |
| `toString()` | Returns the PDF as a string. |

The filename defaults to `document.pdf`. If you return a `PendingOutput` straight from a route, the PDF opens inline.

You can mix the two styles. To send a document you built in PHP as a Laravel response, wrap it with `Papercut::make($document)->pdf()`. To go the other way, `Papercut::view(...)->document()` gives you the core `Document` that a Blade view built.

### Password protection

To make the PDF open only with a password, call `encrypt()` before `pdf()`: `Papercut::view(...)->encrypt('open-sesame')->pdf()`. [Passwords, signatures and merging](./passwords-and-signatures.md) covers permissions.

### Saving to storage

`store()` writes to any disk in `config/filesystems.php`, which makes it a good fit for queued jobs that generate a document and send a link to it later:

```php
use Illuminate\Support\Facades\Storage;
use Papercut\Laravel\Facades\Papercut;

Papercut::view('pdf.invoice', ['invoice' => $invoice])
    ->pdf()
    ->store("invoices/{$invoice->number}.pdf", 's3');

$url = Storage::disk('s3')->temporaryUrl("invoices/{$invoice->number}.pdf", now()->addDay());
```

A third argument is passed on to the disk, so `store($path, 's3', 'public')` makes the file publicly visible. If the disk reports that the write failed, `store()` throws a `RuntimeException`.

## Components are immutable

Every method returns a new copy and leaves the original untouched:

```php
$label = Text::make('Total')->bold();
$big   = $label->size(pt(14));   // $label is still the default size
```

That means you can build a piece once and reuse it in as many places as you like, without one use affecting another.

## Next

[Layout](./layout.md) covers rows, columns and units.
