<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\Document;
use Papercut\Export\PdfOptions;
use Papercut\Tests\Support\PdfInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExamplesTest extends TestCase
{
    /** @return array<string, array{string, int, int}> name, min pages, max pages */
    public static function examples(): array
    {
        return ['invoice' => ['invoice', 1, 1], 'transcript' => ['transcript', 2, \PHP_INT_MAX], 'report' => ['report', 2, \PHP_INT_MAX], 'responses' => ['responses', 1, 1], 'guide' => ['guide', 3, 3], 'certificate' => ['certificate', 1, 1], 'article' => ['article', 2, 2]];
    }

    #[DataProvider('examples')]
    public function test_example_produces_a_valid_pdf(string $name, int $minPages, int $maxPages): void
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
        $pages = (int) PdfInspector::info($pdf)['Pages'];
        self::assertGreaterThanOrEqual($minPages, $pages);
        self::assertLessThanOrEqual($maxPages, $pages);
    }

    public function test_article_converts_without_warnings_even_in_strict_mode(): void
    {
        $document = require dirname(__DIR__, 4) . '/examples/core/documents/article.php';
        self::assertInstanceOf(Document::class, $document);
        $warnings = [];
        $document->toPdf(new PdfOptions(strict: true, onWarning: function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        }));
        self::assertSame([], $warnings);
    }

    public function test_certificate_text_sits_on_the_template(): void
    {
        $root = dirname(__DIR__, 4);
        exec(sprintf('cd %s && %s -d xdebug.mode=off examples/core/certificate.php 2>&1', escapeshellarg($root), escapeshellarg(PHP_BINARY)));
        $text = PdfInspector::text((string) file_get_contents($root . '/examples/core/certificate.pdf'));
        foreach (['C E R T I F I C A T E', 'of Completion', 'This certifies that', 'Priya Raman', 'Introduction to Beekeeping', 'DATE', 'SIGNATURE'] as $expected) {
            self::assertStringContainsStringIgnoringCase($expected, $text);
        }
    }

    public function test_report_example_makes_no_unbacked_claims(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4) . '/examples/core/documents/report.php');
        foreach (['PAGES PER SECOND', '38 KB', 'Benchmark', 'shrinking', 'every modifier also accepts a string'] as $claim) {
            self::assertStringNotContainsString($claim, $source);
        }
    }

    public function test_transcript_heading_sits_in_the_summary_box_like_the_blade_view_and_courses_are_distinct(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4) . '/examples/core/documents/transcript.php');
        self::assertMatchesRegularExpression('/\$summary = Column::make\(\s*Text::h3\(\'Cumulative summary\'\),/', $source);
        self::assertStringNotContainsString('keepWithNext', $source);
        $root = dirname(__DIR__, 4);
        exec(sprintf('cd %s && %s -d xdebug.mode=off examples/core/transcript.php 2>&1', escapeshellarg($root), escapeshellarg(PHP_BINARY)));
        $text = PdfInspector::text((string) file_get_contents($root . '/examples/core/transcript.pdf'));
        self::assertDoesNotMatchRegularExpression('/\b(Calculus|Physics|Logic) [2-9]\b/', $text);
    }

    /** The home page shows trimmed excerpts of the transcript; each must still be a run of lines in the real file. */
    public function test_home_page_excerpts_match_the_transcript_sources(): void
    {
        $root = dirname(__DIR__, 4);
        $home = (string) file_get_contents($root . '/site/src/README.md');
        $files = ['blade' => '/examples/laravel/views/transcript.blade.php', 'php' => '/examples/core/documents/transcript.php'];
        foreach ($files as $lang => $file) {
            self::assertSame(1, preg_match('/^```' . $lang . '\n(.*?)^```$/ms', $home, $m), "no {$lang} excerpt on the home page");
            $excerpt = array_map('trim', explode("\n", rtrim($m[1])));
            $source = array_map('trim', explode("\n", (string) file_get_contents($root . $file)));
            $found = false;
            for ($i = 0; $i + count($excerpt) <= count($source) && !$found; $i++) {
                $found = array_slice($source, $i, count($excerpt)) === $excerpt;
            }
            self::assertTrue($found, "the home page's {$lang} excerpt no longer matches {$file}");
        }
    }
}
