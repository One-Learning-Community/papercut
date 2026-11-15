<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\Node\UnderOver;
use PHPUnit\Framework\TestCase;

final class UnderOverTest extends TestCase
{
    private const SIZE = 12.0;

    /** @return list<Glyph> */
    private static function glyphs(Box $b): array
    {
        return array_values(array_filter($b->items, fn ($i) => $i instanceof Glyph));
    }

    /** Ink top and bottom of a glyph, y down, in points.
     *
     * @return array{float, float}
     */
    private static function inkY(Glyph $g): array
    {
        $bb = Math::stixTwoMath()->bbox($g->gid);
        $u = $g->size / Math::stixTwoMath()->unitsPerEm;

        return [$g->y - $bb[3] * $u, $g->y - $bb[1] * $u];
    }

    /** Ink left and right of a glyph, in points.
     *
     * @return array{float, float}
     */
    private static function inkX(Glyph $g): array
    {
        $bb = Math::stixTwoMath()->bbox($g->gid);
        $u = $g->size / Math::stixTwoMath()->unitsPerEm;

        return [$g->x + $bb[0] * $u, $g->x + $bb[2] * $u];
    }

    private static function k(string $name): float
    {
        return Math::stixTwoMath()->math()->constant($name) * self::SIZE / Math::stixTwoMath()->unitsPerEm;
    }

    public function test_display_sum_uses_display_variant(): void
    {
        $display = Math::layout(Math::sum(), self::SIZE, true);
        $inline = Math::layout(Math::sum(), self::SIZE, false);

        self::assertGreaterThan($inline->height(), $display->height());
        $g = self::glyphs($display)[0];
        // STIX's biggest sum is shorter than displayOperatorMinHeight, so the largest variant is the one drawn.
        $variants = (Math::stixTwoMath()->math()->vertical(Math::stixTwoMath()->gid(0x2211)) ?? self::fail('no construction'))->variants;
        self::assertSame($variants[count($variants) - 1][0], $g->gid);
        self::assertNotSame(Math::stixTwoMath()->gid(0x2211), $g->gid);
        self::assertSame('∑', $g->text);
        // centred on the math axis
        [$top, $bottom] = self::inkY($g);
        self::assertEqualsWithDelta(-self::k('axisHeight'), ($top + $bottom) / 2, 0.01);
    }

    public function test_display_limits_above_and_below(): void
    {
        $box = Math::layout(Math::sum('i', 'n'), self::SIZE, true);
        $bigGid = self::glyphs(Math::layout(Math::sum(), self::SIZE, true))[0]->gid;
        $sigma = null;
        $above = $below = null;
        foreach (self::glyphs($box) as $g) {
            if ($g->gid === $bigGid) {
                $sigma = $g;
            } elseif ($g->y < 0) {
                $above = $g;
            } else {
                $below = $g;
            }
        }
        self::assertNotNull($sigma);
        self::assertNotNull($above);
        self::assertNotNull($below);
        [$sigmaTop, $sigmaBottom] = self::inkY($sigma);
        self::assertGreaterThanOrEqual(self::k('upperLimitGapMin') - 0.01, $sigmaTop - self::inkY($above)[1]);
        self::assertGreaterThanOrEqual(self::k('lowerLimitGapMin') - 0.01, self::inkY($below)[0] - $sigmaBottom);
        // both limits are centred over the sigma
        [$sl, $sr] = self::inkX($sigma);
        [$al, $ar] = self::inkX($above);
        self::assertEqualsWithDelta(($sl + $sr) / 2, ($al + $ar) / 2, 1.0);
    }

    public function test_inline_sum_puts_limits_as_scripts(): void
    {
        $a = Math::layout(Math::sum('i=1', 'n'), self::SIZE, false);
        $b = Math::layout(Math::subSup('∑', 'i=1', 'n'), self::SIZE, false);

        self::assertEqualsWithDelta($b->width, $a->width, 1e-9);
        self::assertEqualsWithDelta($b->ascent, $a->ascent, 1e-9);
        self::assertEqualsWithDelta($b->descent, $a->descent, 1e-9);
        self::assertEquals($b->items, $a->items);
    }

