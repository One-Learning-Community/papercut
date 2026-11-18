<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Render;

use Quoin\Math\Math;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\UnderOver;
use Quoin\Math\Render\TextWriter;
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
        yield 'space wide' => [Math::row('a', Math::space(0.5), 'b'), 'a b'];
        yield 'space thin' => [Math::row('a', Math::space(0.1), 'b'), 'ab'];
        yield 'phantom' => [Math::row('a', Math::phantom('x'), 'b'), 'ab'];
        yield 'stack' => [Math::stack('n', 'k'), '(n; k)'];
        yield 'binom' => [Math::binom('n', 'k'), '(n; k)'];
        yield 'stack in a script' => [Math::sup(Math::stack('n', 'k'), '2'), '(n; k)²'];
        yield 'styled' => [Math::styled('R', 'double-struck'), 'ℝ'];
        yield 'single letter script' => [Math::sup('x', 'n'), 'xⁿ'];
        yield 'script with one letter and signs' => [Math::sup('x', 'n + 1'), 'xⁿ⁺¹'];
        yield 'script with an operator' => [Math::sup('x', 'n + m'), 'x^(n + m)'];
        yield 'single token script without parens' => [Math::sup('x', Math::text('max')), 'x^max'];
        yield 'function name' => [Math::row('sin', 'x'), 'sin x'];
        yield 'middle bar' => [Math::fenced('{', '}', 'x', Math::middle('|'), Math::frac('a', 'b')), '{x | a/b}'];
        yield 'plain root' => [Math::sqrt('2'), '√2'];
    }

    #[DataProvider('cases')]
    public function test_linear_text(Node $node, string $expected): void
    {
        $this->assertSame($expected, TextWriter::write($node));
    }

    public function test_limits_accents_and_bars(): void
    {
        self::assertSame('∑_(i=1)^n i²', TextWriter::write(Math::row(Math::sum('i = 1', 'n'), Math::sup('i', '2'))));
        self::assertSame("x\u{0302}", TextWriter::write(Math::hat('x')));
        self::assertSame("x\u{0304}", TextWriter::write(Math::overline('x')));
        self::assertSame("x\u{0332}", TextWriter::write(Math::underline('x')));
        self::assertSame("(xy)\u{0302}", TextWriter::write(Math::hat('xy')));
        self::assertSame("a\u{20D7}", TextWriter::write(Math::vec('a')));
        self::assertSame('lim_(x→0) sin x', TextWriter::write(Math::row(Math::under('lim', 'x → 0'), 'sin x')));
        self::assertSame('(a + b)_n', TextWriter::write(Math::underbrace('a + b', 'n')));
        self::assertSame('(a + b)', TextWriter::write(Math::overbrace('a + b')));
        self::assertSame('∫₀¹ x', TextWriter::write(Math::row(Math::integral('0', '1'), 'x')));
        self::assertSame('=_def', TextWriter::write(Math::under('=', Math::text('def'))));
    }

    public function test_unstretched_brace_reads_as_a_limit(): void
    {
        self::assertSame('a^(⏞)', TextWriter::write(new UnderOver(Math::row('a'), null, new Operator('⏞', false))));
    }

    public function test_brace_wins_over_accentunder(): void
    {
        $base = Math::underbrace('x + y');
        self::assertInstanceOf(UnderOver::class, $base);
        $flagged = new UnderOver($base->base, $base->under, $base->over, false, true);

        self::assertSame(TextWriter::write($base), TextWriter::write($flagged));
        self::assertStringNotContainsString('⏟', TextWriter::write($flagged));
    }

    public function test_a_label_less_chemistry_arrow_reads_as_a_relation(): void
    {
        self::assertSame('CH₄ + 2 O₂ → CO₂ + 2 H₂O', TextWriter::write(Math::tex('\\ce{CH4 + 2 O2 -> CO2 + 2 H2O}')));
        self::assertSame('A → B', TextWriter::write(Math::tex('\\ce{A -> B}')));
    }

    public function test_a_labeled_arrow_keeps_its_label_and_is_set_off(): void
    {
        self::assertSame('A →^Δ B', TextWriter::write(Math::tex('\\ce{A ->[\\Delta] B}')));
    }

    public function test_blank_limits_are_no_limits(): void
    {
        foreach (['\\overset{}{x}', '\\underset{}{x}', '\\overbrace{x}^{}', '\\underbrace{x}_{}'] as $tex) {
            self::assertSame('x', TextWriter::write(Math::tex($tex)), $tex);
        }
    }

    public function test_a_prescript_in_the_marked_form_is_set_off_from_the_atom_before(): void
    {
        self::assertSame('y ^(ab)x', TextWriter::write(Math::tex('y{}^{ab}x')));
        self::assertSame('y _(a + b)x', TextWriter::write(Math::tex('y{}_{a+b}x')));
        // The compact form reads as itself and needs no separator.
        self::assertSame('y¹⁴C', TextWriter::write(Math::tex('y{}^{14}C')));
    }
}
