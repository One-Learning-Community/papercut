<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\NoteLevel;
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

    public function test_mixed_number_gap_survives_a_fallback_around_the_fraction(): void
    {
        $note = new MathNote(NoteLevel::Approximated, 'mfoo', 'drawn as its contents');
        $plain = Math::layout(Math::row('3', Math::frac('1', '2')), 18, false);
        $wrapped = Math::layout(Math::row('3', new Fallback(Math::frac('1', '2'), $note)), 18, false);

        self::assertEqualsWithDelta($plain->width, $wrapped->width, 1e-9);
    }

    /** @return list<float> x of each glyph in the display list, in order */
    private static function glyphXs(Node $n, float $size): array
    {
        $xs = [];
        foreach (Math::layout($n, $size, false)->items as $i) {
            if ($i instanceof Glyph) {
                $xs[] = $i->x;
            }
        }

        return $xs;
    }

    public function test_explicit_spacing_overrides_dictionary(): void
    {
        $c = new Context(Math::stixTwoMath(), 12);
        $plain = self::glyphXs(Math::row('a', new Operator('x', lspace: 0, rspace: 0), 'b'), 12);
        $spaced = self::glyphXs(Math::row('a', new Operator('x', lspace: 5, rspace: 5), 'b'), 12);
        self::assertCount(3, $spaced);
        self::assertEqualsWithDelta($c->mu(5), $spaced[1] - $plain[1], 0.01);
        self::assertEqualsWithDelta($c->mu(10), $spaced[2] - $plain[2], 0.01);
    }

    public function test_explicit_spacing_dropped_in_scripts(): void
    {
        $plain = Math::layout(Math::sup('y', Math::row('a', new Operator('x', lspace: 0, rspace: 0), 'b')), 12)->width;
        $spaced = Math::layout(Math::sup('y', Math::row('a', new Operator('x', lspace: 5, rspace: 5), 'b')), 12)->width;
        self::assertEqualsWithDelta($plain, $spaced, 0.001);
    }
}
