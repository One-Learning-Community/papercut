<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use Quoin\Math\Math;
use Quoin\Math\Node\Node;
use Quoin\Math\NoteLevel;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\TexLexer;
use Quoin\Math\Tex\TexMacros;
use Quoin\Math\Tex\TexReader;
use PHPUnit\Framework\TestCase;

final class TexMacroTest extends TestCase
{
    private static function same(Node $expected, string $tex): void
    {
        TreeAssert::assertSameTree($expected, Math::tex($tex));
        self::assertSame([], TexReader::read($tex)->notes(), 'no notes for ' . $tex);
    }

    public function test_newcommand(): void
    {
        self::same(Math::row('x ∈', Math::styled('R', 'double-struck')), '\newcommand{\R}{\mathbb{R}} x \in \R');
        self::same(Math::row('x ∈', Math::styled('R', 'double-struck')), '\newcommand\R{\mathbb{R}} x \in \R');
    }

    public function test_arguments_and_default(): void
    {
        self::same(Math::row('a + b'), '\newcommand{\add}[2]{#1 + #2} \add{a}{b}');
        self::same(Math::row('a + b'), '\newcommand{\add}[2]{#1 + #2} \add ab');
        self::same(Math::sup('a', '2'), '\newcommand{\sq}[1]{#1^2} \sq{a}');
        // The default of an optional first argument: empty here, so \partial^{} (one empty script).
        $with = Math::tex('\newcommand{\pd}[2][]{\partial^{#1} #2} \pd[2]{f}');
        TreeAssert::assertSameTree(Math::tex('\partial^{2} f'), $with);
        TreeAssert::assertSameTree(Math::tex('\partial^{} f'), Math::tex('\newcommand{\pd}[2][]{\partial^{#1} #2} \pd{f}'));
        TreeAssert::assertSameTree(Math::tex('\partial^{n} f'), Math::tex('\newcommand{\pd}[2][n]{\partial^{#1} #2} \pd{f}'));
    }

    public function test_renew_and_provide(): void
    {
        self::same(Math::row('b'), '\newcommand{\a}{a}\renewcommand{\a}{b}\a');
        self::same(Math::row('a'), '\newcommand{\a}{a}\providecommand{\a}{b}\a');
        self::same(Math::row('b'), '\providecommand{\a}{b}\a');
    }

    public function test_def(): void
    {
        self::same(Math::sup('x', '2'), '\def\sq#1{#1^2} \sq{x}');
        self::same(Math::row('x + y'), '\def\a{x}\def\b#1#2{#1+#2} \b{\a}{y}');
    }

    public function test_delimited_parameters_are_noted(): void
    {
        $notes = TexReader::read('\def\a#1.{#1} x')->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('delimited parameters', $notes[0]->reason);
    }

    public function test_declare_math_operator(): void
    {
        self::same(Math::tex('\operatorname{tr} A'), '\DeclareMathOperator{\tr}{tr} \tr A');
        self::same(Math::tex('\operatorname*{argmax}_x f'), '\DeclareMathOperator*{\argmax}{argmax} \argmax_x f');
    }

    public function test_macros_are_scoped_to_one_read(): void
    {
        TexReader::read('\newcommand{\a}{x}\a');
        self::assertNotSame([], TexReader::read('\a')->notes());
    }

    public function test_whole_input_wrappers(): void
    {
        self::assertTrue(TexReader::read('$$x$$')->display);
        self::assertFalse(TexReader::read('$x$')->display);
        self::assertFalse(TexReader::read('\(x\)')->display);
        self::assertTrue(TexReader::read('\[x\]')->display);
        self::assertTrue(TexReader::read(' $$ x $$ ')->display);
        foreach (['$x$', '$$x$$', '\(x\)', '\[x\]', '\begin{equation}x\end{equation}', '\begin{equation*}x\end{equation*}', '\begin{displaymath}x\end{displaymath}'] as $tex) {
            self::same(Math::tex('x'), $tex);
        }
        self::assertTrue(TexReader::read('\begin{equation}x\end{equation}')->display);
        self::assertTrue(TexReader::read('\begin{displaymath}x\end{displaymath}')->display);
        self::assertFalse(TexReader::read('x')->display);
    }

    public function test_two_formulas_are_not_one_wrapper(): void
    {
        self::assertFalse(TexReader::read('$a$ and $b$')->display);
        self::assertFalse(TexReader::read('$$a$ b$')->display);
    }

    public function test_top_level_displaystyle_sets_display(): void
    {
        self::assertTrue(TexReader::read('\displaystyle x')->display);
        self::assertFalse(TexReader::read('{\displaystyle x}')->display);
        self::assertFalse(TexReader::read('\textstyle x')->display);
    }

    public function test_definitions_end_with_their_group(): void
    {
        self::assertNotSame([], TexReader::read('{\def\a{x}\a}\a')->notes());
        self::assertSame([], TexReader::read('{\def\a{x}\a} y')->notes());
        self::assertNotSame([], TexReader::read('{\newcommand{\a}{x}}\a')->notes());
        self::same(Math::row('x'), '\def\a{x}{\a}');
    }

    public function test_definitions_still_count_after_the_limit(): void
    {
        [$tokens, $notes] = TexMacros::expand(TexLexer::lex('\def\a{\a}\a\def\b{y}\b'));
        self::assertCount(1, $notes);
        self::assertSame([], array_filter($tokens, static fn (array $t): bool => $t[0] === 'cs' && $t[1] === 'def'));
        self::assertCount(1, TexReader::read('\def\a{\a}\a\def\b{y}\b')->notes());
    }

    public function test_arguments_survive_the_size_cap(): void
    {
        // The call that crosses the cap takes an argument; every one of the 60 arguments is still on the stream.
        $tex = '\def\e#1{' . str_repeat('x', 2000) . '#1}' . str_repeat('\e{Q}', 60);
        [$tokens, $notes] = TexMacros::expand(TexLexer::lex($tex));
        self::assertCount(1, $notes);
        self::assertCount(60, array_filter($tokens, static fn (array $t): bool => $t[0] === 'ch' && $t[1] === 'Q'));
    }

    public function test_hash_hash_in_a_body(): void
    {
        self::assertSame([], TexReader::read('\def\a#1{\def\b##1{##1+#1}}\a{x}\b{y}')->notes());
        TreeAssert::assertSameTree(Math::tex('y+x'), Math::tex('\def\a#1{\def\b##1{##1+#1}}\a{x}\b{y}'));
    }

    public function test_two_bracket_wrappers_are_not_one(): void
    {
        self::assertFalse(TexReader::read('\[a\] + \[b\]')->display);
        self::assertFalse(TexReader::read('\(a\) + \(b\)')->display);
        self::assertTrue(TexReader::read('\[\left[a\right]\]')->display);
    }

    public function test_only_plain_displaymath_is_display(): void
    {
        self::assertNotSame([], TexReader::read('\begin{displaymath*}x\end{displaymath*}')->notes());
        self::assertFalse(TexReader::read('\begin{displaymath*}x\end{displaymath*}')->display);
    }
}
