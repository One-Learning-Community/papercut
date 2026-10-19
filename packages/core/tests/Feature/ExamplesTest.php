<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\Tests\Support\PdfInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExamplesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function examples(): array
    {
        return ['invoice' => ['invoice'], 'transcript' => ['transcript'], 'report' => ['report']];
    }

    #[DataProvider('examples')]
    public function test_example_produces_a_valid_pdf(string $name): void
    {
        $root = dirname(__DIR__, 4);
        $pdfPath = $root . '/examples/core/' . $name . '.pdf';
        @unlink($pdfPath);

        $output = [];
        $code = -1;
        exec(sprintf('cd %s && %s -d xdebug.mode=off examples/core/%s.php 2>&1', escapeshellarg($root), escapeshellarg(PHP_BINARY), $name), $output, $code);

        self::assertSame(0, $code, implode("\n", $output));
        self::assertFileExists($pdfPath);
        self::assertStringContainsString($name . '.pdf', implode("\n", $output));
        $pdf = (string) file_get_contents($pdfPath);
        PdfInspector::check($pdf);
        self::assertGreaterThanOrEqual(1, (int) PdfInspector::info($pdf)['Pages']);
    }
}
