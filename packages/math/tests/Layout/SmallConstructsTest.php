<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use Papercut\Math\Node\Forms;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\Render\TextWriter;
use Papercut\Math\Styles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmallConstructsTest extends TestCase
{
    public function test_space_width(): void
    {
        $box = Math::layout(Math::space(0.5), 12.0);
        self::assertEqualsWithDelta(6.0, $box->width, 1e-9);
        self::assertSame(0.0, $box->ascent);
        self::assertSame(0.0, $box->descent);
        self::assertSame([], $box->items);
    }

    public function test_negative_space_pulls_back(): void
    {
        $plain = Math::layout(Math::row('a', Math::space(0), 'b'), 12.0)->width;
        $pulled = Math::layout(Math::row('a', Math::space(-0.2), 'b'), 12.0)->width;
        self::assertEqualsWithDelta(2.4, $plain - $pulled, 1e-9);
    }

    public function test_phantom_has_metrics_but_no_items(): void
    {
        $real = Math::layout(Math::frac('a', 'b'), 12.0);
        $ghost = Math::layout(Math::phantom(Math::frac('a', 'b')), 12.0);
        self::assertSame([], $ghost->items);
        self::assertEqualsWithDelta($real->width, $ghost->width, 1e-9);
        self::assertEqualsWithDelta($real->ascent, $ghost->ascent, 1e-9);
        self::assertEqualsWithDelta($real->descent, $ghost->descent, 1e-9);
    }

    public function test_phantom_children(): void
    {
        $p = Math::phantom('x');
        self::assertSame([$p->content], $p->children());
        self::assertSame([], Math::space(1)->children());
    }

    public function test_barless_fraction_has_no_rule(): void
    {
        $bar = Math::layout(Math::frac('a', 'b'), 12.0);
        $none = Math::layout(Math::stack('a', 'b'), 12.0);
        self::assertNotEmpty(array_filter($bar->items, fn ($i) => $i instanceof Rule));
        self::assertEmpty(array_filter($none->items, fn ($i) => $i instanceof Rule));
        self::assertGreaterThan(0.0, $none->ascent);
        self::assertGreaterThan(0.0, $none->descent);
    }

    public function test_binomial_is_fenced_barless_fraction(): void
    {
        $b = Math::binom('n', 'k');
        self::assertSame('(', $b->open);
        self::assertSame(')', $b->close);
        self::assertEmpty(array_filter(Math::layout($b, 12.0)->items, fn ($i) => $i instanceof Rule));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function variants(): iterable
    {
        yield 'double-struck R' => ['R', 'double-struck', 'ℝ'];
        yield 'bold x' => ['x', 'bold', '𝐱'];
        yield 'script B' => ['B', 'script', 'ℬ'];
        yield 'italic h' => ['h', 'italic', 'ℎ'];
        yield 'double-struck 1' => ['1', 'double-struck', '𝟙'];
        yield 'bold alpha' => ['α', 'bold', '𝛂'];
        yield 'bold plus' => ['+', 'bold', '+'];
        yield 'fraktur C' => ['C', 'fraktur', 'ℭ'];
        yield 'script e' => ['e', 'script', 'ℯ'];
        yield 'monospace a' => ['a', 'monospace', '𝚊'];
        yield 'bold epsilon symbol' => ['ϵ', 'bold', '𝛜'];
        yield 'italic partial' => ['∂', 'italic', '𝜕'];
        yield 'bold nabla' => ['∇', 'bold', '𝛁'];
        yield 'sans bold phi symbol' => ['ϕ', 'bold-sans-serif', "\u{1D78D}"];
        yield 'script greek unchanged' => ['α', 'script', 'α'];
        yield 'normal' => ['a', 'normal', 'a'];
        yield 'sans-serif bold-italic Z' => ['Z', 'sans-serif-bold-italic', '𝙕'];
    }

    #[DataProvider('variants')]
    public function test_styles_variant(string $in, string $variant, string $out): void
    {
        self::assertSame(mb_ord($out), Styles::variant(mb_ord($in), $variant));
    }

    public function test_styled_builds_an_upright_identifier(): void
    {
        $id = Math::styled('R', 'double-struck');
        self::assertInstanceOf(\Papercut\Math\Node\Identifier::class, $id);
        self::assertSame('ℝ', $id->name);
        self::assertTrue($id->upright);
    }

    public function test_styled_rejects_empty_text(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Math::styled('', 'bold');
    }

    public function test_space_does_not_make_a_minus_infix(): void
    {
        $row = Math::row(Math::space(0.5), '−', '3');
        self::assertInstanceOf(Row::class, $row);
        self::assertSame([null, 'prefix', null], $row->forms());
        $tight = Math::row('−', '3');
        $spaced = Math::layout($row, 12.0)->width - Math::layout(Math::space(0.5), 12.0)->width;
        self::assertEqualsWithDelta(Math::layout($tight, 12.0)->width, $spaced, 1e-9);
        self::assertSame('−3', TextWriter::write($row));
        self::assertSame([null, 'postfix', null], Forms::of([new Identifier('a'), new Operator('!'), Math::space(1)]));
        self::assertSame([null, 'prefix', null], Forms::of([Math::phantom(Math::space(1)), new Operator('−'), new Number('3')]));
    }

    public function test_space_beside_a_fraction_adds_no_automatic_gap(): void
    {
        $with = Math::layout(Math::row('3', Math::space(0), Math::frac('1', '2')), 18.0, false)->width;
        $frac = Math::layout(Math::frac('1', '2'), 18.0, false)->width;
        $three = Math::layout(Math::row('3'), 18.0, false)->width;
        self::assertEqualsWithDelta($three + $frac, $with, 1e-9);
    }

    public function test_stacked_box_uses_the_stack_constants(): void
    {
        $font = Math::stixTwoMath();
        $u = 12.0 / $font->unitsPerEm;
        $k = fn (string $n) => $font->math()->constant($n) * $u;
        $box = Math::layout(Math::stack('a', 'b'), 12.0);
        $num = Math::layout(Math::row('a'), 12.0);
        $den = Math::layout(Math::row('b'), 12.0);
        $up = $k('stackTopDisplayStyleShiftUp');
        $down = $k('stackBottomDisplayStyleShiftDown');
        $short = $k('stackDisplayStyleGapMin') - (($up - $num->descent) + ($down - $den->ascent));
        if ($short > 0) {
            $up += $short / 2;
            $down += $short / 2;
        }
        self::assertEqualsWithDelta($up + $num->ascent, $box->ascent, 1e-6);
        self::assertEqualsWithDelta($down + $den->descent, $box->descent, 1e-6);
        $barred = Math::layout(Math::frac('a', 'b'), 12.0);
        self::assertNotEqualsWithDelta($barred->ascent, $box->ascent, 1e-6);
    }

    public function test_styled_rejects_unknown_variant(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Math::styled('R', 'wobbly');
    }
}
