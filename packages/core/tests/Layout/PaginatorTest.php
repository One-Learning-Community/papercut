<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Document, Image, OverflowMode, Page, PageNumber, Text};
use Papercut\Exception\LayoutOverflow;
use Papercut\Layout\{LayoutContext, LayoutEngine, Paginator};
use Papercut\Paint\Painter;
use Papercut\Paint\TextRun;
use Papercut\Style\Theme;
use Papercut\Tests\Support\FakeBackend;
use Papercut\Text\FontRegistry;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class PaginatorTest extends TestCase
{
    /** @var list<string> */
    private array $warnings = [];

    /** @return list<\Papercut\Paint\PageDisplayList> */
    private function paginate(Document $doc, OverflowMode $mode = OverflowMode::Throw): array
    {
        $b = new FakeBackend();
        $ctx = new LayoutContext(FontRegistry::withDefaults($b), Theme::default(), $b, $mode, function (string $w): void { $this->warnings[] = $w; });
        return Painter::paint((new Paginator(new LayoutEngine($ctx), $ctx))->paginate($doc));
    }

    /** @return list<string> */
    private function texts(\Papercut\Paint\PageDisplayList $p): array
    {
        return array_values(array_map(fn (TextRun $r) => $r->text, array_filter($p->ops, fn ($o) => $o instanceof TextRun)));
    }

    private function small(): Page
    {
        // 200x100 page, 10pt margins -> body 180x80 minus header/footer
        return Page::size(pt(200), pt(100))->margin(pt(10))->headerGap(pt(0))->footerGap(pt(0));
    }

    public function test_flows_body_across_pages_with_header_footer_and_page_numbers(): void
    {
        $lines = array_map(fn (int $i) => Text::make("L$i"), range(1, 6));
        $doc = Document::make($this->small()->header('Head')->footer(PageNumber::make('{page}/{total}'))->body(...$lines));
        $pages = $this->paginate($doc);
        // body height = 80 - 14 - 14 = 52 => 3 lines (42) per page
        self::assertCount(2, $pages);
        self::assertSame(['Head', 'L1', 'L2', 'L3', '1/2'], $this->texts($pages[0]));
        self::assertSame(['Head', 'L4', 'L5', 'L6', '2/2'], $this->texts($pages[1]));
    }

    public function test_each_page_template_starts_new_page(): void
    {
        $pages = $this->paginate(Document::make($this->small()->body('A'), $this->small()->body('B')));
        self::assertCount(2, $pages);
    }

    public function test_overflow_throws_with_path(): void
    {
        $this->expectException(LayoutOverflow::class);
        $this->expectExceptionMessageMatches('/image/');
        $this->paginate(Document::make($this->small()->body(Image::bytes('x')->height(pt(500)))));
    }

    public function test_overflow_clips_in_clip_mode(): void
    {
        $pages = $this->paginate(Document::make($this->small()->body(Image::bytes('x')->height(pt(500)))), OverflowMode::Clip);
        self::assertCount(1, $pages);
        self::assertNotEmpty($this->warnings);
    }

    public function test_header_footer_too_big(): void
    {
        $this->expectException(LayoutOverflow::class);
        $tall = Text::make("a\nb")->size(pt(40));
        $this->paginate(Document::make($this->small()->header($tall)->footer($tall)->body('x')));
    }

    public function test_background_paints_first_on_every_page(): void
    {
        $lines = array_map(fn (int $i) => Text::make("L$i"), range(1, 12));
        $pages = $this->paginate(Document::make($this->small()->background('BG')->header('Head')->body(...$lines)));
        // body height = 80 - 14 = 66 => 4 lines (56) per page
        self::assertCount(3, $pages);
        foreach ($pages as $p) {
            self::assertSame(['BG', 'Head'], array_slice($this->texts($p), 0, 2));
        }
        // the background is laid out at the full page, not inside the margins
        $first = $pages[0]->ops[0];
        self::assertInstanceOf(TextRun::class, $first);
        self::assertEqualsWithDelta(0.0, $first->x, 1e-9);
    }

    public function test_page_number_intrinsic_width_uses_999(): void
    {
        $h = new \Papercut\Tests\Support\LayoutHarness();
        $size = $h->engine->intrinsic(PageNumber::make('{page}/{total}'), \Papercut\Style\ComputedStyle::root(Theme::default()));
        // "999/999" = 7 glyphs x 5pt (FakeFont, 10pt)
        self::assertEqualsWithDelta(['min' => 35.0, 'max' => 35.0], $size, 1e-9);
    }

    public function test_page_size_carried_to_display_list(): void
    {
        $pages = $this->paginate(Document::make(Page::a4()->body('x')));
        self::assertEqualsWithDelta([595.28, 841.89], [$pages[0]->width, $pages[0]->height], 0.01);
    }
}
