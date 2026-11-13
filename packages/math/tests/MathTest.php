<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use PHPUnit\Framework\TestCase;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\Render\SvgRenderer;
use Papercut\Math\Styles;
use Papercut\Math\Tokenizer;

final class MathTest extends TestCase
{
    public function test_math_constants_of_stix_two_math(): void
    {
        $m = Math::stixTwoMath()->math();
        self::assertSame(1000, Math::stixTwoMath()->unitsPerEm);
        self::assertSame(258, $m->constant('axisHeight'));
        self::assertSame(68, $m->constant('fractionRuleThickness'));
        self::assertSame(70, $m->constant('scriptPercentScaleDown'));
        self::assertSame(55, $m->constant('scriptScriptPercentScaleDown'));
        self::assertSame(55, $m->constant('radicalDegreeBottomRaisePercent'));
    }

    public function test_radical_variants_and_assembly(): void
    {
        $f = Math::stixTwoMath();
        $con = $f->math()->vertical($f->gid(0x221A));
        self::assertNotNull($con);
        self::assertGreaterThanOrEqual(3, count($con->variants));
        self::assertNotEmpty($con->parts);
        // Advance measurements grow with each variant.
        $adv = array_column($con->variants, 1);
        $sorted = $adv;
        sort($sorted);
        self::assertSame($sorted, $adv);
    }

    public function test_cmap_and_italic_mapping(): void
    {
        $f = Math::stixTwoMath();
        self::assertGreaterThan(0, $f->gid(0x1D465)); // italic x
        self::assertSame(0x1D465, Styles::italic(ord('x')));
        self::assertSame(0x210E, Styles::italic(ord('h')));
        self::assertSame(0x1D434, Styles::italic(ord('A')));
        self::assertGreaterThan(0, $f->gid(0x210E));
    }

    public function test_tokenizer_classifications(): void
    {
        $t = Tokenizer::tokenize('3.14x + 2 = y');
        self::assertInstanceOf(Number::class, $t[0]);
        self::assertSame('3.14', $t[0]->text);
        self::assertInstanceOf(Identifier::class, $t[1]);
        self::assertInstanceOf(Operator::class, $t[2]);
        self::assertSame('+', $t[2]->char);
        self::assertCount(6, $t); // whitespace dropped

        $minus = Tokenizer::tokenize('a-b')[1];
        self::assertInstanceOf(Operator::class, $minus);
        self::assertSame('−', $minus->char);
    }

    public function test_unicode_script_digits_stay_attached(): void
    {
        $t = Tokenizer::tokenize('b² + x₂');
        self::assertSame(['b', '²', '+', 'x', '₂'], array_map(fn ($n) => $n instanceof Identifier ? $n->name : ($n instanceof Number ? $n->text : ($n instanceof Operator ? $n->char : '')), $t));
        self::assertInstanceOf(Number::class, $t[1]);
        // Drawn as ordinary characters: glyph text is the source character.
        $box = Math::layout(new Row([$t[0], $t[1]]), 12);
        $glyph = $box->items[1];
        self::assertInstanceOf(Glyph::class, $glyph);
        self::assertSame('²', $glyph->text);
    }

    public function test_prefix_minus_has_no_spacing(): void
    {
        $c = new Context(Math::stixTwoMath(), 10);
        $m = new Operator('−');
        $m2 = new Operator('−');
        $w1 = $m->layoutAs($c, 'prefix')->width;
        $w2 = $m2->layoutAs($c, 'infix')->width;
        self::assertEqualsWithDelta(2 * 4 / 18 * 10, $w2 - $w1, 1e-6);
    }

    public function test_fraction_width_is_wider_part_plus_padding(): void
    {
        $c = new Context(Math::stixTwoMath(), 20, 0, true);
        $frac = Math::frac('abc', 'd');
        $fc = $c->fractionChild();
        $numW = Math::row('abc')->layout($fc)->width;
        $denW = Math::row('d')->layout($fc)->width;
        $box = $frac->layout($c);
        self::assertEqualsWithDelta(max($numW, $denW) + 2 * Fraction::padding($c), $box->width, 1e-6);
        $rules = array_filter($box->items, fn ($i) => $i instanceof Rule);
        self::assertCount(1, $rules);
        self::assertEqualsWithDelta(max($numW, $denW), array_values($rules)[0]->w, 1e-6);
    }

    public function test_inline_fraction_is_smaller_than_display(): void
    {
        $d = Math::layout(Math::frac('a', 'b'), 12, true);
        $i = Math::layout(Math::frac('a', 'b'), 12, false);
        self::assertLessThan($d->height(), $i->height());
        $sizes = array_map(fn ($g) => $g->size, array_filter($i->items, fn ($x) => $x instanceof Glyph));
        self::assertEqualsWithDelta(8.4, max([0.0, ...$sizes]), 1e-6); // 70% script scale of STIX Two Math
    }

    public function test_superscript_is_raised_and_smaller(): void
    {
        $box = Math::layout(Math::sup('x', '2'), 20);
        $g = array_values(array_filter($box->items, fn ($i) => $i instanceof Glyph));
        self::assertCount(2, $g);
        self::assertSame(20.0, $g[0]->size);
        self::assertEqualsWithDelta(14.0, $g[1]->size, 1e-6);
        self::assertLessThan(0, $g[1]->y); // above the baseline (y down)
        self::assertGreaterThan($g[0]->x, $g[1]->x);
    }

    public function test_stretchy_fence_grows_and_uses_assembly(): void
    {
        $small = Math::layout(Math::fenced('(', ')', 'x'), 20);
        $tall = Math::layout(Math::fenced('(', ')', Math::frac('1', Math::frac('1', Math::frac('1', 'x')))), 20);
        self::assertGreaterThan($small->height(), $tall->height());
        $veryTall = Math::layout(Math::fenced('[', ']', Math::frac(Math::frac('1', 'x'), Math::frac('1', Math::frac('1', 'y')))), 20);
        $nullText = array_filter($veryTall->items, fn ($i) => $i instanceof Glyph && $i->text === null);
        self::assertGreaterThan(2, count($nullText), 'assembly pieces carry no text');
    }

    public function test_svg_output(): void
    {
        $box = Math::layout(Math::row('x =', Math::frac('a', 'b')), 12);
        $svg = (new SvgRenderer(Math::stixTwoMath()))->render($box, 1);
        self::assertStringStartsWith('<svg ', $svg);
        self::assertStringContainsString('<rect', $svg);
        self::assertNotFalse(simplexml_load_string($svg));
    }
}
