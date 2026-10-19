<?php

declare(strict_types=1);

namespace Papercut\Tests\Paint;

use Papercut\Paint\{Clip, DocumentMeta, Line, Opacity, PageDisplayList, Rect, TextRun};
use Papercut\Tests\Support\FakeFont;
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

final class OpsTest extends TestCase
{
    public function test_ops_hold_their_values(): void
    {
        $run = new TextRun(10, 20, 'Hi', new FakeFont(), 12, Color::black());
        self::assertSame('Hi', $run->text);
        self::assertSame(0.0, $run->letterSpacing);

        $rect = new Rect(0, 0, 100, 50, fill: Color::white(), radius: 4);
        self::assertNull($rect->stroke);
        self::assertSame(4.0, $rect->radius);

        $line = new Line(0, 0, 10, 0, Color::black(), dashed: true);
        self::assertTrue($line->dashed);

        $clip = new Clip(0, 0, 10, 10, [$rect, new Opacity(0.5, [$line])]);
        self::assertCount(2, $clip->ops);

        $page = new PageDisplayList(595, 842, [$run, $clip]);
        self::assertCount(2, $page->ops);

        $meta = new DocumentMeta(title: 'T');
        self::assertSame('T', $meta->title);
        self::assertNull($meta->createdAt);
    }

    public function test_rect_rejects_negative_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Rect(0, 0, -1, 10);
    }

    public function test_opacity_rejects_out_of_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Opacity(1.5, []);
    }

    public function test_page_rejects_zero_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PageDisplayList(0, 842, []);
    }

    public function test_text_run_rejects_non_positive_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextRun(0, 0, 'x', new FakeFont(), 0, Color::black());
    }

    public function test_fake_font_metrics(): void
    {
        $f = new FakeFont();
        self::assertSame(12.0, $f->advance('ab', 12));
        self::assertSame(14.0, $f->advance('ab', 12, 1.0));
        self::assertSame(2, $f->advanceCalls);
        self::assertFalse($f->covers('漢'));
    }
}
