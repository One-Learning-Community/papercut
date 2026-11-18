<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use Quoin\Math\Math;
use Quoin\Math\MathNote;
use Quoin\Math\Node\DisplayStyle;
use Quoin\Math\Node\Fallback;
use Quoin\Math\Node\Fenced;
use Quoin\Math\Node\Fraction;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Number;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Phantom;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Node\Space;
use Quoin\Math\Node\Text;
use Quoin\Math\Node\UnderOver;
use Quoin\Math\NoteLevel;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\TexReader;
use PHPUnit\Framework\TestCase;

final class TexReaderTest extends TestCase
{
    private static function same(Node $expected, string $tex): void
    {
        TreeAssert::assertSameTree($expected, Math::tex($tex));
        self::assertSame([], TexReader::read($tex)->notes(), 'no notes for ' . $tex);
    }

    /** @return list<MathNote> */
    private static function notes(string $tex): array
    {
        return TexReader::read($tex)->notes();
    }

    public function test_result(): void
    {
        $r = TexReader::read('x');
        self::assertFalse($r->display);
        TreeAssert::assertSameTree(new Identifier('x'), $r->node);
        self::assertSame([], $r->notes());
    }

    public function test_fractions_and_roots(): void
    {
        self::same(Math::frac('a', 'b'), '\frac{a}{b}');
        self::same(Math::frac('a', 'b'), '\frac ab');
        self::same(new DisplayStyle(Math::frac('a', 'b'), true), '\dfrac{a}{b}');
        self::same(new DisplayStyle(Math::frac('a', 'b'), false), '\tfrac{a}{b}');
        self::same(new DisplayStyle(Math::frac('a', 'b'), true), '\cfrac{a}{b}');
        self::same(Math::binom('n', 'k'), '\binom{n}{k}');
        self::same(new DisplayStyle(Math::binom('n', 'k'), true), '\dbinom{n}{k}');
        self::same(Math::stack('a', 'b'), '{a \atop b}');
        self::same(Math::frac('a', 'b'), '{a \over b}');
        self::same(Math::binom('n', 'k'), '{n \choose k}');
        self::same(Math::sqrt('x'), '\sqrt{x}');
        self::same(Math::sqrt('x'), '\sqrt x');
        self::same(Math::root('27', '3'), '\sqrt[3]{27}');
        self::same(Math::root('x', Math::row('a', ']')), '\sqrt[{a ]}]{x}');
        self::same(new Fenced('[', ')', new Fraction(Math::row('a'), Math::row('b'), false)), '\genfrac{[}{)}{0pt}{}{a}{b}');
    }

    public function test_leaves(): void
    {
        self::same(Math::row('x + 1 = 2'), 'x + 1 = 2');
        self::same(Math::row('x − 1'), 'x - 1');
        self::same(new Identifier('α'), '\alpha');
        self::same(new Identifier('Γ'), '\varGamma');
        self::same(new Identifier('Γ', false), '\Gamma');
        self::same(new Identifier('∞', false), '\infty');
        self::same(Math::row('a ≤ b'), 'a \leq b');
        self::same(Math::row('a → b'), 'a \to b');
        self::same(new Operator('{'), '\{');
        self::same(new Operator('‖'), '\|');
        self::same(new Operator('^'), '\char"5E');
        self::same(new Operator('~'), '\char"7E');
        self::same(new Operator('%'), '\%');
        self::same(new Operator('⨌'), '⨌');
        self::same(new Row([new Number('3.14')]), '3.14');
        self::same(new Row([new Number('1'), new Number('2')]), '1 {2}');
        self::same(new Row([new Number('1'), new Number('.5'), new Operator('+'), new Number('2')]), '1 {.5} + 2');
        self::same(new Row([new Number('12'), new Operator('+'), new Number('3')]), '12+3');
        self::same(Math::row('1 + 2'), '1 + 2');
    }

    public function test_a_digit_run_leaves_its_last_digit_to_a_script(): void
    {
        // TeX puts the script on the last digit only: 12^3 is 1 and 2^3.
        self::same(new Row([new Number('1'), Math::sup('2', '3')]), '12^3');
        self::same(new Row([new Number('1'), Math::sup('2', '3')]), '1 2^3');
        self::same(Math::sup('12', '3'), '{12}^3');
        self::same(Math::sup('1.5', '2'), '1.5^2');
        self::same(Math::sup('7', '2'), '7^2');
    }

    public function test_scripts_and_primes(): void
    {
        self::same(Math::sup('x', '2'), 'x^2');
        self::same(Math::sub('x', 'i'), 'x_i');
        self::same(Math::subSup('x', '1', '2'), 'x_1^2');
        self::same(Math::subSup('x', '1', '2'), 'x^2_1');
        self::same(Math::sup('e', '−kt'), 'e^{- k t}');
        self::same(Math::sup(Math::sub('x', '1'), '2'), '{x_1}^2');
        self::same(Math::sup(Math::row('a + b'), '2'), '{a + b}^2');
        self::same(Math::sup('x', '′'), "x'");
        self::same(Math::sup('x', '″'), "x''");
        self::same(Math::sup('x', Math::row('′', '2')), "x'^2");
        self::same(Math::sup('x', '′'), 'x^\prime');
        self::same(Math::sup('x', 'α'), 'x^\alpha');
        self::same(Math::sup('x', Math::frac('a', 'b')), 'x^{\frac{a}{b}}');
    }

