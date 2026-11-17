<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\{Box, Glyph, Rule};
use Papercut\Math\Math;
use Papercut\Math\Node\{Grid, Node, Scripts};
use PHPUnit\Framework\TestCase;

final class GridTest extends TestCase
{
    private const SIZE = 12.0;

    private static function axis(): float
    {
        return Math::stixTwoMath()->math()->constant('axisHeight') * self::SIZE / 1000;
    }

    private static function lay(Node $n, bool $display = true): Box
    {
        return Math::layout($n, self::SIZE, $display);
    }

    /** @return list<Glyph> */
    private static function glyphs(Box $box): array
    {
        return array_values(array_filter($box->items, static fn ($i): bool => $i instanceof Glyph));
    }

    /**
     * Where a cell, laid out in text style, ended up inside $grid: its first item's position there minus alone.
     *
     * @return array{float, float} dx, dy (dy is the cell's baseline)
     */
    private static function shiftOf(Box $grid, Node $cell): array
    {
        $a = self::lay($cell, false)->items[0];
        foreach ($grid->items as $i) {
            if ($a instanceof Rule && $i instanceof Rule && $i->w === $a->w) {
                return [$i->x - $a->x, $i->y - $a->y];
            }
            if ($a instanceof Glyph && $i instanceof Glyph && $i->gid === $a->gid) {
                return [$i->x - $a->x, $i->y - $a->y];
            }
        }
        self::fail('cell not found in the grid');
    }

    public function test_column_gap_is_one_em(): void
    {
        $a = Math::row('a');
        $b = Math::row('b');
        $box = self::lay(new Grid([[$a, $b]]));
        $this->assertEqualsWithDelta(self::lay($a, false)->width + self::lay($b, false)->width + 12.0, $box->width, 1e-9);
    }

    public function test_column_width_is_the_widest_cell(): void
    {
        $box = self::lay(new Grid([[Math::row('a')], [Math::row('abcd')]]));
        $this->assertEqualsWithDelta(self::lay(Math::row('abcd'), false)->width, $box->width, 1e-9);
    }

    public function test_cells_are_centred_by_default_and_left_right_on_request(): void
    {
        $i = Math::row('i');
        $w = Math::row('WWWW');
        $wi = self::lay($i, false)->width;
        $ww = self::lay($w, false)->width;
        foreach ([[[], ($ww - $wi) / 2], [['left'], 0.0], [['right'], $ww - $wi]] as [$align, $expect]) {
            $box = self::lay(new Grid([[$i], [$w]], $align));
            $alone = self::glyphs(self::lay($i, false))[0];
            $first = self::glyphs($box)[0];
            $this->assertEqualsWithDelta($expect, $first->x - $alone->x, 1e-9);
        }
    }

    public function test_rows_sit_one_and_a_fifth_em_apart(): void
    {
        $box = self::lay(new Grid([[Math::row('x')], [Math::row('y')]]));
        $g = self::glyphs($box);
        $this->assertEqualsWithDelta(14.4, $g[1]->y - $g[0]->y, 1e-9);
    }

    public function test_tall_content_pushes_rows_apart(): void
    {
        $f1 = Math::frac('a', 'b');
        $f2 = Math::frac('c', 'd');
        $box = self::lay(new Grid([[$f1], [$f2]]));
        $rules = array_values(array_filter($box->items, static fn ($i): bool => $i instanceof Rule));
        // Fraction bars sit at the same height above each cell's baseline.
        $expect = self::lay($f1, false)->descent + self::lay($f2, false)->ascent + 0.1 * 12;
        $this->assertGreaterThan(14.4, $expect);
        $this->assertCount(2, $rules);
        $this->assertEqualsWithDelta($expect, $rules[1]->y - $rules[0]->y, 1e-9);
    }

