# Passwords, signatures and merging

## Password protection

Papercut can encrypt a PDF so it only opens with a password. It uses AES-256, the strongest encryption PDF readers support.

::: code-tabs#style
@tab Laravel
```php
return Papercut::view('pdf.statement', $data)
    ->encrypt('open-sesame')
    ->pdf()
    ->download('statement.pdf');
```
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Export\{Encryption, PdfOptions};

$document = Document::make(Page::a4()->body(Text::p('Account balance: 42.00')));

$document->toPdf(new PdfOptions(encryption: Encryption::password('open-sesame')))->save('statement.pdf');
```
:::

With just a password, readers can still print the document and copy text from it, but not edit it.

### Permissions

To change what readers can do, build an `Encryption` yourself:

```php
use Papercut\Export\Encryption;

new Encryption(
    userPassword: 'open-sesame',   // needed to open the document; '' lets anyone open it
    ownerPassword: 'admin-only',   // lifts the restrictions; leave it out to generate a random one
    print: true,
    copy: false,
    modify: false,
    annotate: false,
    assemble: false,               // insert, delete or rotate pages
);
```

In Laravel, pass it to `encrypt()` in place of the password string.

Leaving out the user password makes a document anyone can open but nobody can, say, copy from. If you don't set an owner password, Papercut generates a random one, so the restrictions can't be lifted at all.

Permissions are honoured by well-behaved PDF readers, not enforced by the encryption itself, so treat them as a deterrent. The password needed to open the document is real protection. Whatever the permissions, screen readers can always read the text.

Only PDFs are encrypted. Calling `encrypt()` doesn't affect `->html()`.

## Digital signatures

A signature proves who issued a document and that nobody has changed it since. Papercut doesn't sign documents itself. Signing comes with certificates, timestamp servers and long-term validation, and [php-pdf](https://github.com/dskripchenko/php-pdf), the library Papercut uses to write PDFs, already handles all of that. A Papercut PDF can be signed as soon as it's rendered:

```php
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;

$pdf = Papercut::view('pdf.contract', $data)->pdf()->toString();

$signed = PdfSigner::fromBytes($pdf)->sign(new SignatureConfig(
    certPem: file_get_contents(storage_path('certs/signing.pem')),
    privateKeyPem: file_get_contents(storage_path('certs/signing.key')),
    reason: 'Approved',
));
```

In a Laravel app, [laravel-php-pdf](https://github.com/dskripchenko/laravel-php-pdf) wraps this with config-driven keys, timestamp servers, hardware security modules and cloud key stores. See php-pdf's [signing guide](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/SIGNING.md) for everything it supports.

## Merging and stamping

php-pdf can also combine a Papercut PDF with existing ones: add your standard terms to the end of an invoice, or print a letterhead behind every page.

```php
use Dskripchenko\PhpPdf\Pdf\Merge\{PdfMerger, PdfSource, Placement};

$invoice = Papercut::view('pdf.invoice', $data)->pdf()->toString();

$pdf = PdfMerger::create()
    ->append(PdfSource::fromBytes($invoice))
    ->append(PdfSource::fromFile(resource_path('pdf/terms.pdf')))
    ->stamp(PdfSource::fromFile(resource_path('pdf/letterhead.pdf')), placement: Placement::fit())
    ->toBytes();
```

Links and bookmarks from each document are carried into the merged file.
