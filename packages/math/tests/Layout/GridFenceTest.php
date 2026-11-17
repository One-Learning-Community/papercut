<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Layout;

use Quoin\Math\Math;
use Quoin\Math\Node\{Fallback, Fenced, Grid, Node, Row};
use PHPUnit\Framework\TestCase;

final class GridFenceTest extends TestCase
{
    private const SIZE = 12.0;

    private static function width(Node $n): float
    {
        return Math::layout($n, self::SIZE, true)->width;
    }

    private static function grid(): Grid
    {
        return new Grid([[Math::row('a'), Math::row('b')], [Math::row('c'), Math::row('d')]], ['left']);
    }

    /** A Row around the grid hides it from the gap rule, so the width difference is the gap alone. */
    public function test_cases_leave_three_mu_after_the_brace(): void
    {
        $with = self::width(new Fenced('{', '', self::grid()));
        $without = self::width(new Fenced('{', '', new Row([self::grid()])));
        $this->assertEqualsWithDelta(3 / 18 * self::SIZE, $with - $without, 1e-9);
        $this->assertEqualsWithDelta(3 / 18 * self::SIZE, self::width(Math::cases([['a', 'b'], ['c', 'd']])) - $without, 1e-9);
    }

    public function test_the_gap_also_applies_through_a_fallback(): void
    {
        $note = new \Quoin\Math\MathNote(\Quoin\Math\NoteLevel::Approximated, 'x', 'p');
        $with = self::width(new Fenced('{', '', new Fallback(self::grid(), $note)));
        $without = self::width(new Fenced('{', '', new Row([self::grid()])));
        $this->assertEqualsWithDelta(3 / 18 * self::SIZE, $with - $without, 1e-9);
    }

    public function test_no_gap_for_other_content_or_fences(): void
    {
        $g = self::grid();
        $wrapped = static fn (string $o, string $c): float => self::width(new Fenced($o, $c, new Row([$g])));
        $this->assertEqualsWithDelta($wrapped('{', '}'), self::width(new Fenced('{', '}', $g)), 1e-9);
        $this->assertEqualsWithDelta($wrapped('(', ''), self::width(new Fenced('(', '', $g)), 1e-9);
        $x = Math::row('x');
        $this->assertEqualsWithDelta(self::width(new Fenced('{', '', new Row([$x]))), self::width(new Fenced('{', '', $x)), 1e-9);
    }
}
