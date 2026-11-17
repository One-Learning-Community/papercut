<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Node\DisplayStyle;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Middle;
use Papercut\Math\Node\Row;
use Papercut\Math\NoteLevel;
use PHPUnit\Framework\TestCase;

final class MiddleTest extends TestCase
{
    private const SIZE = 12.0;

    private static function height(Box $b): float
    {
        return $b->ascent + $b->descent;
    }

    private static function barGid(): int
    {
        return Math::stixTwoMath()->gid(0x7C);
    }

    private static function barCount(Box $b): int
    {
        return count(array_filter($b->items, static fn ($i): bool => $i instanceof Glyph && $i->gid === self::barGid()));
    }

    /** Ink height in pt of the glyph pieces (those standing for part of a larger symbol) whose x passes $inFilter. */
    private static function ink(Box $b, \Closure $inFilter): float
    {
        $f = Math::stixTwoMath();
        $u = self::SIZE / $f->unitsPerEm;
        $top = -INF;
        $bottom = INF;
        foreach ($b->items as $i) {
            if ($i instanceof Glyph && $i->text === null && $inFilter($i->x)) {
                $bb = $f->bbox($i->gid);
                $top = max($top, -$i->y + $bb[3] * $u);
                $bottom = min($bottom, -$i->y + $bb[1] * $u);
            }
        }

        return $top - $bottom;
    }

    public function test_middle_stretches_to_fence(): void
    {
        $fence = Math::layout(Math::fenced('{', '}', 'x', Math::middle('|'), Math::frac('a', 'b')), self::SIZE);
        $minX = INF;
        $maxX = -INF;
        foreach ($fence->items as $i) {
            if ($i instanceof Glyph && $i->text === null) {
                $minX = min($minX, $i->x);
                $maxX = max($maxX, $i->x);
            }
        }
        $brace = self::ink($fence, static fn (float $x): bool => $x === $minX);
        $bar = self::ink($fence, static fn (float $x): bool => $x > $minX && $x < $maxX);
        self::assertGreaterThan(0.0, $brace);
        self::assertGreaterThanOrEqual(0.9 * $brace, $bar);
        $mid = (new Middle('|', self::height($fence)))->layout(new Context(Math::stixTwoMath(), self::SIZE));
        // Centred on the math axis, like the fence itself.
        $axis = Math::stixTwoMath()->math()->constant('axisHeight') / Math::stixTwoMath()->unitsPerEm * self::SIZE;
        self::assertEqualsWithDelta($axis, ($mid->ascent - $mid->descent) / 2, 0.05);
    }

    public function test_a_wrapped_middle_still_stretches_and_keeps_its_wrapper(): void
    {
        $note = new MathNote(NoteLevel::Approximated, 'x', 'p');
        $plain = Math::layout(Math::fenced('{', '}', 'x', Math::middle('|'), Math::frac('a', 'b')), self::SIZE);
        $wrappers = [
            'fallback' => new Fallback(new Middle('|'), $note),
            'display style' => new DisplayStyle(new Middle('|'), false),
            'fallback in display style' => new DisplayStyle(new Fallback(new Middle('|'), $note), true),
        ];
        foreach ($wrappers as $name => $middle) {
            $fenced = new Fenced('{', '}', new Row([Math::row('x'), $middle, Math::frac('a', 'b')]));
            self::assertEqualsWithDelta(self::height($plain), self::height($fenced->layout(new Context(Math::stixTwoMath(), self::SIZE))), 1e-6, $name);
            $out = Fenced::stretchMiddles($fenced->content, 5.0);
            self::assertInstanceOf(Row::class, $out);
            self::assertSame($middle::class, $out->children[1]::class, $name);
            $inner = Fallback::unwrap($out->children[1]);
            self::assertInstanceOf(Middle::class, $inner);
            self::assertTrue($inner->stretches(), $name);
            self::assertSame($middle instanceof Fallback ? $note : null, $out->children[1] instanceof Fallback ? $out->children[1]->note : null, $name);
        }
        // The wrapper around the whole Row survives too.
        $wrapped = Fenced::stretchMiddles(new Fallback(new Row([new Middle('|')]), $note), 5.0);
        self::assertInstanceOf(Fallback::class, $wrapped);
        self::assertSame($note, $wrapped->note);
        $wrapped = Fenced::stretchMiddles(new DisplayStyle(new Row([new Middle('|')]), false), 5.0);
        self::assertInstanceOf(DisplayStyle::class, $wrapped);
        self::assertFalse($wrapped->display);
    }

    public function test_a_fraction_beside_the_bar_adds_no_extra_gap(): void
    {
        $f = Math::stixTwoMath();
        $adv = $f->advance(self::barGid()) / $f->unitsPerEm * self::SIZE;
        $bar = $adv + 2 * 3 / 18 * self::SIZE;
        $frac = Math::layout(Math::frac('a', 'b'), self::SIZE)->width;
        $x = Math::layout(Math::row('x'), self::SIZE)->width;
        self::assertEqualsWithDelta($x + $bar + $frac, Math::layout(Math::row('x', Math::middle('|'), Math::frac('a', 'b')), self::SIZE)->width, 1e-6);
        self::assertEqualsWithDelta($frac + $bar + $x, Math::layout(Math::row(Math::frac('a', 'b'), Math::middle('|'), 'x'), self::SIZE)->width, 1e-6);
    }

    public function test_invalid_utf8_is_cleaned_and_noted(): void
    {
        $node = Math::middle("\xFF");
        self::assertInstanceOf(Fallback::class, $node);
        self::assertCount(1, Math::notes($node));
        Math::layout(Math::fenced('(', ')', 'x', $node, 'y'), self::SIZE);
        self::assertSame([], Math::notes(Math::middle('|')));
    }

    public function test_middle_outside_fence_is_plain(): void
    {
        $b = Math::layout(Math::row('a', Math::middle('|'), 'b'), self::SIZE);
        self::assertSame(1, self::barCount($b));
        $f = Math::stixTwoMath();
        $bb = $f->bbox(self::barGid());
        $plain = Math::layout(Math::middle('|'), self::SIZE);
        self::assertEqualsWithDelta($bb[3] / $f->unitsPerEm * self::SIZE, $plain->ascent, 0.01);
    }

    public function test_three_mu_each_side_and_none_when_tight(): void
    {
        $f = Math::stixTwoMath();
        $adv = $f->advance(self::barGid()) / $f->unitsPerEm * self::SIZE;
        $mid = new Middle('|');
        $spaced = $mid->layout(new Context($f, self::SIZE));
        self::assertEqualsWithDelta($adv + 2 * 3 / 18 * self::SIZE, $spaced->width, 1e-6);
        self::assertEqualsWithDelta(3 / 18 * self::SIZE, $spaced->trail, 1e-9);
        $tight = $mid->layout(new Context($f, self::SIZE, 0, true, true));
        self::assertEqualsWithDelta($adv, $tight->width, 1e-6);
        self::assertEqualsWithDelta(0.0, $tight->trail, 1e-9);
        $stretched = (new Middle('|', 40.0))->layout(new Context($f, self::SIZE));
        self::assertEqualsWithDelta(3 / 18 * self::SIZE, $stretched->trail, 1e-9);
    }
}
