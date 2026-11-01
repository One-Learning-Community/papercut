# Mail attachments and macros

Status: design, for 0.6.0. Date: 2026-10-31.

Two small Laravel additions for 0.6.0. (A text watermark was designed alongside them and then parked; see
`2026-10-31-watermark-design-parked.md`.)

## 1. Mail attachments (`Attachable`)

- `PendingOutput` implements `Illuminate\Contracts\Mail\Attachable`, so
  `->attach(Papercut::view('invoice', $data)->pdf())` works in a Mailable or notification.
- The attachment's name is the same default name `download()` uses (`document.pdf`, `document.docx`,
  `document.html`), and its MIME type comes from the output.
- `->attachment('invoice.pdf')` returns an `Illuminate\Mail\Attachment` with that name, for when the default name
  won't do (the same filename cleaning as `download()`).
- `PendingHtml` is attachable too (*my call*): it attaches the full HTML page as `document.html`, so
  `->attach(...->html())` works like the other formats.
- The document is rendered when the mail is built, not before, and only once (the existing render cache). Naming
  the attachment must not render it: `PendingOutput` learns its format (extension and MIME type) when it's made.
- Tested with Laravel's mail fake: the attached bytes, name and MIME type.

## 2. Macros (`Macroable`)

- `Illuminate\Support\Traits\Macroable` on `PapercutManager`, `PendingDocument`, `PendingOutput`, and also
  `PendingHtml`, since `html()` returns it (*my call*: the same reason applies to all four).
- Lets apps add their own shortcuts, e.g. `PendingOutput::macro('toS3', fn (string $path) => $this->store($path, 's3'))`.
- Tested: a macro on each class can be called and sees `$this`.
- Docs: a short note in the Laravel guide.

## Left to the builder

- Internal names and how the format reaches `PendingOutput`.