    public function test_prescripts_from_an_empty_group(): void
    {
        $c = new Identifier('C', false);
        self::same(Math::prescripts($c, '6', '14'), '{}^{14}_{6}\mathrm{C}');
        self::same(Math::prescripts($c, '6', '14'), '{}_{6}^{14}\mathrm{C}');
        self::same(Math::prescripts($c, '6', '14', null, '4+'), '{}^{14}_{6}\mathrm{C}^{4+}');
        self::same(Math::prescripts($c, '6', '14'), '{}^{14}_{6} \mathrm{C}');
    }

    public function test_an_empty_group_with_scripts_and_nothing_to_carry_them_stays_as_scripts(): void
    {
        $n = Math::tex('{}^2');
        self::assertInstanceOf(Scripts::class, $n);
        self::assertNull($n->preSup);
        self::assertSame([], Fallback::unwrap($n->base)->children());
        $r = Math::tex('{}^2 + x');
        self::assertInstanceOf(Row::class, $r);
        self::assertInstanceOf(Scripts::class, $r->children[0]);
        self::assertNull($r->children[0]->preSup);
        self::assertSame([], self::notes('{}^2 + x'));
    }

    public function test_prescript_command(): void
    {
        $c = new Identifier('C', false);
        self::same(Math::prescripts($c, '6', '14'), '\prescript{14}{6}{\mathrm{C}}');
        self::same(Math::prescripts($c, null, '14'), '\prescript{14}{}{\mathrm{C}}');
        self::same(Math::prescripts($c, '6', null), '\prescript{}{6}{\mathrm{C}}');
        self::same(new Row([]), '\prescript{}{}{}');
        self::same(Math::prescripts($c, '6', '14', null, '4+'), '\prescript{14}{6}{\mathrm{C}}^{4+}');
        TreeAssert::assertSameTree(Math::tex('{}^{14}_{6}\mathrm{C}^{4+}'), Math::tex('\prescript{14}{6}{\mathrm{C}}^{4+}'));
    }

    public function test_sideset(): void
    {
        self::same(Math::prescripts(Math::tex('\sum'), 'a', 'b', 'c', 'd'), '\sideset{_a^b}{_c^d}\sum');
        self::same(Math::prescripts(Math::tex('\sum'), null, null, null, '′'), "\\sideset{}{'}\\sum");
        self::same(Math::prescripts(Math::tex('\sum'), 'a', null, null, null), '\sideset{_a}{}\sum');
        self::same(Math::tex('\sum'), '\sideset{}{}\sum');
    }

    public function test_sideset_keeps_the_class_of_its_base(): void
    {
        $r = Math::tex('\sideset{}{\prime}\sum_{n<k} a_n');
        self::assertInstanceOf(Row::class, $r);
        self::assertInstanceOf(UnderOver::class, $r->children[0]);
    }

    public function test_sideset_notes_what_it_cannot_draw(): void
    {
        $n = self::notes('\sideset{x_a}{}\sum');
        self::assertCount(1, $n);
        self::assertSame('\sideset', $n[0]->element);
        self::assertSame(NoteLevel::Dropped, $n[0]->level);
        self::assertCount(1, self::notes('\sideset{x}{y}\sum'));
    }

    public function test_a_space_or_nothing_does_not_carry_prescripts(): void
    {
        foreach (['{}^2\quad x', '{}^2\,x', '{}^2{}'] as $tex) {
            $r = Math::tex($tex);
            self::assertInstanceOf(Row::class, $r, $tex);
            self::assertInstanceOf(Scripts::class, $r->children[0], $tex);
            self::assertNull($r->children[0]->preSup, $tex);
            self::assertNotInstanceOf(Scripts::class, $r->children[1] ?? null, $tex);
        }
    }

    public function test_consecutive_empty_groups_each_take_their_turn(): void
    {
        $r = Math::tex('{}^a{}^b C');
        self::assertInstanceOf(Row::class, $r);
        self::assertCount(2, $r->children);
        self::assertInstanceOf(Scripts::class, $r->children[0]);
        self::assertSame([], Fallback::unwrap($r->children[0]->base)->children());
        TreeAssert::assertSameTree(Math::prescripts('C', null, 'b'), $r->children[1]);
    }

    public function test_scripts_on_a_group_stay_as_they_were(): void
    {
        self::same(Math::sup(Math::row('a + b'), '2'), '{a + b}^2');
    }