    public function test_integral_keeps_side_scripts_in_display(): void
    {
        $box = Math::layout(Math::integral('0', '1'), self::SIZE, true);
        $glyphs = self::glyphs($box);
        $integral = $glyphs[0];

        self::assertSame('∫', $integral->text);
        foreach (array_slice($glyphs, 1) as $g) {
            self::assertGreaterThan($integral->x, $g->x, 'limit sits beside the integral');
        }
    }

    public function test_limit_operator_flags_override_dictionary(): void
    {
        $stackedInline = new UnderOver(new Operator('∑', false, null, false), Math::row('i'), Math::row('n'));
        $box = Math::layout($stackedInline, self::SIZE, false);
        $scripts = Math::layout(Math::sum('i', 'n'), self::SIZE, false);

        // movablelimits false keeps the limits above and below even inline; the dictionary default makes them scripts
        self::assertGreaterThan($scripts->height(), $box->height());
        $sigmaGid = Math::stixTwoMath()->gid(0x2211);
        $sigma = null;
        $above = null;
        foreach (self::glyphs($box) as $g) {
            if ($g->gid === $sigmaGid) {
                $sigma = $g;
            } elseif ($g->y < 0) {
                $above = $g;
            }
        }
        self::assertNotNull($sigma);
        self::assertNotNull($above);
        self::assertLessThan(self::inkY($sigma)[0], self::inkY($above)[1], 'the over limit sits above the sum');
        self::assertTrue((new Operator('∑'))->isLargeop('prefix'));
        self::assertTrue((new Operator('∑'))->hasMovableLimits('prefix'));
        self::assertFalse((new Operator('∫'))->hasMovableLimits('prefix'));
    }

    public function test_display_integral_picks_first_variant_at_least_min_height(): void
    {
        $f = Math::stixTwoMath();
        $g = self::glyphs(Math::layout(Math::row('∫'), self::SIZE, true))[0];
        $construction = $f->math()->vertical($f->gid(0x222B)) ?? self::fail('no construction');
        $min = $f->math()->constant('displayOperatorMinHeight');
        $expected = null;
        foreach ($construction->variants as [$vg, $size]) {
            if ($size >= $min) {
                $expected = $vg;
                break;
            }
        }
        self::assertNotNull($expected);
        self::assertSame($expected, $g->gid);
        self::assertSame('∫', $g->text);
    }

    public function test_hat_sits_on_x(): void
    {
        $x = Math::layout(Math::row('x'), self::SIZE);
        $box = Math::layout(Math::hat('x'), self::SIZE);
        [$base, $hat] = self::glyphs($box);

        self::assertGreaterThan($x->ascent, $box->ascent);
        [$hatTop, $hatBottom] = self::inkY($hat);
        [$baseTop] = self::inkY($base);
        self::assertLessThan($baseTop, $hatBottom, 'accent is above the letter');
        self::assertLessThan($baseTop, $hatTop);
        // the accent's attachment point sits on the letter's attachment point
        $m = Math::stixTwoMath()->math();
        $u = self::SIZE / Math::stixTwoMath()->unitsPerEm;
        $baseAttach = $base->x + $m->topAccentAttachment($base->gid) * $u;
        [$hl, $hr] = self::inkX($hat);
        $hatAttach = $m->topAccentAttachment($hat->gid) !== null ? $hat->x + $m->topAccentAttachment($hat->gid) * $u : ($hl + $hr) / 2;
        self::assertEqualsWithDelta($baseAttach, $hatAttach, 0.5);
    }

    public function test_wide_hat_stretches(): void
    {
        $narrow = self::glyphs(Math::layout(Math::hat('x'), self::SIZE))[1];
        $wide = self::glyphs(Math::layout(Math::hat('xyz'), self::SIZE))[3];

        [$nl, $nr] = self::inkX($narrow);
        [$wl, $wr] = self::inkX($wide);
        self::assertGreaterThan($nr - $nl, $wr - $wl);
    }

