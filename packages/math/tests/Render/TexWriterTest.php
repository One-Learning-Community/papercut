<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Node\DisplayStyle;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Grid;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\UnderOver;
use Papercut\Math\NoteLevel;
use Papercut\Math\Render\TexWriter;
use Papercut\Math\Tests\Fixtures;
use Papercut\Math\Tex\TexReader;
use Papercut\Math\Tex\TexSymbols;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TexWriterTest extends TestCase
{
    private static function tex(Node $n): string
    {
        return TexWriter::write($n);
    }

    public function test_identifiers(): void
    {
        self::assertSame('x', self::tex(new Identifier('x')));
        self::assertSame('\alpha', self::tex(new Identifier('α')));
        self::assertSame('\varGamma', self::tex(new Identifier('Γ')));
        self::assertSame('\Gamma', self::tex(new Identifier('Γ', false)));
        self::assertSame('\mathrm{d}', self::tex(new Identifier('d', false)));
        self::assertSame('\infty', self::tex(new Identifier('∞', false)));
        self::assertSame('\sin', self::tex(new Identifier('sin', null, true)));
        self::assertSame('\lim', self::tex(new Identifier('lim', null, true)));
        self::assertSame('\operatorname{rank}', self::tex(new Identifier('rank', false, true)));
        self::assertSame('\mathit{ab}', self::tex(new Identifier('ab', true)));
    }

    public function test_styled_letters(): void
    {
        self::assertSame('\mathbb{R}', self::tex(Math::styled('R', 'double-struck')));
        self::assertSame('\mathbb{N}', self::tex(Math::styled('N', 'double-struck')));
        self::assertSame('\mathbf{v}', self::tex(Math::styled('v', 'bold')));
        self::assertSame('\mathcal{L}', self::tex(Math::styled('L', 'script')));
        self::assertSame('\mathfrak{g}', self::tex(Math::styled('g', 'fraktur')));
        self::assertSame('\mathbb{AB}', self::tex(Math::styled('AB', 'double-struck')));
        self::assertSame('\boldsymbol{x}', self::tex(Math::styled('x', 'bold-italic')));
        self::assertSame('\mathbb{1}', self::tex(Math::styled('1', 'double-struck')));
    }

    public function test_numbers_and_text(): void
    {
        self::assertSame('3.14', self::tex(new Number('3.14')));
        self::assertSame('\text{if x}', self::tex(Math::text('if x')));
        self::assertSame('\text{ab}', self::tex(Math::text('ab')));
        self::assertSame(
            '\text{\textbackslash{} \{ \} \$ \& \# \textasciicircum{} \_ \% \textasciitilde{}}',
            self::tex(Math::text('\\ { } $ & # ^ _ % ~')),
        );
    }

    public function test_operators(): void
    {
        self::assertSame('x + 1', self::tex(Math::row('x + 1')));
        self::assertSame('x - 1', self::tex(Math::row('x − 1')));
        self::assertSame('a \leq b', self::tex(Math::row('a ≤ b')));
        self::assertSame('a \times b', self::tex(Math::row('a × b')));
        self::assertSame('\sum', self::tex(new Operator('∑')));
        self::assertSame('\{', self::tex(new Operator('{')));
        self::assertSame('\mathrel{=}', self::tex(new Operator('=', lspace: 5, rspace: 5)));
        self::assertSame('\mathbin{+}', self::tex(new Operator('+', lspace: 4, rspace: 4)));
        self::assertSame('\mathpunct{,}', self::tex(new Operator(',', lspace: 0, rspace: 3)));
        self::assertSame('\mathop{x}', self::tex(new Operator('x', lspace: 1, rspace: 1)));
        self::assertSame('\mathopen{(}', self::tex(new Operator('(', lspace: 0, rspace: 0)));
        self::assertSame('\mathclose{)}', self::tex(new Operator(')', lspace: 0, rspace: 0)));
        self::assertSame('\mathord{x}', self::tex(new Operator('x', lspace: 0, rspace: 0)));
        // A form alone is MathML-only and is dropped (R1).
        self::assertSame('=', self::tex(new Operator('=', form: 'infix')));
        self::assertSame('a = b', self::tex(new Row([new Identifier('a'), new Operator('=', form: 'prefix'), new Identifier('b')])));
        self::assertSame('a + b', self::tex(new Row([new Identifier('a'), new Operator('+', form: 'infix'), new Identifier('b')])));
    }

    public function test_fixed_size_delimiters(): void
    {
        self::assertSame('\bigl(', self::tex(Math::big('(', 1)));
        self::assertSame('\Bigr)', self::tex(Math::big(')', 2)));
        self::assertSame('\biggl\langle', self::tex(Math::big('⟨', 3)));
        self::assertSame('\Bigg|', self::tex(Math::big('|', 4)));
        self::assertSame('\bigr\}', self::tex(Math::big('}', 1)));
        self::assertSame('\Bigl[', self::tex(new Operator('[', minSize: 1.7)));
        self::assertSame('\bigg|', self::tex(new Operator('|', maxSize: 2.6)));
    }

    public function test_spaces(): void
    {
        self::assertSame('\,', self::tex(Math::space(3 / 18)));
        self::assertSame('\:', self::tex(Math::space(4 / 18)));
        self::assertSame('\;', self::tex(Math::space(5 / 18)));
        self::assertSame('\!', self::tex(Math::space(-3 / 18)));
        self::assertSame('\quad', self::tex(Math::space(1)));
        self::assertSame('\qquad', self::tex(Math::space(2)));
        self::assertSame('\hspace{0.37em}', self::tex(Math::space(0.37)));
        self::assertSame('\hspace{-0.5em}', self::tex(Math::space(-0.5)));
    }

    public function test_phantom_fractions_roots(): void
    {
        self::assertSame('\phantom{x}', self::tex(Math::phantom('x')));
        self::assertSame('\frac{a}{b}', self::tex(Math::frac('a', 'b')));
        self::assertSame('\frac{a + 1}{b}', self::tex(Math::frac('a + 1', 'b')));
        self::assertSame('\binom{n}{k}', self::tex(Math::binom('n', 'k')));
        self::assertSame('{a \atop b}', self::tex(Math::stack('a', 'b')));
        self::assertSame('\sqrt{x}', self::tex(Math::sqrt('x')));
        self::assertSame('\sqrt[3]{x}', self::tex(Math::root('x', '3')));
    }

    public function test_scripts(): void
    {
        self::assertSame('x^2', self::tex(Math::sup('x', '2')));
        self::assertSame('x_i', self::tex(Math::sub('x', 'i')));
        self::assertSame('x_1^2', self::tex(Math::subSup('x', '1', '2')));
        self::assertSame('e^{- k t}', self::tex(Math::sup('e', '−kt')));
        self::assertSame('x^{12}', self::tex(Math::sup('x', '12')));
        self::assertSame('x^\alpha', self::tex(Math::sup('x', 'α')));
        self::assertSame('{x_1}^2', self::tex(Math::sup(Math::sub('x', '1'), '2')));
        self::assertSame('{a + b}^2', self::tex(Math::sup(Math::row('a + b'), '2')));
        self::assertSame('x^{\frac{a}{b}}', self::tex(Math::sup('x', Math::frac('a', 'b'))));
        self::assertSame('\sum\nolimits_{i = 1}^n', self::tex(new \Papercut\Math\Node\Scripts(new Operator('∑'), Math::row('i = 1'), Math::row('n'))));
        self::assertSame('\int_0^1', self::tex(Math::integral('0', '1')));
    }

    public function test_control_word_script_arguments_are_braced(): void
    {
        foreach (['x_{\sin}', 'x_{\ne}', '\sum_{\int}', 'x_{\,}', '\operatorname*{argmax}_{\sin}', '\operatorname*{ab}_{\neq}', 'x^\alpha', 'x_\infty'] as $tex) {
            $out = TexWriter::write(TexReader::read($tex)->node);
            self::assertDoesNotMatchRegularExpression('/[_^]\\\\(?!alpha|infty)/', $out, $tex);
            self::assertEquals(TexReader::read($tex)->node, TexReader::read($out)->node, $tex);
        }
        self::assertSame('x_{\sin}', TexWriter::write(TexReader::read('x_{\sin}')->node));
        self::assertSame('x_\infty', TexWriter::write(TexReader::read('x_\infty')->node));
    }

    public function test_operatorname_star_spelling_only_for_an_under_limit(): void
    {
        // Only the names TeX gives limits (lim, max, ...) take the starred spelling; others read back the same through \underset.
        self::assertSame('\underset{x}{\operatorname{argmax}}', TexWriter::write(TexReader::read('\operatorname*{argmax}_x')->node));
        self::assertSame('\liminf_x', TexWriter::write(TexReader::read('\operatorname*{liminf}_x')->node));
        self::assertSame('\overset{a}{\mathrm{xy}}', TexWriter::write(TexReader::read('\overset{a}{\mathrm{xy}}')->node));
    }

    public function test_limits(): void
    {
        self::assertSame('\sum_{i = 1}^n', self::tex(Math::sum('i = 1', 'n')));
        self::assertSame('\prod^n', self::tex(Math::product(null, 'n')));
        self::assertSame('\lim_{x \to 0}', self::tex(new UnderOver(new Identifier('lim', null, true), Math::row('x → 0'), null)));
        self::assertSame('\int\limits_0^1', self::tex(new UnderOver(new Operator('∫'), Math::row('0'), Math::row('1'))));
        self::assertSame('{\sum_i}^2', self::tex(Math::sup(Math::sum('i'), '2')));
    }

    public function test_accents_and_brackets(): void
    {
        self::assertSame('\widehat{x}', self::tex(Math::hat('x')));
        self::assertSame('\hat{x}', self::tex(new UnderOver(Math::row('x'), null, new Operator("\u{0302}", stretchy: false), true)));
        self::assertSame('\widetilde{x}', self::tex(Math::tilde('x')));
        self::assertSame('\overrightarrow{v}', self::tex(Math::vec('v')));
        self::assertSame('\vec{v}', self::tex(new UnderOver(Math::row('v'), null, new Operator("\u{20D7}", stretchy: false), true)));
        self::assertSame('\dot{x}', self::tex(Math::dot('x')));
        self::assertSame('\overline{A B}', self::tex(Math::overline('AB')));
        self::assertSame('\underline{A B}', self::tex(Math::underline('AB')));
        self::assertSame('\overbrace{a + b}', self::tex(Math::overbrace('a + b')));
        self::assertSame('\overbrace{a + b}^n', self::tex(Math::overbrace('a + b', 'n')));
        self::assertSame('\underbrace{a + b}', self::tex(Math::underbrace('a + b')));
        self::assertSame('\underbrace{a + b}_{n + 1}', self::tex(Math::underbrace('a + b', 'n + 1')));
        self::assertSame('\overset{a}{x}', self::tex(Math::over('x', 'a')));
        self::assertSame('\underset{a}{x}', self::tex(Math::under('x', 'a')));
        self::assertSame('\underset{b}{\overset{a}{x}}', self::tex(Math::underOver('x', 'b', 'a')));
        self::assertSame('\xrightarrow{f}', self::tex(Math::xarrow('→', 'f')));
        self::assertSame('\xrightarrow[g]{f}', self::tex(Math::xarrow('→', 'f', 'g')));
        self::assertSame('\xleftrightarrow[g]{}', self::tex(Math::xarrow('↔', null, 'g')));
        self::assertSame('\xRightarrow{f}', self::tex(Math::xarrow('⇒', 'f')));
        self::assertSame('\xlongequal{\text{def}}', self::tex(Math::xarrow('=', Math::text('def'))));
        self::assertSame('\overset{a}{\to}', self::tex(new UnderOver(new Operator('→', stretchy: false), null, Math::row('a'))));
    }

    public function test_fenced_and_middle(): void
    {
        self::assertSame('\left( x \right)', self::tex(Math::fenced('(', ')', 'x')));
        self::assertSame('\left. x \right|', self::tex(Math::fenced('', '|', 'x')));
        self::assertSame('\left\{ x \middle| x > 0 \right\}', self::tex(Math::fenced('{', '}', 'x', Math::middle('|'), 'x > 0')));
        self::assertSame('\left\langle x \right\rangle', self::tex(Math::fenced('⟨', '⟩', 'x')));
        self::assertSame('\left\lfloor x \right\rfloor', self::tex(Math::fenced('⌊', '⌋', 'x')));
        self::assertSame('\left[ x \right)', self::tex(Math::fenced('[', ')', 'x')));
        self::assertSame('\left\Vert x \right\Vert', self::tex(Math::fenced('‖', '‖', 'x')));
    }

    public function test_matrices(): void
    {
        $rows = [['a', 'b'], ['c', 'd']];
        self::assertSame('\begin{pmatrix}a & b \\\\ c & d\end{pmatrix}', self::tex(Math::matrix($rows)));
        self::assertSame('\begin{bmatrix}a & b \\\\ c & d\end{bmatrix}', self::tex(Math::matrix($rows, 'bracket')));
        self::assertSame('\begin{Bmatrix}a & b \\\\ c & d\end{Bmatrix}', self::tex(Math::matrix($rows, 'brace')));
        self::assertSame('\begin{vmatrix}a & b \\\\ c & d\end{vmatrix}', self::tex(Math::matrix($rows, 'bar')));
        self::assertSame('\begin{Vmatrix}a & b \\\\ c & d\end{Vmatrix}', self::tex(Math::matrix($rows, 'doublebar')));
        self::assertSame('\begin{matrix}a & b \\\\ c & d\end{matrix}', self::tex(Math::matrix($rows, 'none')));
        self::assertSame('\begin{cases}1 & x > 0 \\\\ 0 & x \leq 0\end{cases}', self::tex(Math::cases([['1', 'x > 0'], ['0', 'x ≤ 0']])));
        self::assertSame('\begin{array}{rr}a & b\end{array}', self::tex(Math::matrix([['a', 'b']], 'none', 'right')));
        self::assertSame('\left\langle \begin{array}{cc}a & b\end{array} \right\rangle', self::tex(new Fenced('⟨', '⟩', new Grid([[Math::row('a'), Math::row('b')]]))));
        self::assertSame('\left( \begin{array}{ll}a & b\end{array} \right)', self::tex(Math::matrix([['a', 'b']], 'paren', 'left')));
        self::assertSame('\begin{matrix}a \\\\ c & d\end{matrix}', self::tex(Math::matrix([['a'], ['c', 'd']], 'none')));
    }

    public function test_dcases(): void
    {
        $cases = new Fenced('{', '', new Grid([[Math::row('1'), Math::row('x')]], ['left'], display: true));
        self::assertSame('\begin{dcases}1 & x\end{dcases}', self::tex($cases));
    }

    public function test_display_grids_stay_display_grids(): void
    {
        self::assertSame('\begin{gathered}a \\\\ b\end{gathered}', self::tex(new Grid([[Math::row('a')], [Math::row('b')]], ['center'], display: true)));
        self::assertSame('\begin{darray}{ll}a & b\end{darray}', self::tex(new Grid([[Math::row('a'), Math::row('b')]], ['left', 'left'], display: true)));
    }

    public function test_primes_are_apostrophes_and_negations_are_not(): void
    {
        self::assertSame("f'''", self::tex(Math::tex("f'''")));
        self::assertSame("f''", self::tex(Math::tex("f''")));
        self::assertSame("f''''^2", self::tex(Math::tex("f''''^2")));
        self::assertSame("f''^2_a", self::tex(Math::tex("f''_a^2")));
        self::assertSame('\prime\prime', self::tex(new Operator('″')));
        self::assertSame('\not\subset', self::tex(new Operator('⊄')));
        self::assertSame('a \not\equiv b', self::tex(Math::row('a', new Operator('≢'), 'b')));
        self::assertSame('\neq', self::tex(new Operator('≠')));
    }

    public function test_double_primes_round_trip_wherever_they_stand(): void
    {
        foreach (["\\lim\\nolimits'' x", "f\u{2033}", "\u{2033} + 1"] as $src) {
            $a = Math::tex($src);
            \Papercut\Math\Tests\TreeAssert::assertSameTree($a, Math::tex(self::tex($a)));
        }
        \Papercut\Math\Tests\TreeAssert::assertSameTree(new Identifier('″', false), Math::tex(self::tex(new Operator('″'))));
        self::assertSame('\prime\prime', self::tex(Math::tex('\prime\prime')));
    }

    public function test_wide_display_grids_are_darrays(): void
    {
        $wide = new Grid([[Math::row('a'), Math::row('b')]], ['center', 'center'], display: true);
        self::assertSame('\begin{darray}{cc}a & b\end{darray}', self::tex($wide));
        \Papercut\Math\Tests\TreeAssert::assertSameTree($wide, Math::tex(self::tex($wide)));
    }

    public function test_symbol_commands_and_primes_round_trip(): void
    {
        self::assertSame('\Re z', self::tex(Math::tex('\Re z')));
        foreach (['\Re z + \Im w', "f''' + g'''", "f'''^2", "f''_a^2", '\not\subset \not\equiv'] as $src) {
            $a = Math::tex($src);
            \Papercut\Math\Tests\TreeAssert::assertSameTree($a, Math::tex(self::tex($a)));
        }
    }

    public function test_aligned(): void
    {
        self::assertSame('\begin{aligned}x &= 1\end{aligned}', self::tex(Math::aligned([['x', '= 1']])));
        self::assertSame(
            '\begin{aligned}x &= 1 \\\\ y &= 2\end{aligned}',
            self::tex(Math::aligned([['x', '= 1'], ['y', '= 2']])),
        );
        self::assertSame(
            '\begin{aligned}x &= 1 & y &= 2\end{aligned}',
            self::tex(Math::aligned([['x', '= 1', 'y', '= 2']])),
        );
        // The leading operator of a left cell loses its form, whatever it is, in a bare Operator cell and a Row cell alike.
        self::assertSame('\begin{aligned}x &+ 1\end{aligned}', self::tex(Math::aligned([['x', '+ 1']])));
        self::assertSame('\begin{aligned}x &+\end{aligned}', self::tex(Math::aligned([['x', '+']])));
        self::assertSame('\begin{aligned}x &= 1 \\\\ &= 2\end{aligned}', self::tex(Math::aligned([['x', '= 1'], ['', '= 2']])));
        self::assertSame('\begin{aligned}&= 2\end{aligned}', self::tex(Math::aligned([['', '= 2']])));
    }

    public function test_display_style_and_fallback(): void
    {
        self::assertSame('{\displaystyle \frac{a}{b}}', self::tex(new DisplayStyle(Math::frac('a', 'b'), true)));
        self::assertSame('{\textstyle \frac{a}{b}}', self::tex(new DisplayStyle(Math::frac('a', 'b'), false)));
        $note = new MathNote(NoteLevel::Approximated, 'mglyph', 'x');
        self::assertSame('x', self::tex(new Fallback(new Identifier('x'), $note)));
        self::assertSame('a b', self::tex(new Row([new Identifier('a'), new Fallback(null, $note), new Identifier('b')])));
        self::assertSame('', self::tex(new Fallback(null, $note)));
    }

    public function test_nested_rows_are_braced(): void
    {
        self::assertSame('a {b c}', self::tex(new Row([new Identifier('a'), new Row([new Identifier('b'), new Identifier('c')])])));
    }

    public function test_command_followed_by_letter_gets_a_space(): void
    {
        self::assertSame('\alpha x', self::tex(Math::row('α x')));
        self::assertSame('\sin x', self::tex(Math::row('sin x')));
        self::assertSame('\mathrm{d} x', self::tex(Math::row(new Identifier('d', false), 'x')));
    }

    public function test_no_trailing_whitespace(): void
    {
        foreach ([Math::fenced('(', ')'), Math::matrix([['a', '']], 'none'), Math::aligned([['x', '']]), Math::row('')] as $n) {
            $s = self::tex($n);
            self::assertSame(trim($s), $s);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function fixtureNames(): iterable
    {
        foreach (array_keys(Fixtures::all()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('fixtureNames')]
    public function test_every_fixture_writes(string $name): void
    {
        $s = self::tex(Fixtures::all()[$name]);
        self::assertNotSame('', $s, $name);
        $bare = preg_replace('/\\\\[{}]/', '', $s) ?? '';
        self::assertSame(substr_count($bare, '{'), substr_count($bare, '}'), $name . ': ' . $s);
        self::assertSame(trim($s), $s);
    }

    public function test_symbols(): void
    {
        self::assertSame('\alpha', TexSymbols::command('α'));
        self::assertSame('α', TexSymbols::char('\alpha'));
        self::assertSame('α', TexSymbols::char('alpha'));
        self::assertSame('\leq', TexSymbols::command('≤'));
        self::assertSame('≤', TexSymbols::char('\le'));
        self::assertSame('\neq', TexSymbols::command('≠'));
        self::assertNull(TexSymbols::command('x'));
        self::assertNull(TexSymbols::command('+'));
        self::assertNull(TexSymbols::char('\nosuchcommand'));
        self::assertSame('\{', TexSymbols::delimiter('{'));
        self::assertSame('\langle', TexSymbols::delimiter('⟨'));
        self::assertSame('.', TexSymbols::delimiter(''));
        self::assertNull(TexSymbols::delimiter('x'));
        self::assertSame(['double-struck', 'R'], TexSymbols::unstyle('ℝ'));
        self::assertSame(['bold', 'a'], TexSymbols::unstyle('𝐚'));
        self::assertNull(TexSymbols::unstyle('a'));
    }

    public function test_every_command_round_trips_through_the_tables(): void
    {
        foreach (array_merge(TexSymbols::GREEK, TexSymbols::GREEK_UP, TexSymbols::OPS, TexSymbols::LARGE, TexSymbols::INTS) as $char) {
            $cmd = TexSymbols::command($char);
            if ($cmd !== null) {
                self::assertSame($char, TexSymbols::char($cmd), $char);
            }
        }
    }

    public function test_fix_round_one(): void
    {
        // Findings 1: nothing KaTeX 0.16 rejects.
        self::assertSame('\char"5E', self::tex(new Operator('^')));
        self::assertSame('\char"7E', self::tex(new Operator('~')));
        self::assertSame('⨌', self::tex(new Operator('⨌')));
        self::assertSame('↤', self::tex(new Operator('↤')));
        self::assertSame('\operatorname{sgn}', self::tex(new Identifier('sgn', null, true)));
        self::assertSame('\overset{⃐}{x}', self::tex(new UnderOver(Math::row('x'), null, new Operator("\u{20D0}"), true)));
        // \dddot and \ddddot (newer KaTeX) have their own commands, so they read back as the accents they were.
        self::assertSame('\dddot{x}', self::tex(Math::tex('\dddot{x}')));
        self::assertSame('\ddddot{x}', self::tex(Math::tex('\ddddot{x}')));
        // A fence with no \left spelling keeps its glyphs as plain symbols (corners, and any unknown glyph).
        self::assertSame('\ulcorner x \lrcorner', self::tex(Math::fenced('⌜', '⌟', 'x')));
        self::assertSame('\ulcorner x )', self::tex(Math::fenced('⌜', ')', 'x')));
        self::assertSame('p z q', self::tex(Math::fenced('p', 'q', 'z')));
        self::assertSame('\ulcorner', self::tex(new Operator('⌜')));
        self::assertSame('\ulcorner', self::tex(new Operator('⌜', minSize: 1.8)));
        self::assertSame('\left( a \ulcorner b \right)', self::tex(Math::fenced('(', ')', 'a', Math::middle('⌜'), 'b')));
        self::assertSame('\left( a \middle. b \right)', self::tex(Math::fenced('(', ')', 'a', Math::middle(''), 'b')));
        self::assertSame('\begin{aligned}x &= 1 & &= 2\end{aligned}', self::tex(Math::aligned([['x', '= 1', '', '= 2']])));
        self::assertSame('\sqrt[a \rbrack]{x}', self::tex(Math::root('x', Math::row(new Identifier('a'), new Operator(']')))));
        // 2: brace forms are not atoms before a script.
        self::assertSame('{\overbrace{x}^a}^2', self::tex(Math::sup(Math::overbrace('x', 'a'), '2')));
        self::assertSame('{\overbrace{x}}^n', self::tex(Math::sup(Math::overbrace('x'), 'n')));
        // 3 / R2: adjacent numbers.
        self::assertSame('1 {2}', self::tex(new Row([new Number('1'), new Number('2')])));
        self::assertSame('1 {.5} + 2', self::tex(new Row([new Number('1'), new Number('.5'), new Operator('+'), new Number('2')])));
        // 4 / R3: a pair versus a nested pair.
        self::assertSame('\underset{b}{\overset{a}{x}}', self::tex(Math::underOver('x', 'b', 'a')));
        self::assertSame('\underset{b}{{\overset{a}{x}}}', self::tex(Math::under(Math::over('x', 'a'), 'b')));
        // 5: spacing-free implication arrows.
        self::assertSame('\Longrightarrow', self::tex(new Operator('⟹')));
        self::assertSame('\Longleftarrow', self::tex(new Operator('⟸')));
        self::assertSame('\Longleftrightarrow', self::tex(new Operator('⟺')));
        // 6: exact spacing fractions.
        foreach (['thinspace' => 3 / 18, 'medspace' => 4 / 18, 'thickspace' => 5 / 18, 'negthinspace' => -3 / 18, 'negmedspace' => -4 / 18, 'negthickspace' => -5 / 18, ' ' => 1 / 3] as $k => $em) {
            self::assertEqualsWithDelta($em, TexSymbols::SPACES[$k], 1e-12, (string) $k);
        }
        // 7: a script base made of several groups is braced.
        self::assertSame('{\mathbb{R} \mathrm{x}}^2', self::tex(Math::sup(new Row([Math::styled('R', 'double-struck'), new Identifier('x', false)]), '2')));
        self::assertSame('\left( x \right)^2', self::tex(Math::sup(Math::fenced('(', ')', 'x'), '2')));
    }

    public function test_fix_round_three(): void
    {
        self::assertSame('\ulcorner a | b \lrcorner', self::tex(Math::fenced('⌜', '⌟', 'a', Math::middle('|'), 'b')));
        self::assertSame('{\ulcorner x \lrcorner}^2', self::tex(Math::sup(Math::fenced('⌜', '⌟', 'x'), '2')));
        self::assertSame('{\ulcorner x}^2', self::tex(Math::sup(Math::fenced('⌜', '', 'x'), '2')));
        self::assertSame('\left( x \right)^2', self::tex(Math::sup(Math::fenced('(', ')', 'x'), '2')));
        self::assertSame('\binom{n}{k}^2', self::tex(Math::sup(Math::binom('n', 'k'), '2')));
    }

    public function test_fix_round_one_negated_symbol_without_a_negated_form(): void
    {
        self::assertSame('\\not|', self::tex(new Operator("|\u{0338}")));
        self::assertSame('\\not\\perp', self::tex(new Operator("⊥\u{0338}")));
        foreach (['\\not|', '\\not\\perp'] as $tex) {
            self::assertSame($tex, TexWriter::write(Math::tex($tex)));
        }
    }

    public function test_alignment_without_pair_gap_is_alignedat(): void
    {
        self::assertStringStartsWith('\\begin{alignedat}{2}', self::tex(Math::aligned([['x', '= 1', 'y', '= 2']], 0.0)));
        self::assertStringStartsWith('\\begin{aligned}', self::tex(Math::aligned([['x', '= 1', 'y', '= 2']])));
    }

    public function test_xlongequal_round_trips_and_is_stretchy_in_mathml(): void
    {
        $a = \Papercut\Math\Math::tex('A \xlongequal{x} B');
        self::assertSame('A \xlongequal{x} B', self::tex($a));
        \Papercut\Math\Tests\TreeAssert::assertSameTree($a, \Papercut\Math\Math::tex(self::tex($a)));
        self::assertStringContainsString('<mo stretchy="true">=</mo>', \Papercut\Math\Render\MathmlWriter::write($a));
    }

    public function test_plain_labeled_equals_does_not_become_xlongequal(): void
    {
        foreach ([\Papercut\Math\Math::over('=', 'd'), \Papercut\Math\Math::mathml('<math><mover><mo>=</mo><mi>d</mi></mover></math>')] as $a) {
            $tex = self::tex($a);
            self::assertStringNotContainsString('xlongequal', $tex);
            \Papercut\Math\Tests\TreeAssert::assertSameTree($a, \Papercut\Math\Math::tex($tex));
        }
    }

    public function test_labeled_arrows_round_trip_to_the_same_tree(): void
    {
        $M = \Papercut\Math\Math::class;
        $inputs = [
            $M::over('→', 'f'),
            $M::mathml('<math><mover><mo>→</mo><mi>f</mi></mover></math>'),
            $M::tex('\xrightarrow{f}'),
            $M::tex('\xlongequal{d}'),
        ];
        foreach ($inputs as $a) {
            \Papercut\Math\Tests\TreeAssert::assertSameTree($a, $M::tex(self::tex($a)));
        }
        self::assertStringNotContainsString('stretchy', \Papercut\Math\Render\MathmlWriter::write($M::tex('\xrightarrow{f}')));
        self::assertStringContainsString('stretchy="true"', \Papercut\Math\Render\MathmlWriter::write($M::tex('\xlongequal{d}')));
    }
}