    public function test_a_double_superscript_gives_a_note(): void
    {
        $notes = self::notes('x^1^2');
        self::assertCount(1, $notes);
        self::assertSame('^', $notes[0]->element);
        self::assertSame('double superscript', $notes[0]->reason);
        self::assertCount(1, self::notes('x_1_2'));
    }

    public function test_functions_and_operatorname(): void
    {
        self::same(new Identifier('sin', false, true), '\sin');
        self::same(Math::sup(new Identifier('sin', false, true), '2'), '\sin^2');
        self::same(new UnderOver(new Identifier('lim', false, true), Math::row('x → 0'), null), '\lim_{x \to 0}');
        self::same(new Scripts(new Identifier('lim', false, true), Math::row('x'), null), '\lim\nolimits_x');
        self::same(new Identifier('rank', false, true), '\operatorname{rank}');
        self::same(Math::sub(new Identifier('rank', false, true), 'n'), '\operatorname{rank}_n');
        self::same(new UnderOver(new Identifier('argmax', false, true), Math::row('x'), null), '\operatorname*{argmax}_x');
        self::same(new Identifier('sgn', false, true), '\operatorname{sgn}');
        self::same(new Row([new Identifier('sin', false, true), new Identifier('x')]), '\sin x');
    }

    public function test_large_operators_and_limits(): void
    {
        self::same(new Operator('∑'), '\sum');
        self::same(Math::sum('i = 1', 'n'), '\sum_{i = 1}^n');
        self::same(Math::product(null, 'n'), '\prod^n');
        self::same(new Scripts(new Operator('∑'), Math::row('i = 1'), Math::row('n')), '\sum\nolimits_{i = 1}^n');
        self::same(Math::integral('0', '1'), '\int_0^1');
        self::same(new UnderOver(new Operator('∫'), Math::row('0'), Math::row('1')), '\int\limits_0^1');
        self::same(Math::sup(Math::sum('i'), '2'), '{\sum_i}^2');
    }

    public function test_nolimits_lays_out_the_display_size_operator(): void
    {
        $node = Math::tex('\sum\nolimits_{i}');
        self::assertInstanceOf(Scripts::class, $node);
        // \nolimits only moves the limits beside the sign: the sign itself is still the display-size variant.
        $box = Math::layout($node->base, 12.0, true);
        $small = Math::layout($node->base, 12.0, false);
        self::assertGreaterThan($small->ascent + $small->descent, $box->ascent + $box->descent);
    }

    public function test_fonts(): void
    {
        self::same(Math::styled('R', 'double-struck'), '\mathbb{R}');
        self::same(Math::styled('AB', 'double-struck'), '\mathbb{AB}');
        self::same(Math::styled('v', 'bold'), '\mathbf{v}');
        self::same(Math::styled('L', 'script'), '\mathcal{L}');
        self::same(Math::styled('g', 'fraktur'), '\mathfrak{g}');
        self::same(Math::styled('x', 'bold-italic'), '\boldsymbol{x}');
        self::same(Math::styled('1', 'double-struck'), '\mathbb{1}');
        self::same(Math::styled('x', 'sans-serif'), '\mathsf x');
        self::same(Math::styled('h', 'italic'), '\mathit{h}');
        self::same(new Identifier('d', false), '\mathrm{d}');
        self::same(new Identifier('ab', false), '\mathrm{ab}');
        self::same(new Identifier('α', false), '\mathrm{\alpha}');
        self::same(new Identifier('ab', true), '\mathit{ab}');
        self::same(new Row([Math::styled('a', 'bold'), Math::styled('b', 'bold')]), '\mathbf{a} \mathbf{b}');
        self::same(new Row([Math::styled('a', 'bold'), new Operator('+'), Math::styled('b', 'bold')]), '\mathbf{a + b}');
        self::same(new Row([Math::styled('a', 'bold'), new Identifier('b')]), '{\bf a} b');
    }

    public function test_text(): void
    {
        self::same(Math::text('density'), '\text{density}');
        self::same(Math::text('if x'), '\text{if x}');
        self::same(Math::text(''), '\text{}');
        self::same(Math::text('\\ { } $ & # ^ _ % ~'), '\text{\textbackslash{} \{ \} \$ \& \# \textasciicircum{} \_ \% \textasciitilde{}}');
        self::same(Math::text('hi'), '\mbox{hi}');
        self::same(Math::text('hi'), '\textrm{hi}');
        self::same(new Row([new Text('for '), new Identifier('x'), new Text(' small')]), '\text{for $x$ small}');
        $notes = self::notes('\textbf{hi}');
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
    }