    public function test_overline_is_a_rule_as_wide_as_base(): void
    {
        $base = Math::layout(Math::row('xy'), self::SIZE);
        $box = Math::layout(Math::overline('xy'), self::SIZE);
        $rules = array_values(array_filter($box->items, fn ($i) => $i instanceof Rule));

        self::assertCount(1, $rules);
        self::assertEqualsWithDelta($base->width, $rules[0]->w, 1e-9);
        self::assertEqualsWithDelta(self::k('overbarRuleThickness'), $rules[0]->h, 1e-9);
        self::assertEqualsWithDelta(-($base->ascent + self::k('overbarVerticalGap') + $rules[0]->h), $rules[0]->y, 1e-9);
        self::assertEqualsWithDelta($base->ascent + self::k('overbarVerticalGap') + $rules[0]->h + self::k('overbarExtraAscender'), $box->ascent, 1e-9);
        self::assertCount(2, self::glyphs($box));
    }

    public function test_underline_is_a_rule_below_the_base(): void
    {
        $base = Math::layout(Math::row('y'), self::SIZE);
        $box = Math::layout(Math::underline('y'), self::SIZE);
        $rules = array_values(array_filter($box->items, fn ($i) => $i instanceof Rule));

        self::assertCount(1, $rules);
        self::assertEqualsWithDelta($base->descent + self::k('underbarVerticalGap'), $rules[0]->y, 1e-9);
        self::assertGreaterThan($base->descent, $box->descent);
    }

    public function test_vec_arrow_stretches_over_ab(): void
    {
        $one = self::glyphs(Math::layout(Math::vec('a'), self::SIZE))[1];
        $two = self::glyphs(Math::layout(Math::vec('ab'), self::SIZE));
        $arrow = $two[2];
        $ab = Math::layout(Math::row('ab'), self::SIZE);

        [$l1, $r1] = self::inkX($one);
        [$l2, $r2] = self::inkX($arrow);
        self::assertGreaterThan($r1 - $l1, $r2 - $l2);
        self::assertGreaterThan($ab->width * 0.8, $r2 - $l2);
    }

    public function test_overbrace_with_label(): void
    {
        $base = Math::layout(Math::row('a + b + c'), self::SIZE);
        $box = Math::layout(Math::overbrace('a + b + c', 'n'), self::SIZE);
        $plain = Math::layout(Math::overbrace('a + b + c'), self::SIZE);

        $label = array_values(array_filter(self::glyphs($box), fn (Glyph $g) => $g->text === '𝑛'));
        self::assertCount(1, $label);
        $braceGlyphs = array_filter(self::glyphs($plain), fn (Glyph $g) => self::inkY($g)[1] < -$base->ascent);
        self::assertNotEmpty($braceGlyphs);
        $braceTop = min([...array_map(fn (Glyph $g) => self::inkY($g)[0], $braceGlyphs), INF]);
        self::assertLessThan($braceTop, self::inkY($label[0])[1], 'label sits above the brace');
        $braceLeft = min([...array_map(fn (Glyph $g) => self::inkX($g)[0], $braceGlyphs), INF]);
        $braceRight = max([...array_map(fn (Glyph $g) => self::inkX($g)[1], $braceGlyphs), -INF]);
        self::assertGreaterThanOrEqual($base->width - 0.5, $braceRight - $braceLeft);
    }

