<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Math;
use Papercut\Math\Node\Node;

/**
 * Sample formulas for tests and visual checks (tests/render-fixtures.php renders them).
 *
 * Naming convention: a fixture name ending in "-inline" is laid out in text (inline) style; every other name is laid
 * out in display style. The same formula may appear under both names.
 */
final class Fixtures
{
    /** @return array<string, Node> */
    public static function all(): array
    {
        $sum = fn () => Math::row(Math::sum('i = 1', 'n'), Math::sup('i', '2'), '=', Math::frac(Math::row('n(n + 1)(2n + 1)'), '6'));
        $quad = fn () => Math::row('x =', Math::frac(Math::row('−b ±', Math::sqrt(Math::sup('b', '2'), '− 4ac')), '2a'));

        return [
            'quadratic-display' => $quad(),
            'quadratic-inline' => $quad(),
            'slope' => Math::row('m =', Math::frac(
                Math::row(Math::sub('y', '2'), '−', Math::sub('y', '1')),
                Math::row(Math::sub('x', '2'), '−', Math::sub('x', '1')),
            )),
            'cube-root' => Math::row(Math::root('27', '3'), '= 3'),
            'mixed-number' => Math::row('3', Math::frac('1', '2')),
            'a-over-b' => Math::row(Math::frac('a', 'b'), '= c'),
            'density' => Math::row(Math::text('density'), '=', Math::frac(Math::text('mass'), Math::text('volume'))),
            'paren-frac-squared' => Math::sup(Math::fenced('(', ')', Math::frac('a', 'b')), '2'),
            'one-plus-1-over-n' => Math::sup(Math::fenced('(', ')', Math::row('1 +', Math::frac('1', 'n'))), 'n'),
            'x1-squared' => Math::subSup('x', '1', '2'),
            'e-to-minus-kt' => Math::sup('e', '−kt'),
            'unicode-script-digits' => Math::row('b² + x₂ = c'),
            'nested-radical' => Math::sqrt('1 +', Math::frac('1', Math::row('1 +', Math::frac('1', 'x')))),
            'binomial' => Math::row(Math::binom('n', 'k'), '=', Math::frac('n!', Math::row('k!(n − k)!'))),
            'spaced-dx' => Math::row('∫', Math::sup('x', '2'), Math::space(1 / 6), Math::styled('d', 'normal'), 'x'),
            'phantom-align' => Math::row(Math::phantom('−'), '3', '+', '12'),
            'double-struck-r' => Math::row('x ∈', Math::styled('R', 'double-struck'), ', ', Math::styled('h', 'italic')),
            'sum-display' => $sum(),
            'sum-inline' => $sum(),
            'integral-display' => Math::row(Math::integral('0', '1'), Math::sup('x', '2'), Math::space(1 / 6), Math::styled('d', 'normal'), 'x', '=', Math::frac('1', '3')),
            'limit-inline' => Math::row(Math::under('lim', 'x → 0'), Math::frac(Math::row('sin x'), 'x'), '= 1'),
            'x-hat' => Math::row(Math::hat('x'), '+ 1'),
            'wide-hat' => Math::hat('xyz'),
            'x-bar' => Math::row(Math::overline('x'), '=', Math::frac(Math::row('x₁ + x₂ + x₃'), '3')),
            'vector-ab' => Math::row(Math::vec('ab'), '=', Math::vec('a'), '+', Math::vec('b')),
            'overbrace' => Math::overbrace('a + b + c', Math::text('three terms')),
            'tall-fence' => Math::row(Math::fenced('[', ']', Math::frac(Math::frac('1', 'x'), Math::frac('1', Math::frac('1', 'y')))), '= z'),
        ];
    }
}