    public function test_spaces_and_phantoms(): void
    {
        self::same(Math::space(3 / 18), '\,');
        self::same(Math::space(4 / 18), '\:');
        self::same(Math::space(5 / 18), '\;');
        self::same(Math::space(-3 / 18), '\!');
        self::same(Math::space(1), '\quad');
        self::same(Math::space(2), '\qquad');
        self::same(Math::space(0.37), '\hspace{0.37em}');
        self::same(Math::space(-0.5), '\hspace{-0.5em}');
        self::same(Math::space(3 / 18), '\mkern3mu');
        self::same(Math::space(3 / 18), '\mskip 3mu');
        self::same(Math::space(0.2), '\kern 2pt');
        self::same(new Space(1 / 3), '\ ');
        self::same(Math::phantom('x'), '\phantom{x}');
        self::same(new Row([Math::phantom('−'), new Number('3')]), '\phantom{-} 3');
    }

    public function test_accents_and_over_under(): void
    {
        self::same(new UnderOver(new Identifier('v'), null, new Operator("\u{0302}", stretchy: false), true), '\hat{v}');
        self::same(Math::hat('x'), '\widehat{x}');
        self::same(Math::tilde('x'), '\widetilde{x}');
        self::same(Math::vec('v'), '\overrightarrow{v}');
        self::same(new UnderOver(new Identifier('v'), null, new Operator("\u{20D7}", stretchy: false), true), '\vec{v}');
        self::same(Math::dot('x'), '\dot{x}');
        self::same(Math::accent('x', "\u{0304}"), '\bar x');
        self::same(Math::overline('AB'), '\overline{A B}');
        self::same(Math::underline('AB'), '\underline{A B}');
        self::same(Math::overbrace('a + b'), '\overbrace{a + b}');
        self::same(Math::overbrace('a + b', 'n'), '\overbrace{a + b}^n');
        self::same(Math::underbrace('a + b', 'n + 1'), '\underbrace{a + b}_{n + 1}');
        self::same(Math::sup(Math::overbrace('x', 'a'), '2'), '{\overbrace{x}^a}^2');
        self::same(Math::over('x', 'a'), '\overset{a}{x}');
        self::same(Math::over('x', 'a'), '\stackrel{a}{x}');
        self::same(Math::under('x', 'a'), '\underset{a}{x}');
        self::same(Math::underOver('x', 'b', 'a'), '\underset{b}{\overset{a}{x}}');
        self::same(Math::under(Math::over('x', 'a'), 'b'), '\underset{b}{{\overset{a}{x}}}');
        self::same(new UnderOver(new Operator('→', stretchy: false), null, Math::row('a')), '\overset{a}{\to}');
    }

    public function test_operator_classes(): void
    {
        self::same(new Operator('=', lspace: 5, rspace: 5), '\mathrel{=}');
        self::same(new Operator('+', lspace: 4, rspace: 4), '\mathbin{+}');
        self::same(new Operator(',', lspace: 0, rspace: 3), '\mathpunct{,}');
        self::same(new Operator('x', lspace: 1, rspace: 1), '\mathop{x}');
        self::same(new Operator('x', lspace: 5, rspace: 5), '\mathrel{x}');
        self::same(new Operator('(', lspace: 0, rspace: 0), '\mathopen{(}');
        self::same(new Operator(')', lspace: 0, rspace: 0), '\mathclose{)}');
        self::same(new Operator('x', lspace: 0, rspace: 0), '\mathord{x}');
        self::same(new Row([new Identifier('a'), new Operator('+', lspace: 0, rspace: 0), new Identifier('b')]), 'a {+} b');
        // An argument is not an atom: no class.
        self::same(Math::frac('+', 'b'), '\frac{+}{b}');
        self::same(Math::sup('x', '+'), 'x^{+}');
    }

    public function test_display_style(): void
    {
        self::same(new DisplayStyle(Math::frac('a', 'b'), true), '{\displaystyle \frac{a}{b}}');
        self::same(new DisplayStyle(Math::frac('a', 'b'), false), '{\textstyle \frac{a}{b}}');
    }

    public function test_comments_whitespace_and_groups(): void
    {
        self::same(Math::row('a + b'), "a % a comment\n + b");
        self::same(Math::row('a b'), "  a \n\t b  ");
        self::same(new Row([new Identifier('a'), new Row([new Identifier('b'), new Identifier('c')])]), 'a {b c}');
        self::same(new Row([]), '');
        self::same(new Row([]), '{}');
    }

    public function test_unknown_command(): void
    {
        $node = Math::tex('\foo');
        self::assertInstanceOf(Fallback::class, $node);
        self::assertSame(NoteLevel::Dropped, $node->note->level);
        self::assertSame('\foo', $node->note->element);
        self::assertSame('unknown command', $node->note->reason);
        self::assertSame('offset 0', $node->note->where);
        self::assertInstanceOf(Text::class, $node->content);
        self::assertSame('\foo', $node->content->text);
        // One note per command, wherever it appears.
        self::assertCount(1, self::notes('\foo + \foo'));
        self::assertCount(2, self::notes('\foo + \baz'));
        $second = self::notes('x + \foo');
        self::assertSame('offset 4', $second[0]->where);
    }

