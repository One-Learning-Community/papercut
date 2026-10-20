<?php

declare(strict_types=1);

namespace Papercut\Tests\Export;

use Papercut\{Document, Image, OverflowMode, Page, Text};
use Papercut\Exception\LayoutOverflow;
use Papercut\Export\{ExportOptions, PdfExporter, PdfOptions};
use Papercut\Layout\WidthResolver;
use Papercut\Style\Theme;
use PHPUnit\Framework\TestCase;

use function Papercut\{mm, pt};

final class PdfExporterTest extends TestCase
{
    private function doc(): Document
    {
        return Document::make(Page::a5()->body(Text::make('Hi')))->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function test_to_pdf_is_a_pdf_output(): void
    {
        $out = $this->doc()->toPdf();
        self::assertSame('application/pdf', $out->mime);
        self::assertSame('pdf', $out->extension);
        self::assertStringStartsWith('%PDF-', $out->bytes());
    }

    public function test_export_with_plain_export_options_uses_their_theme(): void
    {
        $doc = $this->doc();
        $themed = $doc->export(new PdfExporter(), new ExportOptions(theme: Theme::default()->with(fontSize: 20.0)));
        self::assertNotSame($doc->export(new PdfExporter())->bytes(), $themed->bytes());
        self::assertSame($doc->toPdf()->bytes(), $doc->export(new PdfExporter())->bytes());
    }

    public function test_from_is_identity_for_pdf_options(): void
    {
        $o = new PdfOptions(overflow: OverflowMode::Clip);
        self::assertSame($o, PdfOptions::from($o));
        self::assertSame(OverflowMode::Throw, PdfOptions::from(new ExportOptions())->overflow);
    }

    public function test_pdf_options_keep_overflow_and_cache_dir(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $doc = Document::make(Page::a5()->body(Image::bytes((string) $png)->width(mm(10))->height(mm(5000))));
        try {
            $doc->toPdf();
            self::fail('expected LayoutOverflow');
        } catch (LayoutOverflow) {
            self::addToAssertionCount(1);
        }
        $warnings = [];
        $pdf = $doc->toPdf(new PdfOptions(onWarning: function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        }, overflow: OverflowMode::Clip))->bytes();
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertNotSame([], $warnings);

        $dir = sys_get_temp_dir() . '/papercut-cachedir-' . getmypid();
        $kerned = Document::make(Page::a5()->body(Text::make('AVATAR Wavy Tea')));
        try {
            $off = $kerned->toPdf(new PdfOptions(cacheDir: false))->bytes();
            self::assertDirectoryDoesNotExist($dir);
            self::assertSame($off, $kerned->toPdf(new PdfOptions(cacheDir: $dir))->bytes());
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }
    }

    public function test_width_resolver_for_builds_a_fresh_engine_each_call(): void
    {
        self::assertNotSame(WidthResolver::for(new ExportOptions()), WidthResolver::for(new ExportOptions()));
    }
}