    public function test_grid_is_centred_on_the_axis(): void
    {
        foreach ([1, 2, 3] as $n) {
            $rows = [];
            $frac = Math::frac('a', 'b');
            for ($r = 0; $r < $n; $r++) {
                $rows[] = [Math::row('x'), $frac];
            }
            $box = self::lay(new Grid($rows));
            $this->assertEqualsWithDelta(2 * self::axis(), $box->ascent - $box->descent, 1e-9);
            // The tallest cell of the first row (the fraction) starts exactly at the top of the box.
            $this->assertEqualsWithDelta(-$box->ascent, self::shiftOf($box, $frac)[1] - self::lay($frac, false)->ascent, 1e-9);
        }
    }

    public function test_cells_use_text_style_unless_display(): void
    {
        $f = Math::frac('a', 'b');
        $text = self::lay(new Grid([[$f]]));
        $disp = self::lay(new Grid([[$f]], display: true));
        $this->assertLessThan($disp->height(), $text->height());
    }

    public function test_rows_and_alignments_with_odd_keys_are_read_as_lists(): void
    {
        $odd = new Grid(
            [5 => [3 => Math::row('a'), 9 => Math::row('b')], 'x' => [7 => Math::row('c')]],
            ['p' => 'left', 'q' => 'right'],
        );
        $clean = new Grid([[Math::row('a'), Math::row('b')], [Math::row('c')]], ['left', 'right']);
        $this->assertSame(2, $odd->columns);
        $this->assertSame(['left', 'right'], $odd->columnAlign);
        $this->assertSame([0, 1], array_keys($odd->rows));
        $this->assertSame([0, 1], array_keys($odd->rows[0]));
        $this->assertSame([0, 1], array_keys($odd->rows[1]));
        $this->assertEquals(self::lay($clean), self::lay($odd));
    }

    public function test_a_nested_array_alignment_throws_without_a_warning(): void
    {
        set_error_handler(static function (int $no, string $msg): never {
            self::fail("unexpected PHP error: $msg");
        });
        try {
            foreach ([[['left']], ['left', ['x']]] as $align) {
                try {
                    new Grid([[Math::row('a')]], self::untyped($align));
                    $this->fail('expected an InvalidArgumentException');
                } catch (\InvalidArgumentException $e) {
                    $this->assertStringContainsString('Unknown column alignment', $e->getMessage());
                }
            }
        } finally {
            restore_error_handler();
        }
    }

    private static function untyped(mixed $v): mixed
    {
        return $v;
    }

    public function test_ragged_rows_are_padded(): void
    {
        $g = new Grid([[Math::row('a')], [Math::row('a'), Math::row('b'), Math::row('c')]]);
        $this->assertSame(3, $g->columns);
        $this->assertCount(3, $g->rows[0]);
        $this->assertCount(3, $g->columnAlign);
        $this->assertSame(['center', 'center', 'center'], $g->columnAlign);
        $this->assertSame(['left', 'right', 'right'], (new Grid($g->rows, ['left', 'right']))->columnAlign);
    }

    public function test_row_alignments(): void
    {
        $tall = Math::frac('a', 'b');
        $short = Math::row('x');
        $axis = self::axis();
        $ta = self::lay($tall, false);
        $sa = self::lay($short, false);
        foreach (['baseline', 'axis', 'center', 'top', 'bottom'] as $mode) {
            $box = self::lay(new Grid([[$tall, $short]], [], $mode));
            [, $ty] = self::shiftOf($box, $tall);
            [, $sy] = self::shiftOf($box, $short);
            $tallTop = $ty - $ta->ascent;
            $tallBottom = $ty + $ta->descent;
            $shortTop = $sy - $sa->ascent;
            $shortBottom = $sy + $sa->descent;
            switch ($mode) {
                case 'top':
                    $this->assertEqualsWithDelta($tallTop, $shortTop, 1e-9);
                    break;
                case 'bottom':
                    $this->assertEqualsWithDelta($tallBottom, $shortBottom, 1e-9);
                    break;
                case 'center':
                    $this->assertEqualsWithDelta(($tallTop + $tallBottom) / 2, ($shortTop + $shortBottom) / 2, 1e-9);
                    break;
                case 'baseline':
                    $this->assertEqualsWithDelta($ty, $sy, 1e-9);
                    break;
                case 'axis':
                    // Cell centres sit on the row's axis (axis above the row baseline); the grid then shifts as a whole to centre on the axis.
                    $rowA = max($ta->ascent, $sa->ascent);
                    $rowD = max($ta->descent, $sa->descent);
                    $expect = -$axis + (-$axis - ($rowD - $rowA) / 2);
                    $this->assertEqualsWithDelta($expect, ($tallTop + $tallBottom) / 2, 1e-9);
                    $this->assertEqualsWithDelta($expect, ($shortTop + $shortBottom) / 2, 1e-9);
                    break;
            }
        }
    }