    public function test_never_throws(): void
    {
        foreach (['{', '}', '^', '_', '\\', '\frac', '\frac{a', 'x^', '\sqrt[', '\text{abc', '\text{$x', '\hspace{', "\xff\xfe", '&', '\\\\', '#1', '\mathbf'] as $tex) {
            self::assertInstanceOf(Node::class, Math::tex($tex), $tex);
        }
    }

    public function test_invalid_utf8_is_noted(): void
    {
        $notes = self::notes("a\xffb");
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
    }

    public function test_fix_round_one_repeated_scripts_stay_flat(): void
    {
        // The runs of bare scripts also miss their last argument.
        foreach ([['x' . str_repeat('^', 16000), 2], ['x' . str_repeat('_', 16000), 2], ['x' . str_repeat('^1', 8000), 1]] as [$tex, $notes]) {
            $r = TexReader::read($tex);
            self::assertInstanceOf(Scripts::class, $r->node instanceof Fallback ? $r->node->content : $r->node);
            self::assertCount($notes, $r->notes());
            unset($r);
        }
    }

    public function test_fix_round_one_control_symbols_do_not_stop_groups(): void
    {
        self::same(Math::frac(Math::row('{ a }'), 'b'), '\frac{\{ a \}}{b}');
        self::same(Math::sup('x', Math::row('{ 1 }')), 'x^{\{ 1 \}}');
        self::same(new Row([new Operator('$'), new Identifier('x')]), '\text{$\$x$}');
        $tex = \Quoin\Math\Render\TexWriter::write(Math::frac(Math::row('{ a }'), 'b'));
        TreeAssert::assertSameTree(Math::frac(Math::row('{ a }'), 'b'), Math::tex($tex));
        $tex = \Quoin\Math\Render\TexWriter::write(Math::sup('x', Math::row('{ 1 }')));
        TreeAssert::assertSameTree(Math::sup('x', Math::row('{ 1 }')), Math::tex($tex));
    }

    public function test_fix_round_one_offsets_are_bytes(): void
    {
        $notes = self::notes('α + \foo');
        self::assertSame('offset 5', $notes[0]->where);
    }

    public function test_fix_round_one_font_single_argument(): void
    {
        self::same(new Row([Math::styled('α', 'bold'), new Identifier('b')]), '\mathbf\alpha b');
    }

    public function test_fix_round_one_nbsp_is_content_in_text(): void
    {
        self::same(Math::text("a\u{00A0}b"), "\\text{a\u{00A0}b}");
        self::same(Math::row('a b'), "a\u{00A0}b");
    }

    public function test_fix_round_one_trailing_point(): void
    {
        self::same(new Number('1.'), '1.');
        self::same(new Row([new Number('1.'), new Number('5')]), '1. {5}');
        self::same(new Row([new Number('1.5'), new Operator('+'), new Number('2.')]), '1.5 + 2.');
    }

    public function test_fix_round_one_prime_after_script_is_a_double_superscript(): void
    {
        $notes = self::notes("x^2'");
        self::assertCount(1, $notes);
        self::assertSame('double superscript', $notes[0]->reason);
        self::assertCount(0, self::notes("x'^2"));
    }

    public function test_fix_round_one_unbraced_genfrac_delimiters(): void
    {
        self::same(new DisplayStyle(new Fenced('(', ')', new Fraction(Math::row('a'), Math::row('b'))), true), '\genfrac(){1pt}{0}ab');
        self::same(new Fenced('⟨', '⟩', new Fraction(Math::row('a'), Math::row('b'), false)), '\genfrac\langle\rangle{0pt}{}ab');
    }

    public function test_fix_round_one_bad_char_code_gives_a_note(): void
    {
        $notes = self::notes('\char"1 x');
        self::assertCount(1, $notes);
        self::assertSame('\char', $notes[0]->element);
        self::assertCount(1, self::notes('\char x'));
    }

    public function test_left_middle_right(): void
    {
        self::same(Math::fenced('{', '}', 'x', Math::middle('|'), 'x > 0'), '\left\{ x \middle| x>0 \right\}');
        self::same(Math::fenced('(', '', 'x'), '\left( x \right.');
        self::same(Math::fenced('', ')', 'x'), '\left. x \right)');
        self::same(Math::fenced('(', ')', 'a', Math::middle('|'), 'b', Math::middle('‖'), 'c'), '\left(a\middle|b\middle\|c\right)');
        self::same(Math::fenced('⟨', '⟩', 'x'), '\left\langle x \right\rangle');
    }

    public function test_fence_takes_scripts_and_nests(): void
    {
        self::same(new Scripts(Math::fenced('(', ')', 'x'), null, new Number('2')), '\left( x \right)^2');
        self::same(Math::fenced('[', ']', Math::fenced('(', ')', 'x')), '\left[ \left( x \right) \right]');
    }

