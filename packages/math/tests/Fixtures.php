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
            'tall-fence' => Math::row(Math::fenced('[', ']', Math::frac(Math::frac('1', 'x'), Math::frac('1', Math::frac('1', 'y')))), '= z'),
        ];
    }
}
