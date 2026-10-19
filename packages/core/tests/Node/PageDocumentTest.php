<?php

declare(strict_types=1);

namespace Papercut\Tests\Node;

use Papercut\{Document, Page, Text};
use Papercut\Exception\InvalidValue;
use PHPUnit\Framework\TestCase;

use function Papercut\{mm, pt};

final class PageDocumentTest extends TestCase
{
    public function test_size_rejects_non_positive_or_non_finite(): void
    {
        foreach ([[0, 100], [100, 0], [-1, 100], [100, -5]] as [$w, $h]) {
            try {
                Page::size(pt($w), pt($h));
                self::fail('accepted');
            } catch (InvalidValue $e) {
                self::assertSame('page size', $e->kind);
            }
        }
    }

    public function test_page_sizes_and_orientation(): void
    {
        self::assertEqualsWithDelta([595.28, 841.89], [Page::a4()->width(), Page::a4()->height()], 0.01);
        self::assertEqualsWithDelta([841.89, 595.28], [Page::a4()->landscape()->width(), Page::a4()->landscape()->height()], 0.01);
        self::assertEqualsWithDelta([612.0, 792.0], [Page::named('LETTER')->width(), Page::named('letter')->height()], 0.01);
        self::assertSame(100.0, Page::size(pt(100), pt(200))->width());
        $this->expectException(InvalidValue::class);
        Page::named('b5');
    }

    public function test_page_slots_and_margins(): void
    {
        $p = Page::a4()->margin(mm(10), mm(15))->header('H')->footer('F')->body('A')->body(Text::make('B'));
        self::assertEqualsWithDelta(mm(15)->points, $p->margins()->left->points, 1e-9);
        self::assertCount(1, $p->headerNodes());
        self::assertCount(1, $p->footerNodes());
        self::assertCount(2, $p->bodyNodes());
        self::assertEqualsWithDelta(mm(20)->points, Page::a4()->margins()->top->points, 1e-9);
    }

    public function test_document_meta(): void
    {
        $d = Document::make(Page::a4())->title('T')->author('A')->lang('en')->createdAt(new \DateTimeImmutable('2026-01-01'));
        self::assertSame('T', $d->meta()->title);
        self::assertSame('en', $d->meta()->lang);
        self::assertCount(1, $d->pages());
    }
}