    public function test_stray_middle_and_right_are_plain_with_notes(): void
    {
        $n = self::notes('a \middle| b');
        self::assertCount(1, $n);
        self::assertSame(NoteLevel::Approximated, $n[0]->level);
        self::assertSame('\middle outside \left…\right', $n[0]->reason);
        $kids = self::childrenOf(Math::tex('a \middle| b'));
        self::assertInstanceOf(Fallback::class, $kids[1]);
        TreeAssert::assertSameTree(new Operator('|'), $kids[1]->content ?? new Row([]));
        $n = self::notes('x \right)');
        self::assertCount(1, $n);
        self::assertStringContainsString('\right', $n[0]->element);
    }

    public function test_missing_right_gives_a_note(): void
    {
        $tree = Math::tex('\left( x');
        $n = self::notes('\left( x');
        self::assertCount(1, $n);
        self::assertSame('missing \right', $n[0]->reason);
        self::assertInstanceOf(Fallback::class, $tree);
        TreeAssert::assertSameTree(Math::fenced('(', '', 'x'), $tree->content ?? new Row([]));
        // A group closes the fence early rather than being eaten.
        self::assertCount(1, self::notes('{\left( x} y'));
    }

    public function test_nesting_well_past_the_old_limit_reads_without_a_note(): void
    {
        // Each \left( x^{ unit is two levels; 100 units is 200 levels, over the old limit of 48.
        $tex = str_repeat('\left(x^{', 100) . 'x' . str_repeat('}\right)', 100);
        self::assertSame([], self::notes($tex));
    }

    public function test_deep_left_nesting_is_bounded(): void
    {
        $tex = str_repeat('\left( ', 1000) . 'x' . str_repeat(' \right)', 1000);
        $n = self::notes($tex);
        self::assertNotEmpty($n);
        self::assertSame('nesting', $n[0]->element);
        self::assertSame('nested too deeply', $n[0]->reason);
    }

    public function test_fix_round_one_empty_xarrow_does_not_throw(): void
    {
        foreach (['\xrightarrow{}', 'a\xrightarrow{}b', '\xlongequal{}', '\xrightarrow[{}]{ }', '\xrightarrow[]{}'] as $tex) {
            self::same($tex === '\xlongequal{}' ? new Operator('=') : (str_starts_with($tex, 'a') ? Math::row('a', new Operator('→'), 'b') : new Operator('→')), $tex);
        }
        foreach (['\xrightarrow', '\xrightarrow['] as $tex) {
            $n = self::notes($tex);
            self::assertCount(1, $n, $tex);
            self::assertSame('missing argument', $n[0]->reason);
        }
    }

    public function test_fix_round_one_delimiter_notes_name_their_command(): void
    {
        $n = self::notes('\left');
        self::assertSame('\left', $n[0]->element);
        self::assertSame('offset 0', $n[0]->where);
        $n = self::notes('\left( x \right');
        self::assertCount(1, $n);
        self::assertSame('\right', $n[0]->element);
        self::assertSame('offset 9', $n[0]->where);
    }

    public function test_fix_round_one_top_level_row_break_does_not_end_a_fence(): void
    {
        foreach (self::notes('\left( a \\\\ b \right)') as $note) {
            self::assertStringNotContainsString('\right', $note->reason);
        }
        self::assertCount(1, self::notes('\left( a \\\\ b \right)'));
    }

    public function test_fix_round_one_null_big_delimiter(): void
    {
        self::same(new Row([]), '\big.');
        self::same(new Row([]), '\Bigl.');
        self::assertCount(1, self::notes('\big'));
    }

    public function test_fix_round_one_unclosed_left_stops_at_the_optional_bracket(): void
    {
        $tree = Math::tex('\sqrt[\left( a]{b}');
        self::assertInstanceOf(\Quoin\Math\Node\Root::class, $tree);
        TreeAssert::assertSameTree(Math::row('b'), $tree->radicand);
    }

    public function test_fix_round_one_mod(): void
    {
        self::same(new Row([new Space(1.0), new Identifier('mod', false, true), new Space(6 / 18), new Identifier('n')]), '\mod{n}');
    }

    public function test_big_family(): void
    {
        self::same(Math::big('(', 2), '\Bigl(');
        self::same(Math::big(')', 1), '\bigr)');
        self::same(Math::big('|', 3), '\bigg|');
        self::same(Math::big('|', 4), '\Biggm\vert');
        self::same(Math::big('{', 1), '\big\{');
        self::same(Math::big('⟨', 2), '\Big\langle');
    }

    public function test_extensible_arrows(): void
    {
        self::same(Math::xarrow('→', 'f', Math::row('n → ∞')), '\xrightarrow[n\to\infty]{f}');
        self::same(Math::xarrow('→', 'f'), '\xrightarrow{f}');
        self::same(Math::xarrow('→', null, 'u'), '\xrightarrow[u]{}');
        self::same(Math::xarrow('⇔', 'a'), '\xLeftrightarrow{a}');
        self::same(Math::xarrow('=', 'a'), '\xlongequal{a}');
        self::same(Math::xarrow('↦', 'a'), '\xmapsto{a}');
    }

