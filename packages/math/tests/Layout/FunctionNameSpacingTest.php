<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Layout;

use Quoin\Math\Layout\Glyph;
use Quoin\Math\Math;
use Quoin\Math\Tex\TexReader;
use PHPUnit\Framework\TestCase;

/** TeX's spacing around a function name (class Op): thin next to ordinaries, none next to an opening delimiter. */
final class FunctionNameSpacingTest extends TestCase
{
    /** @return array{width: float, xs: list<float>} */
    private static function lay(string $tex): array
    {
        $b = Math::layout(TexReader::read($tex)->node, 10.0);
        $xs = [];
        foreach ($b->items as $i) {
            if ($i instanceof Glyph) {
                $xs[] = $i->x;
            }
        }

        return ['width' => $b->width, 'xs' => $xs];
    }

    private const float THIN = 10.0 * 3 / 18;

    public function test_nothing_between_a_name_and_an_opening_delimiter(): void
    {
        $paren = self::lay('\sin(x)')['xs'];
        $space = self::lay('\sin x')['xs'];
        // s i n ( : the paren sits right after the name's ink, thin space earlier than the operand does.
        self::assertEqualsWithDelta($space[3] - self::THIN, $paren[3], 0.01);
    }

    public function test_nothing_between_a_name_and_a_closing_delimiter_or_comma(): void
    {
        $plain = self::lay('\sin)')['xs'];
        $dropped = self::lay('\sin')['width'];
        self::assertEqualsWithDelta($dropped - self::THIN, $plain[3], 0.01);
        $comma = self::lay('\sin,')['xs'];
        self::assertEqualsWithDelta($dropped - self::THIN, $comma[3], 0.01);
    }

    public function test_a_thin_space_before_a_name_after_an_ordinary(): void
    {
        $i = self::lay('i')['width'];
        $s = self::lay('\sin')['xs'][0];
        self::assertEqualsWithDelta($i + self::THIN, self::lay('i\sin x')['xs'][1], 0.01);
        self::assertGreaterThan(0.0, $s + 1.0);
    }

    public function test_no_space_before_a_name_at_the_start_after_an_opening_delimiter_or_an_operator(): void
    {
        self::assertEqualsWithDelta(0.0, self::lay('\sin x')['xs'][0], 0.001);
        $open = self::lay('(\sin x')['xs'];
        $paren = self::lay('(')['width'];
        self::assertEqualsWithDelta($paren, $open[1], 0.01);
        $plus = self::lay('a+\sin x')['xs'];
        $head = self::lay('a+')['width'];
        self::assertEqualsWithDelta($head, $plus[2], 0.01);
    }

    public function test_a_script_follows_a_multi_letter_upright_base_directly(): void
    {
        // The base's trailing space goes after the scripts, so the script starts at the base's advance.
        $base = self::lay('\mathrm{Fe}')['xs'];
        $e = $base[1];
        $with = self::lay('\mathrm{Fe}^{3}')['xs'];
        $eAdvance = self::lay('\mathrm{e}')['width'];
        self::assertEqualsWithDelta($e + $eAdvance, $with[2], 0.6);
    }

    public function test_a_non_function_upright_name_is_a_plain_ordinary(): void
    {
        // 2\mathrm{Fe}, 5\mathrm{kg}: Ord-Ord is 0; \mathrm{Fe}(...) Ord-Open 0.
        self::assertEqualsWithDelta(self::lay('2')['width'], self::lay('2\mathrm{Fe}')['xs'][1], 0.01);
        self::assertEqualsWithDelta(self::lay('5')['width'], self::lay('5\mathrm{kg}')['xs'][1], 0.01);
        self::assertEqualsWithDelta(self::lay('\mathrm{Fe}')['width'], self::lay('\mathrm{Fe}(x')['xs'][2], 0.01);
        self::assertEqualsWithDelta(self::lay('\mathrm{Fe}')['width'], self::lay('\mathrm{Fe}\mathrm{k}')['xs'][2], 0.01);
    }

    public function test_operatorname_is_a_function(): void
    {
        self::assertEqualsWithDelta(self::lay('i')['width'] + self::THIN, self::lay('i\operatorname{foo} x')['xs'][1], 0.01);
        self::assertEqualsWithDelta(self::lay('\operatorname{foo}')['width'] - self::THIN, self::lay('\operatorname{foo})')['xs'][3], 0.01);
    }

    public function test_a_thin_space_after_a_factorial_and_none_before_a_relation(): void
    {
        self::assertEqualsWithDelta(self::lay('n!')['width'] + self::THIN, self::lay('n!\sin x')['xs'][2], 0.01);
        // The relation's own thick space is all there is: the same as for a letter before it.
        $sin = self::lay('\sin=')['xs'];
        $x = self::lay('x=')['xs'];
        $rel = self::lay('\sin')['width'] - self::THIN;
        self::assertEqualsWithDelta($rel + ($x[1] - self::lay('x')['width']), $sin[3], 0.01);
    }

    public function test_mathml_function_names(): void
    {
        $lay = static function (string $mi): array {
            $b = Math::layout(Math::mathml('<math><mn>2</mn>' . $mi . '</math>'), 10.0);
            $xs = [];
            foreach ($b->items as $i) {
                if ($i instanceof Glyph) {
                    $xs[] = $i->x;
                }
            }

            return $xs;
        };
        $two = self::lay('2')['width'];
        self::assertEqualsWithDelta($two + self::THIN, $lay('<mi>sin</mi>')[1], 0.01);
        self::assertEqualsWithDelta($two + self::THIN, $lay('<mi>foo</mi><mo>&#x2061;</mo>')[1], 0.01);
        self::assertEqualsWithDelta($two, $lay('<mi>kg</mi>')[1], 0.01);
        self::assertEqualsWithDelta($two, $lay('<mi>foo</mi>')[1], 0.01);
    }
}
