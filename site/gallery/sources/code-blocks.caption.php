<?php

declare(strict_types=1);

use Quoin\{Document, Page};
use Quoin\Decorator\CodeBlock;

use function Quoin\in;

$code = <<<'PHP'
$invoice = Invoice::find($id);

return Quoin::view('invoice', ['invoice' => $invoice])
    ->pdf()
    ->download("invoice-{$invoice->number}.pdf");
PHP;

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    CodeBlock::make($code)->language('php')->label('InvoiceController.php')->variant('caption'),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