    public function test_not(): void
    {
        self::same(new Operator('≠'), '\not=');
        self::same(new Operator('∉'), '\not\in');
        self::same(new Operator("‖\u{0338}"), '\not\|');
    }

    public function test_mods(): void
    {
        $pmod = Math::tex('\pmod{n}');
        $spaces = 0;
        foreach (self::childrenOf($pmod) as $c) {
            $spaces += $c instanceof Space ? 1 : 0;
        }
        self::assertSame(2, $spaces); // the leading quad and exactly one space after mod
        self::assertSame([], self::notes('a \bmod b \pod{n}'));
    }

    /** @return list<Node> */
    private static function childrenOf(Node $n): array
    {
        return $n instanceof Row ? $n->children : [$n];
    }

    public function test_lt_and_gt_are_the_symbols_and_the_angle_delimiters(): void
    {
        TreeAssert::assertSameTree(Math::tex('a < b > c'), Math::tex('a \lt b \gt c'));
        TreeAssert::assertSameTree(Math::tex('\left\langle x \right\rangle'), Math::tex('\left\lt x \right\gt'));
        self::assertSame([], TexReader::read('\left\lt x \right\gt')->notes());
    }

    /** @return iterable<string, array{string, list<array{string, string}>, string}> */
    public static function textCommands(): iterable
    {
        yield 'symbol accents, one note each' => ["\\text{caf\\'e na\\\"ive}", [["\\\"", 'Approximated'], ["\\'", 'Approximated']], 'cafe naive'];
        yield 'letter accents' => ['\text{\u{a} \v{c} \H{o} \r{a}}', [['\H', 'Approximated'], ['\r', 'Approximated'], ['\u', 'Approximated'], ['\v', 'Approximated']], 'a c o a'];
        yield 'more letter accents' => ['\text{\c{c} \k{a} \d{a} \b{a}}', [['\b', 'Approximated'], ['\c', 'Approximated'], ['\d', 'Approximated'], ['\k', 'Approximated']], 'c a a a'];
        yield 'unknown command is drawn and dropped' => ['\text{\foo bar}', [['\foo', 'Dropped']], '\foobar'];
        yield 'styles keep the text' => ['\text{x \textbf{y} \emph{z}}', [['\emph', 'Approximated'], ['\textbf', 'Approximated']], 'x y z'];
        yield 'plain' => ['\text{plain words \% \textrm{roman}}', [], 'plain words % roman'];
    }