    public function test_both_parts_null_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UnderOver(Math::row('x'), null, null);
    }

    public function test_children_are_base_under_over(): void
    {
        $x = Math::row('x');
        $under = Math::row('i');
        $over = Math::row('n');

        self::assertSame([$x, $under, $over], (new UnderOver($x, $under, $over))->children());
        self::assertSame([$x, $over], (new UnderOver($x, null, $over))->children());
    }

    public function test_big_op_without_limits_is_the_operator(): void
    {
        self::assertInstanceOf(Operator::class, Math::bigOp('⋃'));
        self::assertInstanceOf(Operator::class, Math::sum());
    }

    public function test_accent_that_is_not_an_operator_stays_at_base_size(): void
    {
        $box = Math::layout(new UnderOver(Math::row('x'), null, new Identifier('o', false), true), self::SIZE);
        $base = Math::layout(Math::row('x'), self::SIZE);
        $mark = self::glyphs($box)[1];

        self::assertSame(self::SIZE, $mark->size);
        // accent position: raised by ascent - min(ascent, accentBaseHeight)
        self::assertEqualsWithDelta(-($base->ascent - min($base->ascent, self::k('accentBaseHeight'))), $mark->y, 1e-9);
        // centred over the base
        [$ml, $mr] = self::inkX($mark);
        [$bl, $br] = self::inkX(self::glyphs($box)[0]);
        self::assertEqualsWithDelta(($bl + $br) / 2, ($ml + $mr) / 2, 1.0);
    }

    public function test_accent_under_that_is_not_an_operator_stays_at_base_size(): void
    {
        $box = Math::layout(new UnderOver(Math::row('x'), new Identifier('o', false), null, false, true), self::SIZE);

        self::assertSame(self::SIZE, self::glyphs($box)[1]->size);
    }

    public function test_arrow_base_with_labels_uses_stretch_stack_shifts(): void
    {
        $over = self::glyphs(Math::layout(Math::over('→', 'f'), self::SIZE))[1];
        $under = self::glyphs(Math::layout(Math::under('→', 'f'), self::SIZE))[1];

        self::assertEqualsWithDelta(-self::k('stretchStackTopShiftUp'), $over->y, 1e-9);
        self::assertEqualsWithDelta(self::k('stretchStackBottomShiftDown'), $under->y, 1e-9);
    }

    public function test_limits_of_integral_in_under_over_are_offset_by_italic_correction(): void
    {
        $f = Math::stixTwoMath();
        $box = Math::layout(new UnderOver(new Operator('∫'), Math::row('a'), Math::row('a')), self::SIZE, true);
        [$integral, $a1, $a2] = self::glyphs($box);
        $over = $a1->y < 0 ? $a1 : $a2;
        $under = $a1->y < 0 ? $a2 : $a1;
        $italic = $f->math()->italicsCorrection($integral->gid) * self::SIZE / $f->unitsPerEm;

        self::assertGreaterThan(0.0, $italic);
        self::assertEqualsWithDelta($italic, $over->x - $under->x, 1e-6);
    }

    public function test_accent_under_sits_below_the_base(): void
    {
        $base = Math::layout(Math::row('x'), self::SIZE);
        $box = Math::layout(new UnderOver(Math::row('x'), new Operator("\u{0332}"), null, false, true), self::SIZE);
        $mark = self::glyphs($box)[1];

        self::assertEqualsWithDelta($base->descent + self::k('underbarVerticalGap'), self::inkY($mark)[0], 1e-6);
    }

    public function test_underbrace_with_label(): void
    {
        $plain = self::glyphs(Math::layout(Math::underbrace('a + b'), self::SIZE));
        $box = Math::layout(Math::underbrace('a + b', Math::text('n')), self::SIZE);
        $label = array_values(array_filter(self::glyphs($box), fn (Glyph $g) => $g->text === 'n'));
        self::assertCount(1, $label);
        $braces = array_filter($plain, fn (Glyph $g) => $g->y > 0);
        self::assertNotEmpty($braces);
        $braceBottom = max([...array_map(fn (Glyph $g) => self::inkY($g)[1], $braces), -INF]);

        self::assertGreaterThan($braceBottom, self::inkY($label[0])[0], 'label sits below the brace');
    }

    public function test_plain_over_and_under_add_the_extra_ascender_and_descender(): void
    {
        $over = Math::layout(Math::over('x', 'y'), self::SIZE);
        $mark = self::glyphs($over)[1];
        self::assertEqualsWithDelta(-self::inkY($mark)[0] + self::k('overbarExtraAscender'), $over->ascent, 1e-9);

        $under = Math::layout(Math::under('x', 'y'), self::SIZE);
        $mark = self::glyphs($under)[1];
        self::assertEqualsWithDelta(self::inkY($mark)[1] + self::k('underbarExtraDescender'), $under->descent, 1e-9);
    }

    public function test_accent_stretches_to_the_widest_non_stretchy_part(): void
    {
        $narrow = self::glyphs(Math::layout(Math::hat('x'), self::SIZE))[1];
        $wideUnder = self::glyphs(Math::layout(new UnderOver(Math::row('x'), Math::row('abcdef'), new Operator("\u{0302}"), true), self::SIZE));
        $hat = $wideUnder[1];
        self::assertSame(self::SIZE, $hat->size);
        [$nl, $nr] = self::inkX($narrow);
        [$hl, $hr] = self::inkX($hat);

        self::assertGreaterThan($nr - $nl, $hr - $hl);
    }

    public function test_explicit_false_stretchy_keeps_an_arrow_unstretched(): void
    {
        $f = Math::stixTwoMath();
        $arrow = $f->gid(0x2192);
        $stretchy = self::glyphs(Math::layout(new UnderOver(Math::row('abcdefgh'), null, new Operator('→')), self::SIZE));
        $fixed = self::glyphs(Math::layout(new UnderOver(Math::row('abcdefgh'), null, new Operator('→', false)), self::SIZE));

        self::assertNotContains($arrow, array_map(fn (Glyph $g) => $g->gid, array_slice($stretchy, 8)));
        $last = $fixed[count($fixed) - 1];
        self::assertLessThan(self::SIZE, $last->size, 'laid out as an ordinary script-size operator');
        self::assertNull((new Operator('→'))->stretchy);
    }

    public function test_lim_like_base_puts_limit_under_as_script_inline(): void
    {
        $a = Math::layout(Math::under('lim', 'x→0'), self::SIZE, false);
        $b = Math::layout(Math::sub('lim', 'x→0'), self::SIZE, false);

        self::assertEqualsWithDelta($b->width, $a->width, 1e-9);
        self::assertEquals($b->items, $a->items);
        foreach (['max', 'min', 'sup', 'inf', 'det', 'gcd', 'Pr', 'liminf', 'limsup'] as $name) {
            $node = new UnderOver(new Row([new Identifier($name, false)]), Math::row('i'), null);
            self::assertEquals(Math::layout(new Scripts(new Identifier($name, false), Math::row('i'), null), self::SIZE, false)->items, Math::layout($node, self::SIZE, false)->items, $name);
        }
    }

    public function test_lim_like_base_stacks_limit_in_display(): void
    {
        $box = Math::layout(Math::under('lim', 'x→0'), self::SIZE, true);
        $limitGlyphs = array_filter(self::glyphs($box), fn (Glyph $g) => $g->y > 0);
        $baseDescent = Math::layout(Math::row('lim'), self::SIZE, true)->descent;

        self::assertNotEmpty($limitGlyphs);
        foreach ($limitGlyphs as $g) {
            self::assertGreaterThanOrEqual($baseDescent + self::k('lowerLimitGapMin') - 1e-6, self::inkY($g)[0]);
        }
        self::assertGreaterThanOrEqual(self::k('lowerLimitBaselineDropMin') - 1e-6, min([...array_map(fn (Glyph $g) => $g->y, $limitGlyphs), INF]));
    }

    public function test_non_lim_identifier_base_is_not_movable(): void
    {
        $a = Math::layout(Math::under('x', 'y'), self::SIZE, false);
        $b = Math::layout(Math::sub('x', 'y'), self::SIZE, false);

        self::assertNotEquals($b->items, $a->items);
    }
}