    public function test_empty_grid_lays_out_empty(): void
    {
        foreach ([new Grid([]), new Grid([[]])] as $g) {
            $box = self::lay($g);
            $this->assertSame(0.0, $box->width);
            $this->assertSame(0.0, $box->ascent);
            $this->assertSame(0.0, $box->descent);
        }
    }

    public function test_children_are_row_major(): void
    {
        [$a, $b, $c, $d] = [Math::row('a'), Math::row('b'), Math::row('c'), Math::row('d')];
        $this->assertSame([$a, $b, $c, $d], (new Grid([[$a, $b], [$c, $d]]))->children());
    }

    public function test_invalid_alignment_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Grid([[Math::row('a')]], ['middle']);
    }

    public function test_invalid_row_alignment_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Grid([[Math::row('a')]], [], 'middle');
    }

    public function test_nested_grid_in_a_cell_and_in_scripts(): void
    {
        $inner = new Grid([[Math::row('a'), Math::row('b')], [Math::row('c'), Math::row('d')]]);
        $this->assertGreaterThan(0.0, self::lay(new Grid([[$inner, Math::row('x')]]))->width);
        $this->assertGreaterThan(0.0, self::lay(new Scripts($inner, null, Math::row('2')))->width);
    }

    public function test_a_60_by_60_grid_lays_out_fast(): void
    {
        $rows = [];
        for ($i = 0; $i < 60; $i++) {
            $row = [];
            for ($j = 0; $j < 60; $j++) {
                $row[] = Math::row('a');
            }
            $rows[] = $row;
        }
        memory_reset_peak_usage();
        $mem = memory_get_peak_usage();
        $t = microtime(true);
        $box = self::lay(new Grid($rows));
        $this->assertLessThan(2.0, microtime(true) - $t);
        $this->assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $mem);
        $this->assertGreaterThan(0.0, $box->width);
    }

    public function test_column_spacing_sets_gaps(): void
    {
        $grid = new Grid([[Math::row('a'), Math::row('b'), Math::row('c')]], ['left'], columnSpacing: [0.0, 2.0]);
        self::assertSame([0.0, 2.0], $grid->columnSpacing);
        $xs = array_map(static fn (Glyph $g): float => $g->x, self::glyphs(self::lay($grid)));
        $w = static fn (string $s): float => self::lay(Math::row($s))->width;
        self::assertEqualsWithDelta($w('a'), $xs[1] - $xs[0], 0.01);
        self::assertEqualsWithDelta($w('b') + 2 * self::SIZE, $xs[2] - $xs[1], 0.01);
    }

    public function test_column_spacing_repeats_and_defaults(): void
    {
        self::assertSame([1.0, 1.0], (new Grid([[Math::row('a'), Math::row('b'), Math::row('c')]]))->columnSpacing);
        self::assertSame([0.5, 0.5], (new Grid([[Math::row('a'), Math::row('b'), Math::row('c')]], columnSpacing: [0.5]))->columnSpacing);
        self::assertSame([], (new Grid([[Math::row('a')]], columnSpacing: [3.0]))->columnSpacing);
    }

    public function test_negative_column_spacing_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Grid([[Math::row('a'), Math::row('b')]], columnSpacing: [-0.1]);
    }
}
