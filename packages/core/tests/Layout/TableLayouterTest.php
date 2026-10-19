<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Table, TableCell};
use Papercut\Layout\{Fragment, Status};
use Papercut\Paint\Rect;
use Papercut\Style\{ComputedStyle, Theme};
use Papercut\Tests\Support\LayoutHarness;
use PHPUnit\Framework\TestCase;

final class TableLayouterTest extends TestCase
{
    // cell padding 4pt 6pt => cell height 14 + 8 = 22
    public function test_column_widths_fixed_fr_auto(): void
    {
        $t = Table::make()->columns('100pt 1fr auto')->row('a', 'b', 'cc');
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($t, 400)->fragment);
        // auto col = 10 + 12 padding = 22 => fr col = 400 - 100 - 22 = 278
        self::assertSame([6.0, 106.0, 384.0], array_column($runs, 'x'));
    }

    public function test_colspan(): void
    {
        $t = Table::make()->columns('1fr 1fr')->row(TableCell::make('wide')->colspan(2)->right());
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($t, 200)->fragment);
        self::assertSame(174.0, $runs[0]['x']); // 200 - 6 - 20
    }

    public function test_header_repeats_on_continuation(): void
    {
        $t = Table::make()->columns('1fr')->head('H')->rows(range(1, 5), fn (int $i) => ["r$i"]);
        $h = new LayoutHarness();
        $first = $h->layout($t, 100, 66); // header 22 + 2 rows
        self::assertSame(Status::Partial, $first->status);
        self::assertSame(['H', 'r1', 'r2'], array_column(LayoutHarness::runs($first->fragment), 'text'));
        $second = $h->layout($t, 100, null, $first->token);
        self::assertSame(['H', 'r3', 'r4', 'r5'], array_column(LayoutHarness::runs($second->fragment), 'text'));
        $noRepeat = $h->layout($t->repeatHeader(false), 100, null, $first->token);
        self::assertSame(['r3', 'r4', 'r5'], array_column(LayoutHarness::runs($noRepeat->fragment), 'text'));
    }

    public function test_th_is_bold_by_default(): void
    {
        $f = (new LayoutHarness())->layout(Table::make()->head('H')->row('b'), 100)->fragment;
        self::assertNotNull($f);
        $fonts = [];
        $walk = function (\Papercut\Layout\Fragment $fr) use (&$walk, &$fonts): void {
            foreach ($fr->ops() as $op) {
                if ($op instanceof \Papercut\Paint\TextRun) { $fonts[$op->text] = $op->font->id(); }
            }
            foreach ($fr->children() as $c) { $walk($c['fragment']); }
        };
        $walk($f);
        self::assertSame('fake:Inter-Bold.ttf', $fonts['H']);
        self::assertSame('fake:Inter-Regular.ttf', $fonts['b']);
    }

    public function test_track_mismatch_warns_and_defaults_to_fr(): void
    {
        $h = new LayoutHarness();
        $r = $h->layout(Table::make()->columns('1fr 1fr')->row('a', 'b', 'c'), 300);
        self::assertSame(Status::Complete, $r->status);
        self::assertCount(1, $h->warnings);
        self::assertSame([6.0, 106.0, 206.0], array_column(LayoutHarness::runs($r->fragment), 'x'));
    }

    public function test_not_fresh_first_row_does_not_fit(): void
    {
        $t = Table::make()->columns('1fr')->row('a');
        self::assertSame(Status::DoesNotFit, (new LayoutHarness())->layout($t, 100, 10, fresh: false)->status);
    }

    public function test_extra_tracks_are_ignored_with_a_warning(): void
    {
        $h = new LayoutHarness();
        $r = $h->layout(Table::make()->columns('50pt 50pt 50pt')->row('a', 'b'), 300);
        self::assertSame(['[table] Table has 2 columns but 3 tracks'], $h->warnings);
        self::assertSame([6.0, 56.0], array_column(LayoutHarness::runs($r->fragment), 'x'));
    }

    public function test_percent_track_resolves_against_table_content_width(): void
    {
        $t = Table::make()->columns('25% 1fr')->padding(\Papercut\pt(10))->row('a', 'b');
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($t, 220)->fragment);
        // content width 200 => 50 + 150
        self::assertSame([16.0, 66.0], array_column($runs, 'x'));
    }

    public function test_auto_tracks_shrink_toward_min_content_when_max_content_overflows(): void
    {
        // max-content 25+12 = 37 each (74 > 60); min-content 10+12 = 22 each; 16 spare split by (max-min)
        $t = Table::make()->columns('auto auto')->row('aa bb', 'cc dd');
        $runs = LayoutHarness::runs((new LayoutHarness())->layout($t, 60)->fragment);
        self::assertSame([6.0, 36.0], array_values(array_unique(array_column($runs, 'x'))));
    }

    public function test_cells_are_stretched_to_the_row_height(): void
    {
        $t = Table::make()->columns('1fr 1fr')->row(TableCell::make('a')->background('#ff0000'), 'b c');
        $f = (new LayoutHarness())->layout($t, 40)->fragment; // col content 8 => "b c" wraps to 2 lines: 28 + 8
        self::assertNotNull($f);
        self::assertSame(36.0, $f->height);
        $rects = array_values(array_filter(self::cellFragments($f)[0]->ops(), fn ($op) => $op instanceof Rect));
        self::assertSame(36.0, $rects[0]->height);
    }

    public function test_oversized_row_splits_its_cells_in_parallel_and_repeats_the_header(): void
    {
        $t = Table::make()->columns('1fr 1fr')->head('H', 'I')->row(TableCell::make('a', 'b', 'c'), 'x')->row('y', 'z');
        $h = new LayoutHarness();
        $first = $h->layout($t, 100, 60); // header 22, row needs 50 > 38 left
        self::assertSame(Status::Partial, $first->status);
        self::assertSame(['H', 'I', 'a', 'b', 'x'], array_column(LayoutHarness::runs($first->fragment), 'text'));
        self::assertNotNull($first->token);
        self::assertSame(0, $first->token->index);
        self::assertNotNull($first->token->parallel[0] ?? null);
        self::assertNull($first->token->parallel[1]);

        $rest = $h->layout($t, 100, null, $first->token);
        self::assertSame(Status::Complete, $rest->status);
        self::assertSame(['H', 'I', 'c', 'y', 'z'], array_column(LayoutHarness::runs($rest->fragment), 'text'));
        self::assertSame(22.0 + 18.0 + 22.0, $rest->fragment?->height);
    }

    public function test_keep_together_moves_the_whole_table_unless_fresh(): void
    {
        $t = Table::make()->columns('1fr')->keepTogether()->rows(range(1, 5), fn (int $i) => ["r$i"]);
        $h = new LayoutHarness();
        self::assertSame(Status::DoesNotFit, $h->layout($t, 100, 66, fresh: false)->status);
        self::assertSame(Status::Partial, $h->layout($t, 100, 66)->status);
    }

    public function test_intrinsic_sums_columns(): void
    {
        $t = Table::make()->columns('50pt auto')->row('a', 'bbbb');
        $s = (new LayoutHarness())->engine->intrinsic($t, ComputedStyle::root(Theme::default()));
        self::assertSame(['min' => 82.0, 'max' => 82.0], $s);
    }

    /** @return list<Fragment> cell fragments in paint order */
    private static function cellFragments(Fragment $table): array
    {
        return array_map(fn (array $c): Fragment => $c['fragment'], $table->children());
    }
}