    /**
     * @param list<array{string, string}> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('textCommands')]
    public function test_text_mode_commands_are_kept_or_noted(string $tex, array $expected, string $drawn): void
    {
        $r = TexReader::read($tex);
        $notes = array_map(static fn ($n): array => [$n->element, $n->level->name], $r->notes());
        sort($notes);
        self::assertSame($expected, $notes);
        $text = Fallback::unwrap($r->node);
        self::assertSame($drawn, \Quoin\Math\Render\TextWriter::write($text));
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingArguments(): iterable
    {
        yield 'frac' => ['\frac{a}', '\frac'];
        yield 'frac with nothing' => ['\frac', '\frac'];
        yield 'superscript' => ['x^', '^'];
        yield 'subscript' => ['x_', '_'];
        yield 'sqrt' => ['\sqrt', '\sqrt'];
        yield 'macro' => ['\\newcommand{\f}[2]{#1#2} \f{a}', '\f'];
        yield 'nested names the outer command' => ['\frac{\sqrt{x}}', '\frac'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missingArguments')]
    public function test_missing_argument_is_noted(string $tex, string $element): void
    {
        $notes = TexReader::read($tex)->notes();
        self::assertCount(1, $notes, $tex);
        self::assertSame([$element, NoteLevel::Approximated, 'missing argument'], [$notes[0]->element, $notes[0]->level, $notes[0]->reason]);
        self::assertSame([], TexReader::read('\frac{a}{b} \sqrt{x} x^2 \frac{}{}')->notes());
    }

    public function test_row_spacing_is_noted(): void
    {
        $notes = TexReader::read('\begin{aligned} a &= b \\\\[4pt] c &= d \\\\[1em] e &= f \end{aligned}')->notes();
        self::assertCount(1, $notes);
        self::assertSame(['\\\\', NoteLevel::Approximated, 'row spacing is not drawn'], [$notes[0]->element, $notes[0]->level, $notes[0]->reason]);
        self::assertCount(2, TexReader::read('a \\\\[2pt] b')->notes()); // and the break itself
        self::assertSame([], TexReader::read('\begin{aligned} a &= b \\\\ c &= d \end{aligned}')->notes());
    }

    public function test_dashed_column_rules_are_noted_like_rules(): void
    {
        $notes = TexReader::read('\begin{array}{c:c} a & b \end{array}')->notes();
        self::assertSame([['array', NoteLevel::Dropped]], array_map(static fn ($n): array => [$n->element, $n->level], $notes));
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingAtAGroupEdge(): iterable
    {
        yield 'sqrt of a short frac' => ['\sqrt{\frac{1}} + x', '\frac'];
        yield 'group' => ['{\frac{a}}', '\frac'];
        yield 'font group' => ['\mathrm{\frac{a}}', '\frac'];
        yield 'cell frac' => ['\begin{matrix} \frac{a} & b \end{matrix}', '\frac'];
        yield 'cell script' => ['\begin{matrix} x^ & b \end{matrix}', '^'];
        yield 'row end sqrt' => ['\begin{matrix} \sqrt \\\\ b \end{matrix}', '\sqrt'];
        yield 'group script' => ['{x^} + y', '^'];
        yield 'cell frac then cell' => ['\begin{aligned} a &= \frac{1} & b \end{aligned}', '\frac'];
        yield 'before right' => ['\left( \frac{a} \right)', '\frac'];
        yield 'macro at a group end' => ['\newcommand{\f}[1]{(#1)} {\f} b', '\f'];
        yield 'macro at a cell end' => ['\newcommand{\f}[1]{(#1)} \begin{matrix} \f & b \end{matrix}', '\f'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missingAtAGroupEdge')]
    public function test_missing_argument_at_a_group_edge_is_noted(string $tex, string $element): void
    {
        $notes = TexReader::read($tex)->notes();
        self::assertSame([$element], array_map(static fn ($n): string => $n->element, $notes), $tex);
        self::assertSame('missing argument', $notes[0]->reason);
    }

    /** @return iterable<string, array{string}> */
    public static function argumentsPresent(): iterable
    {
        foreach (['\sqrt\frac12', '\sqrt 2', 'x^\alpha', 'x^\prime', 'x_\text{max}', 'x^\mathbf{v}', '\hat\alpha', 'x^{}', '\frac{}{}', '\sqrt[3]{x}', 'x^\,', '\xrightarrow[n]{}',
            '\newcommand{\f}[2][a]{#1#2} \f{b} \f[c]{d}', '{\frac12}', '\begin{matrix} \frac12 & x^2 \\ \sqrt 3 & b \end{matrix}'] as $tex) {
            yield $tex => [$tex];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('argumentsPresent')]
    public function test_present_arguments_stay_silent(string $tex): void
    {
        self::assertSame([], array_map(static fn ($n): string => $n->element . ': ' . $n->reason, TexReader::read($tex)->notes()), $tex);
    }

    public function test_prime_commands_and_apostrophes_are_one_prime_symbol(): void
    {
        TreeAssert::assertSameTree(Math::tex("f''"), Math::tex('f^{\prime\prime}'));
        TreeAssert::assertSameTree(Math::tex("f'''"), Math::tex('f^{\prime \prime \prime}'));
        TreeAssert::assertSameTree(Math::tex("f''"), Math::tex("f^{\\prime'}"));
        TreeAssert::assertSameTree(Math::tex("f'"), Math::tex('f^\prime'));
        self::assertSame("f''", \Quoin\Math\Render\TexWriter::write(Math::tex('f^{\prime\prime}')));
    }

    public function test_dotless_letters_in_text(): void
    {
        self::assertSame([['\u', 'Approximated']], array_map(static fn ($n): array => [$n->element, $n->level->name], TexReader::read('\text{\u{\i}}')->notes()));
        self::assertSame('ıȷ', \Quoin\Math\Render\TextWriter::write(Fallback::unwrap(Math::tex('\text{\i\j}'))));
    }

    public function test_prime_is_not_folded_when_it_is_an_argument(): void
    {
        foreach (["x'\\prime", 'x^\\prime\\prime', '\\frac\\prime\\prime', '\\lim\\nolimits\'\' x', "f\u{2033}"] as $src) {
            $a = Math::tex($src);
            TreeAssert::assertSameTree($a, Math::tex(\Quoin\Math\Render\TexWriter::write($a)));
        }
        self::assertSame([], TexReader::read('\\frac\\prime\\prime')->notes());
        $sup = Math::tex('x^\\prime\\prime');
        self::assertInstanceOf(Row::class, $sup); // a superscript prime, then a prime on the baseline
        self::assertCount(2, $sup->children);
        self::assertSame('x^\\prime \\prime', \Quoin\Math\Render\TexWriter::write(Math::tex("x'\\prime")));
    }

    public function test_square_bracket_ends_the_arguments_inside_an_optional_argument(): void
    {
        $notes = TexReader::read('\\sqrt[\\frac{a}]{x}')->notes();
        self::assertSame([['\\frac', 'missing argument']], array_map(static fn ($n): array => [$n->element, $n->reason], $notes));
        self::assertSame('\\sqrt[\\frac{a}{}]{x}', \Quoin\Math\Render\TexWriter::write(Math::tex('\\sqrt[\\frac{a}]{x}')));
        self::assertSame([], TexReader::read('\\sqrt[3]{x} \\sqrt[{\\frac{a}{]}}]{x} \\frac{a}{b}')->notes());
    }
}
