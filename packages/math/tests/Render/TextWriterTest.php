<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use Papercut\Math\Math;
use Papercut\Math\Node\Node;
use Papercut\Math\Render\TextWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextWriterTest extends TestCase
{
    /** @return iterable<string, array{Node, string}> */
    public static function cases(): iterable
    {
        yield 'quadratic' => [Math::row('x =', Math::frac(Math::row('−b ±', Math::sqrt(Math::sup('b', '2'), '− 4ac')), '2a')), 'x = (−b ± √(b² − 4ac))/(2a)'];
        yield 'cube root' => [Math::row(Math::root('27', '3'), '= 3'), '∛27 = 3'];
        yield 'fourth root' => [Math::root('x', '4'), '∜x'];
        yield 'other index' => [Math::root('x', 'n'), '(n)√(x)'];
        yield 'subscript and superscript' => [Math::subSup('x', '1', '2'), 'x₁²'];
        yield 'exponential' => [Math::sup('e', Math::row('−kt')), 'e^(−kt)'];
        yield 'squared fraction' => [Math::sup(Math::fenced('(', ')', Math::frac('a', 'b')), '2'), '(a/b)²'];
        yield 'density' => [Math::row(Math::text('density'), '=', Math::frac(Math::text('mass'), Math::text('volume'))), 'density = mass/volume'];
        yield 'single letter script' => [Math::sup('x', 'n'), 'xⁿ'];
        yield 'script with one letter and signs' => [Math::sup('x', 'n + 1'), 'xⁿ⁺¹'];
        yield 'script with an operator' => [Math::sup('x', 'n + m'), 'x^(n + m)'];
        yield 'single token script without parens' => [Math::sup('x', Math::text('max')), 'x^max'];
        yield 'function name' => [Math::row('sin', 'x'), 'sin x'];
        yield 'plain root' => [Math::sqrt('2'), '√2'];
    }

    #[DataProvider('cases')]
    public function test_linear_text(Node $node, string $expected): void
    {
        $this->assertSame($expected, TextWriter::write($node));
    }
}
