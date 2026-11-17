# Passwords, signatures and merging

## Password protection

A PDF can be encrypted so it opens only with a password. It uses AES-256, the strongest encryption PDF readers support.

::: code-tabs#style
@tab Laravel
```php
return Quoin::view('pdf.statement', $data)
    ->encrypt('open-sesame')
    ->pdf()
    ->download('statement.pdf');
```
@tab PHP
```php
use Quoin\{Document, Page, Text};
use Quoin\Export\{Encryption, PdfOptions};

$document = Document::make(Page::a4()->body(Text::p('Account balance: 42.00')));

$document->toPdf(new PdfOptions(encryption: Encryption::password('open-sesame')))->save('statement.pdf');
```
:::

With only a password, readers can still print the document and copy text from it, but not edit it.

### Permissions

To change what readers can do, build an `Encryption` yourself:

```php
use Quoin\Export\Encryption;

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

Leaving out the user password makes a document anyone can open but nobody can, say, copy from. If you don't set an owner password, a random one is generated, so the restrictions can't be lifted at all.

Permissions are honored by well-behaved PDF readers, not enforced by the encryption itself, so treat them as a deterrent. The password needed to open the document is real protection. Whatever the permissions, screen readers can always read the text.

Only PDFs are encrypted. Calling `encrypt()` doesn't affect `->html()` or `->docx()`.

## Digital signatures

A signature proves who issued a document and that nobody has changed it since. Signing isn't built in. [php-pdf](https://github.com/dskripchenko/php-pdf), the library that writes the PDFs, handles certificates, timestamp servers and long-term validation. Sign the PDF once it's rendered:

```php
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;

$pdf = Quoin::view('pdf.contract', $data)->pdf()->toString();

$signed = PdfSigner::fromBytes($pdf)->sign(new SignatureConfig(
    certPem: file_get_contents(storage_path('certs/signing.pem')),
    privateKeyPem: file_get_contents(storage_path('certs/signing.key')),
    reason: 'Approved',
));
```

In a Laravel app, [laravel-php-pdf](https://github.com/dskripchenko/laravel-php-pdf) wraps this with config-driven keys, timestamp servers, hardware security modules and cloud key stores. See php-pdf's [signing guide](https://github.com/dskripchenko/php-pdf/blob/main/docs/en/SIGNING.md) for everything it supports.

## Merging and stamping

php-pdf can also combine a generated PDF with existing ones: append your standard terms to an invoice, or print a letterhead behind every page.

```php
use Dskripchenko\PhpPdf\Pdf\Merge\{PdfMerger, PdfSource, Placement};

$invoice = Quoin::view('pdf.invoice', $data)->pdf()->toString();

$pdf = PdfMerger::create()
    ->append(PdfSource::fromBytes($invoice))
    ->append(PdfSource::fromFile(resource_path('pdf/terms.pdf')))
    ->stamp(PdfSource::fromFile(resource_path('pdf/letterhead.pdf')), placement: Placement::fit())
    ->toBytes();
```

Links and bookmarks from each document are carried into the merged file.
