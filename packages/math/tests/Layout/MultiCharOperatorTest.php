<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Math;
use Papercut\Math\Mathml\MathmlReader;
use Papercut\Math\Node\Operator;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\OmmlWriter;
use PHPUnit\Framework\TestCase;

final class MultiCharOperatorTest extends TestCase
{
    private static function text(Box $b): string
    {
        return implode('', array_map(fn (Glyph $g) => (string) $g->text, array_filter($b->items, fn ($i) => $i instanceof Glyph)));
    }

    public function test_every_character_of_an_operator_is_drawn(): void
    {
        foreach (['lim', 'mod', ':=', '′′'] as $op) {
            $b = Math::layout(Math::row('1', new Operator($op), '2'), 12.0, true);
            self::assertSame('1' . $op . '2', self::text($b), $op);
        }
    }

    public function test_multi_character_big_operator_is_not_resized_or_stretched(): void
    {
        $b = Math::layout(Math::bigOp('lim', 'x', null), 12.0, true);
        self::assertStringContainsString('lim', self::text($b));
        // no display variant: the name keeps its normal height above the limit
        self::assertEqualsWithDelta(Math::layout(new Operator('lim'), 12.0, true)->ascent, $b->ascent, 1e-6);
    }

    public function test_mathml_round_trip_keeps_all_letters(): void
    {
        $node = MathmlReader::read('<math><munder><mo movablelimits="true">lim</mo><mi>x</mi></munder></math>')->node;
        $again = MathmlReader::read(MathmlWriter::write($node))->node;
        self::assertStringContainsString('lim', self::text(Math::layout($again, 12.0, true)));
        self::assertStringContainsString('lim', self::text(Math::layout($again, 12.0, false)));
    }

    public function test_omml_writes_alphabetic_operator_text_upright(): void
    {
        $xml = OmmlWriter::oMath(MathmlReader::read('<math><mo>lim</mo></math>')->node);
        self::assertStringContainsString('<m:sty m:val="p"/>', $xml);
        self::assertStringNotContainsString('<m:sty', OmmlWriter::oMath(MathmlReader::read('<math><mo>+</mo></math>')->node));
    }

    public function test_mo_lim_with_limit_is_a_limit_name_like_mi_lim(): void
    {
        $mo = MathmlReader::read('<math><munder><mo>lim</mo><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder></math>')->node;
        $mi = MathmlReader::read('<math><munder><mi>lim</mi><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder></math>')->node;
        foreach ([true, false] as $display) {
            $a = Math::layout($mo, 12.0, $display);
            $b = Math::layout($mi, 12.0, $display);
            // the operator keeps the dictionary's spacing, so only the arrangement is compared
            self::assertEqualsWithDelta($b->descent, $a->descent, 1e-6, $display ? 'display' : 'inline');
        }
    }

    public function test_mo_lim_with_movablelimits_false_stays_stacked_inline(): void
    {
        $fixed = MathmlReader::read('<math><munder><mo movablelimits="false">lim</mo><mi>x</mi></munder></math>')->node;
        $movable = MathmlReader::read('<math><munder><mo>lim</mo><mi>x</mi></munder></math>')->node;
        self::assertGreaterThan(Math::layout($movable, 12.0, false)->descent, Math::layout($fixed, 12.0, false)->descent + 1e-9);
    }

    public function test_multi_character_accent_draws_every_character(): void
    {
        foreach ([
            '<math><mover accent="true"><mi>x</mi><mo>..</mo></mover></math>' => '..',
            '<math><mover><mi>x</mi><mo accent="true">~~</mo></mover></math>' => '~~',
            '<math><munder accentunder="true"><mi>x</mi><mo>__</mo></munder></math>' => '__',
        ] as $src => $marks) {
            $b = Math::layout(MathmlReader::read($src)->node, 12.0, true);
            $glyphs = array_values(array_filter($b->items, fn ($i) => $i instanceof Glyph));
            self::assertCount(3, $glyphs, $src);
            self::assertSame($marks, (string) $glyphs[1]->text . (string) $glyphs[2]->text, $src);
        }
    }

    public function test_styled_mo_lim_still_counts_as_a_limit_name(): void
    {
        $plain = Math::layout(MathmlReader::read('<math><munder><mo>lim</mo><mi>x</mi></munder></math>')->node, 12.0, false);
        $bold = Math::layout(MathmlReader::read('<math><munder><mo mathvariant="bold">lim</mo><mi>x</mi></munder></math>')->node, 12.0, false);
        // inline style: the limit moves beside the name like a script, so the box is not stacked
        self::assertEqualsWithDelta($plain->descent, $bold->descent, 1e-6);
        self::assertGreaterThan($plain->width, $bold->width + 5.0);
    }

    public function test_mo_lim_and_mi_lim_lay_out_alike(): void
    {
        $mi = Math::layout(MathmlReader::read('<math><mi>lim</mi><mi>f</mi></math>')->node, 12.0, true);
        $mo = Math::layout(MathmlReader::read('<math><mo>lim</mo><mi>f</mi></math>')->node, 12.0, true);
        self::assertEqualsWithDelta($mi->width, $mo->width, 0.01);
    }

    public function test_round_trip_keeps_limit_spacing(): void
    {
        $node = Math::under('lim', Math::row('x'));
        $again = MathmlReader::read(MathmlWriter::write($node))->node;
        foreach ([true, false] as $display) {
            self::assertEqualsWithDelta(Math::layout($node, 12.0, $display)->width, Math::layout($again, 12.0, $display)->width, 0.01);
        }
    }
}
