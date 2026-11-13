<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RootTest extends TestCase
{
    private const SIZE = 12.0;

    /** @return list<Glyph> */
    private static function glyphs(Box $b): array
    {
        return array_values(array_filter($b->items, fn ($i) => $i instanceof Glyph));
    }

    private static function unit(): float
    {
        return self::SIZE / Math::stixTwoMath()->unitsPerEm;
    }

    private static function k(string $name): float
    {
        return Math::stixTwoMath()->math()->constant($name) * self::unit();
    }

    public function test_index_sits_kern_before_degree_from_the_start(): void
    {
        $box = Math::layout(Math::root('27', '3'), self::SIZE);
        $index = self::glyphs($box)[0];

        self::assertSame('3', $index->text);
        self::assertEqualsWithDelta(self::k('radicalKernBeforeDegree'), $index->x, 1e-6);
    }

    public function test_surd_starts_after_index_width_plus_both_kerns(): void
    {
        $f = Math::stixTwoMath();
        $box = Math::layout(Math::root('27', '3'), self::SIZE);
        [$index, $surd] = self::glyphs($box);

        $indexWidth = $f->advance($index->gid) * $index->size / $f->unitsPerEm;
        $expected = $index->x + $indexWidth + max(self::k('radicalKernAfterDegree'), -$indexWidth);
        self::assertEqualsWithDelta($expected, $surd->x, 1e-6);
    }

    public function test_index_baseline_is_raised_by_the_percent_of_the_radical_height(): void
    {
        $f = Math::stixTwoMath();
        $box = Math::layout(Math::root('27', '3'), self::SIZE);
        [$index] = self::glyphs($box);

        // The radical alone (no index) gives its height and bottom.
        $sqrt = Math::layout(Math::sqrt('27'), self::SIZE);
        $raise = $f->math()->constant('radicalDegreeBottomRaisePercent') / 100 * ($sqrt->ascent + $sqrt->descent);
        $indexBox = Math::layout(Math::row('3'), $index->size);

        // The index box's bottom (baseline + descent) sits $raise above the radical's bottom.
        self::assertEqualsWithDelta($sqrt->descent - $raise, $index->y + $indexBox->descent, 1e-6);
    }

    /** @return array<string, array{string}> */
    public static function indexes(): array
    {
        return ['digit' => ['3'], 'narrow' => ['i']];
    }

    #[DataProvider('indexes')]
    public function test_index_ink_clears_the_surd_tick(string $idx): void
    {
        $f = Math::stixTwoMath();
        $box = Math::layout(Math::root('27', $idx), self::SIZE);
        [$index, $surd] = self::glyphs($box);

        $isize = $index->size / $f->unitsPerEm;
        $indexBottom = -($index->y - $f->bbox($index->gid)[1] * $isize); // up from baseline
        $indexRight = $index->x + $f->bbox($index->gid)[2] * $isize;

        // The tick: the part of the surd's outline left of the vertex, topmost point.
        $u = self::unit();
        $tickTop = null;
        foreach ($f->contours($surd->gid)[0] as [$px, $py]) {
            if ($surd->x + $px * $u <= $indexRight) {
                $tickTop = max($tickTop ?? $py, $py);
            }
        }
        self::assertNotNull($tickTop);
        $tickTopUp = -($surd->y - $tickTop * $u);

        self::assertGreaterThanOrEqual($tickTopUp, $indexBottom);
    }

    public function test_index_never_starts_before_the_box(): void
    {
        $box = Math::layout(Math::root('2', Math::row()), self::SIZE);
        foreach (self::glyphs($box) as $g) {
            self::assertGreaterThanOrEqual(0.0, $g->x);
        }
        self::assertGreaterThan(0.0, $box->width);
        self::assertNotEmpty(array_filter($box->items, fn ($i) => $i instanceof Rule));
    }

    public function test_narrow_index_clamps_the_kerns(): void
    {
        $f = Math::stixTwoMath();
        $before = self::k('radicalKernBeforeDegree');
        [$index, $surd] = self::glyphs(Math::layout(Math::root('27', 'i'), self::SIZE));

        // The index is narrower than |kernAfter|: the after-kern clamps to minus its width.
        self::assertEqualsWithDelta($before, $index->x, 1e-6);
        self::assertEqualsWithDelta($before, $surd->x, 1e-6);

        [$surdOnly] = self::glyphs(Math::layout(Math::root('27', Math::row()), self::SIZE));
        self::assertEqualsWithDelta($before, $surdOnly->x, 1e-6);
    }
}
