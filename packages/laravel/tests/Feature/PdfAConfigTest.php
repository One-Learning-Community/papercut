<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Export\PdfA;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\Support\ReadsStructure;
use Papercut\Laravel\Tests\TestCase;

/** PDF/A through the `papercut.pdfa` config key and ->pdfA() on a document. */
final class PdfAConfigTest extends TestCase
{
    use ReadsStructure;

    private function isPdfA(string $pdf): bool
    {
        return str_contains($pdf, 'pdfaid:part');
    }

    public function test_not_pdfa_by_default(): void
    {
        $this->assertFalse($this->isPdfA($this->document()->pdf()->toString()));
    }

    public function test_the_config_turns_pdfa_on(): void
    {
        config(['papercut.pdfa' => '2u']);

        $this->assertSame(PdfA::A2u, Papercut::pdfOptions()->pdfA());
        $this->assertTrue($this->isPdfA($this->document()->pdf()->toString()));
    }

    public function test_an_invalid_config_value_is_rejected(): void
    {
        config(['papercut.pdfa' => '9z']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.pdfa');
        $this->document()->pdf()->toString();
    }

    public function test_a_non_string_config_value_is_rejected(): void
    {
        config(['papercut.pdfa' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.pdfa: true; expected null, 1b, 2b, 2u, 2a, 3b, 3u or 3a');
        $this->document()->pdf()->toString();
    }

    public function test_pdfa_on_a_document_overrides_the_config(): void
    {
        $this->assertTrue($this->isPdfA($this->document()->pdfA(PdfA::A2b)->pdf()->toString()));
        $this->assertTrue($this->isPdfA($this->document()->pdfA('2b')->pdf()->toString()));
    }

    public function test_pdfa_null_overrides_a_config_that_is_on(): void
    {
        config(['papercut.pdfa' => '2u']);

        $this->assertFalse($this->isPdfA($this->document()->pdfA(null)->pdf()->toString()));
    }

    public function test_an_invalid_level_string_names_the_valid_ones(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('1b, 2b, 2u, 2a, 3b, 3u, 3a');
        $this->document()->pdfA('4x');
    }

    public function test_pdfa_survives_theme_and_tagged_in_any_order(): void
    {
        $a = $this->document()->pdfA('2u')->theme(['font_size' => 12])->tagged(false);
        $b = $this->document()->tagged(false)->theme(['font_size' => 12])->pdfA('2u');

        foreach ([$a, $b] as $doc) {
            $this->assertTrue($this->isPdfA($doc->pdf()->toString()));
        }
    }
}
