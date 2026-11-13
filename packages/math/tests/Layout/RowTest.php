<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use Papercut\Math\Node\Row;
use PHPUnit\Framework\TestCase;

final class RowTest extends TestCase
{
    /** @return list<?string> */
    private static function forms(string $s): array
    {
        $row = Math::row($s);
        self::assertInstanceOf(Row::class, $row);

        return $row->forms();
    }

    public function test_leading_minus_is_prefix(): void
    {
        self::assertSame(['prefix', null, 'infix', null], self::forms('−b ± c'));
    }

    public function test_minus_after_operator_is_prefix(): void
    {
        self::assertSame([null, 'infix', 'prefix', null], self::forms('a = −b'));
    }

    public function test_minus_after_open_paren_is_prefix(): void
    {
        self::assertSame(['prefix', 'prefix', null, 'infix', null, 'postfix'], self::forms('(−a + b)'));
    }

    public function test_binary_minus_is_infix(): void
    {
        self::assertSame([null, 'infix', null], self::forms('a − b'));
        self::assertSame(['prefix', null, 'postfix', 'infix', null], self::forms('(a) − b'));
    }

    public function test_empty_fraction_slots_lay_out(): void
    {
        $box = Math::layout(Math::frac('', '2'), 12);
        self::assertGreaterThan(0.0, $box->width);
        self::assertCount(1, array_filter($box->items, fn ($i) => $i instanceof Rule));
    }

    public function test_empty_sqrt_lays_out(): void
    {
        $box = Math::layout(Math::sqrt(''), 12);
        self::assertGreaterThan(0.0, $box->width);
        $drawn = array_filter($box->items, fn ($i) => $i instanceof Rule || $i instanceof Glyph);
        self::assertNotEmpty($drawn);
    }

    public function test_mixed_number_gap(): void
    {
        $row = Math::row('3', Math::frac('1', '2'));
        self::assertInstanceOf(Row::class, $row);
        $three = Math::layout(Math::row('3'), 18, false)->width;
        $lone = Math::layout(Math::frac('1', '2'), 18, false);
        $loneRule = array_values(array_filter($lone->items, fn ($i) => $i instanceof Rule))[0];
        $box = Math::layout($row, 18, false);
        $rule = array_values(array_filter($box->items, fn ($i) => $i instanceof Rule))[0];
        // 3/18 em at 18pt is 3pt between the digit and the fraction box.
        self::assertEqualsWithDelta(3.0, $rule->x - $loneRule->x - $three, 0.01);
    }

    public function test_row_applies_forms_to_spacing(): void
    {
        $prefix = Math::layout(Math::row('−x'), 12)->width;
        $infix = Math::layout(Math::row('a − x'), 12)->width - Math::layout(Math::row('a'), 12)->width;
        self::assertLessThan($infix, $prefix);
    }

    public function test_paired_bars_open_and_close(): void
    {
        foreach (['|', '‖'] as $bar) {
            $row = Math::row($bar . 'x' . $bar . '+y');
            self::assertInstanceOf(Row::class, $row);
            self::assertSame(['prefix', null, 'postfix', 'infix', null], $row->forms());
        }
    }

    public function test_plus_after_closing_bar_gets_binary_spacing(): void
    {
        $with = Math::layout(Math::row('|x|+y'), 18, false)->width;
        $without = Math::layout(Math::row('|x|y'), 18, false)->width;
        // + at 18pt: 4/18 em each side is 4pt per side, plus the glyph itself.
        $plus = Math::layout(Math::row('+'), 18, false)->width;
        self::assertEqualsWithDelta(8.0, $with - $without - $plus, 0.05);
    }

    public function test_unpaired_bar_is_a_relation(): void
    {
        $row = Math::row('a|b');
        self::assertInstanceOf(Row::class, $row);
        self::assertSame([null, 'infix', null], $row->forms());
    }
}
