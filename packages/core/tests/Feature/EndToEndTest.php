<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\{Column, Document, Page, PageNumber, Row, Spacer, Table, Text};
use Papercut\Tests\Support\PdfInspector;
use PHPUnit\Framework\TestCase;

use function Papercut\{mm, pt};

final class EndToEndTest extends TestCase
{
    private function transcript(int $rows): Document
    {
        $courses = array_map(fn (int $i) => ["Course $i", (string) ($i % 4 + 1), ['A', 'B', 'C'][$i % 3]], range(1, $rows));
        return Document::make(
            Page::a4()->margin(mm(20))
                ->header(Row::make(Text::make('Official Transcript')->bold()->size(pt(16)), Spacer::flex(), Text::make('OLC')))
                ->body(Column::make(
                    Text::h2('Student: Ada Lovelace'),
                    Table::make()->columns('3fr 25mm 20mm')->head('Course', 'Credits', 'Grade')
                        ->rows($courses, fn (array $c) => [$c[0], $c[1], Text::make($c[2])->right()]),
                )->gap(pt(8)))
                ->footer(PageNumber::make('Page {page} of {total}')->center()),
        )->title('Transcript')->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function test_multi_page_transcript(): void
    {
        $pdf = $this->transcript(120)->toString();
        PdfInspector::check($pdf);
        $pages = (int) PdfInspector::info($pdf)['Pages'];
        self::assertGreaterThanOrEqual(2, $pages);
        $text = PdfInspector::text($pdf);
        self::assertStringContainsString("Page 1 of $pages", $text);
        self::assertStringContainsString("Page $pages of $pages", $text);
        // header row repeats on page 2
        $page2 = array_column(PdfInspector::words($pdf, 2), 'text');
        self::assertContains('Course', $page2);
        self::assertContains('Credits', $page2);
        self::assertStringContainsString('Course 120', $text);
    }

    public function test_deterministic(): void
    {
        self::assertSame($this->transcript(30)->toString(), $this->transcript(30)->toString());
    }

    public function test_save(): void
    {
        $path = sys_get_temp_dir() . '/papercut-e2e-' . getmypid() . '.pdf';
        $this->transcript(5)->save($path);
        self::assertFileExists($path);
        PdfInspector::check((string) file_get_contents($path));
        unlink($path);
    }

    public function test_save_missing_directory_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory does not exist');
        $this->transcript(1)->save(sys_get_temp_dir() . '/papercut-no-such-dir-' . getmypid() . '/x.pdf');
    }

    public function test_stream_writes_output_and_returns_byte_count(): void
    {
        $doc = $this->transcript(5);
        $expected = $doc->render();
        ob_start();
        $n = $doc->stream();
        $out = (string) ob_get_clean();
        self::assertSame(strlen($expected), $n);
        self::assertSame($expected, $out);
    }

    public function test_render_and_to_string_are_equivalent(): void
    {
        $doc = $this->transcript(3);
        self::assertSame($doc->render(), $doc->toString());
    }
}
