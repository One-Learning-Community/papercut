<?php

declare(strict_types=1);

namespace Papercut\Tests\Export;

use Papercut\{Document, Equation, Page, Span, Text};
use Papercut\Exception\UnsupportedFeature;
use Papercut\Export\{HtmlOptions, PdfOptions};
use Papercut\Math\Math;
use PHPUnit\Framework\TestCase;

final class MathNotesReportTest extends TestCase
{
    private const TABLE = '<math><mtable><mtr><mtd><mi>a</mi></mtd></mtr></mtable></math>';

    /** @return list<string> */
    private function pdfMessages(Document $doc): array
    {
        $messages = [];
        $doc->toPdf(new PdfOptions(onWarning: function (string $m) use (&$messages): void {
            $messages[] = $m;
        }, cacheDir: false));
        return $messages;
    }

    public function test_block_equation_from_mathml_reports_table(): void
    {
        $messages = $this->pdfMessages(Document::make(Page::a4()->body(Equation::of(Math::mathml(self::TABLE)))));
        self::assertCount(1, $messages);
        self::assertStringContainsString('math <mtable>', $messages[0]);
    }

    public function test_nested_in_builder_fraction(): void
    {
        $messages = $this->pdfMessages(Document::make(Page::a4()->body(Equation::of(Math::frac(Math::mathml(self::TABLE), 'x')))));
        self::assertCount(1, $messages);
        self::assertStringContainsString('math <mtable>', $messages[0]);
    }

    public function test_inline_span(): void
    {
        $text = Text::spans(Span::text('a '), Span::math(Math::mathml('<math><menclose notation="box"><mi>x</mi></menclose></math>')));
        $messages = $this->pdfMessages(Document::make(Page::a4()->body($text)));
        self::assertCount(1, $messages);
        self::assertStringContainsString('math <menclose>', $messages[0]);
    }

    public function test_strict_mode_fails_on_dropped_only(): void
    {
        $dropped = Document::make(Page::a4()->body(Equation::of(Math::mathml('<math><mi>a</mi><img src="x.png"/></math>'))));
        try {
            $dropped->toPdf(new PdfOptions(strict: true, cacheDir: false));
            self::fail('Expected UnsupportedFeature');
        } catch (UnsupportedFeature $e) {
            self::assertStringContainsString('math <img>', $e->getMessage());
        }

        $approx = Document::make(Page::a4()->body(Equation::of(Math::mathml(self::TABLE))));
        $approx->toPdf(new PdfOptions(strict: true, cacheDir: false));
        $this->addToAssertionCount(1);
    }

    public function test_html_export_reports_too(): void
    {
        $messages = [];
        Document::make(Page::a4()->body(Equation::of(Math::mathml(self::TABLE))))
            ->toHtml(new HtmlOptions(onWarning: function (string $m) use (&$messages): void {
                $messages[] = $m;
            }));
        $math = array_values(array_filter($messages, static fn (string $m): bool => str_contains($m, 'math <mtable>')));
        self::assertCount(1, $math);
        self::assertStringStartsWith('html: ', $math[0]);
    }
}
