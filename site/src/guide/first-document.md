# Your first document

Every document has the same shape: a document, one or more pages, and content on each page. Here is one PDF built both ways:

::: code-tabs#style
@tab Blade
```blade
{{-- resources/views/pdf/hello.blade.php --}}
<x-doc::document title="Hello">
  <x-doc::page size="letter" margin="0.75in">
    <x-doc::column gap="8pt">
      <x-doc::h1>Hello, {{ $name }}</x-doc::h1>
      <x-doc::p>A column of text. Inter is bundled, so this embeds a real font.</x-doc::p>
    </x-doc::column>
  </x-doc::page>
</x-doc::document>
```

```php
use Quoin\Laravel\Facades\Quoin;

Route::get('/hello', fn () => Quoin::view('pdf.hello', ['name' => 'Ada'])->pdf()->download('hello.pdf'));
```
@tab PHP
```php
use Quoin\{Column, Document, Page, Text};
use function Quoin\{in, pt};

$document = Document::make(
    Page::letter()->margin(in(0.75))->body(
        Column::make(
            Text::h1('Hello, Ada'),
            Text::p('A column of text. Inter is bundled, so this embeds a real font.'),
        )->gap(pt(8)),
    ),
)->title('Hello');

$document->toPdf()->save('hello.pdf');
```
:::

A Blade view needs exactly one `<x-doc::document>` at the root. Content put directly inside the document, without a `<x-doc::page>`, is wrapped in a page that uses the paper size and margin from your config.

## Getting the PDF out

In plain PHP, `toPdf()` returns an `Output` with these methods:

| Method | What it does |
|---|---|
| `save($path)` | Writes the PDF to a file. |
| `bytes()` | Returns the PDF as a string. |
| `stream()` | Writes the PDF to the output buffer and returns the number of bytes. |
| `mime()` | Returns the MIME type, such as `application/pdf`. |
| `extension()` | Returns the file extension, such as `pdf`. |

The same document can be written as Word or HTML. `export(new DocxExporter())` and `toHtml()` return the same kind of `Output`. See [Word export](./word-export.md) and [HTML export](./html-export.md).

In Laravel, `Quoin::view($view, $data)` returns a pending document, and `->pdf()` on it returns a `PendingOutput`. `->docx()` gives you one for a [Word](./word-export.md) file, and `->html()->page()` or `->html()->fragment()` one for [HTML](./html-export.md):

| Method | What it does |
|---|---|
| `download($filename)` | Returns a response that downloads the file. |
| `streamDownload($filename)` | The same download as a streamed response, which renders the document as it's sent. Use it in Livewire and Filament actions, which ignore ordinary responses. |
| `inline($filename)` | Returns a response that opens the PDF in the browser. |
| `save($path)` | Writes the PDF to a local file. |
| `store($path, $disk)` | Writes the PDF to a [storage disk](https://laravel.com/docs/filesystem). Leave out `$disk` to use the default one. |
| `toString()` | Returns the PDF as a string. |

The filename defaults to `document.pdf`. If you return a `PendingOutput` straight from a route, the PDF opens inline.

You can mix the two styles. To send a document you built in PHP as a Laravel response, wrap it with `Quoin::make($document)->pdf()`. The other way, `Quoin::view(...)->document()` returns the core `Document` that a Blade view built.

### Password protection

To make the PDF open only with a password, call `encrypt()` before `pdf()`: `Quoin::view(...)->encrypt('open-sesame')->pdf()`. [Passwords, signatures and merging](./passwords-and-signatures.md) covers permissions.

### Saving to storage

`store()` writes to any disk in `config/filesystems.php`, which suits queued jobs that generate a document and send a link to it later:

```php
use Illuminate\Support\Facades\Storage;
use Quoin\Laravel\Facades\Quoin;

Quoin::view('pdf.invoice', ['invoice' => $invoice])
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

Build a piece once and reuse it anywhere; one use never affects another.

## Next

[Layout](./layout.md) covers rows, columns and units.
