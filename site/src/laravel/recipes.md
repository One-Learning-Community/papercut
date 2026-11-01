# Recipes

Short answers to things most apps need once they make documents: keeping them on S3, making them in a queue, emailing them, downloading them from Livewire or Filament, and drawing charts.

## Keep documents on S3 or any disk

`store()` writes to any disk in `config/filesystems.php`. A third argument goes to the disk, so `'public'` makes the file publicly visible:

```php
use Illuminate\Support\Facades\Storage;
use Papercut\Laravel\Facades\Papercut;

$path = "invoices/{$invoice->number}.pdf";

Papercut::view('pdf.invoice', ['invoice' => $invoice])->pdf()->store($path, 's3');
```

If the disk says the write failed, `store()` throws a `RuntimeException`.

To hand the file out later without making it public, send a link that expires, or let Laravel stream it from the disk:

```php
$url = Storage::disk('s3')->temporaryUrl($path, now()->addHour());

return Storage::disk('s3')->download($path, "invoice-{$invoice->number}.pdf");
```

Word and HTML work the same way: `->docx()->store(...)` and `->html()->page()->store(...)`.

## Make documents in a queued job

A long report doesn't have to hold up a request. Render it in a job, store it, and tell the user when it's ready:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Papercut\Laravel\Facades\Papercut;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report)
    {
    }

    public function handle(): void
    {
        $path = "reports/{$this->report->id}.pdf";

        Papercut::view('pdf.report', ['report' => $this->report])->pdf()->store($path, 's3');

        $this->report->update(['pdf_path' => $path]);
        $this->report->user->notify(new ReportReady($this->report));
    }
}
```

- Give the job the model (or its id), and build the document in `handle()`. A `PendingDocument` or `PendingOutput` holds a closure, so it can't be put on the queue.
- Workers on the same server share the parsed-font cache in `storage/framework/cache/papercut` (see `cache_dir` in the [configuration](./configuration.md)), so a font is parsed once, not once per job.
- A very long document can take longer than the worker's default timeout. Set `public $timeout` on the job if it does.

## Email a document

Attach a `PendingOutput` in a mailable's `attachments()` or a notification's `toMail()`. The document renders when it is attached, so a queued mailable renders it on the worker:

```php
public function attachments(): array
{
    return [Papercut::view('pdf.invoice', ['invoice' => $this->invoice])->pdf()->attachment('invoice.pdf')];
}
```

[Attaching to mail](./README.md#attaching-to-mail) covers names, MIME types and notifications.

## Download from Livewire or Filament

A Livewire action only sends a file when it returns a streamed or file response. `download()` returns an ordinary response, which Livewire ignores without an error, so use `streamDownload()`:

```php
use Papercut\Laravel\Facades\Papercut;

public function downloadInvoice()
{
    return Papercut::view('pdf.invoice', ['invoice' => $this->invoice])
        ->pdf()
        ->streamDownload("invoice-{$this->invoice->number}.pdf");
}
```

```blade
<button wire:click="downloadInvoice">Download PDF</button>
```

Filament actions run through Livewire, so the same call works from an action:

```php
Action::make('pdf')
    ->label('Download PDF')
    ->action(fn (Invoice $record) => Papercut::view('pdf.invoice', ['invoice' => $record])
        ->pdf()
        ->streamDownload("invoice-{$record->number}.pdf")),
```

`streamDownload()` names the file the way `download()` does, and works from routes and controllers too. The document renders when the response is sent.

## Draw charts as SVG

JavaScript chart libraries need a browser, so they can't draw into a PDF made on the server. Papercut draws SVG as vector shapes instead, so a chart stays sharp when printed or zoomed. Any PHP library that writes SVG markup works: pass its output to `<x-doc::svg :data="$svg" />`.

For simple charts, a Blade component can write the SVG itself. This one draws a bar chart from an array of labels and values:

```blade
{{-- resources/views/components/bar-chart.blade.php --}}
@props(['data', 'color' => '#4f46e5'])
@php
    $width = 400;
    $height = 150;
    $max = max($data) ?: 1;
    $slot = $width / count($data);
@endphp
<x-doc::svg width="100%">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ $width }} {{ $height + 18 }}">
    @foreach ($data as $label => $value)
      @php $bar = $value / $max * $height; @endphp
      <rect x="{{ $loop->index * $slot + 6 }}" y="{{ $height - $bar }}" width="{{ $slot - 12 }}" height="{{ $bar }}" rx="3" fill="{{ $color }}" />
      <text x="{{ ($loop->index + 0.5) * $slot }}" y="{{ $height + 14 }}" font-size="11" text-anchor="middle">{{ $label }}</text>
    @endforeach
  </svg>
</x-doc::svg>
```

```blade
<x-bar-chart :data="['Jan' => 42, 'Feb' => 51, 'Mar' => 38, 'Apr' => 64]" />
```

`{{ }}` escapes the labels, so a label with `&` or `<` in it can't break the markup. Text in the SVG is set in your document's fonts. [SVG](/guide/images-and-links.md#svg) lists what's drawn. Word export turns an SVG into a picture, which needs `ext-imagick` (see [Word export](/guide/word-export.md)).
