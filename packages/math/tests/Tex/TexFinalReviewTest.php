<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Tex;

use Papercut\Math\Layout\Context;
use Papercut\Math\Math;
use Papercut\Math\Mathml\MathmlReader;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Middle;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\UnderOver;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\TexWriter;
use Papercut\Math\Tests\TreeAssert;
use Papercut\Math\Tex\TexCursor;
use Papercut\Math\Tex\TexReader;
use Papercut\Math\Tex\TexSymbols;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The fixes of the final whole-branch review of the TeX work. */
final class TexFinalReviewTest extends TestCase
{
    /** @return list<string> "element: reason @where" per note */
    private static function notes(string $tex): array
    {
        return array_map(static fn ($n): string => "$n->element: $n->reason @$n->where", TexReader::read($tex)->notes());
    }

    private static function roundTrip(string $tex): string
    {
        $a = Math::tex($tex);
        $out = TexWriter::write($a);
        TreeAssert::assertSameTree($a, Math::tex($out));

        return $out;
    }

    // M1

    public function test_operatorname_notes_the_spacing_it_cannot_keep(): void
    {
        self::assertSame(
            ['\operatorname: only letters and symbols are kept in the name (not \,) @offset 0'],
            self::notes('\operatorname{arg\,min}_x f'),
        );
        self::assertSame([], self::notes('\operatorname{argmin}_x f'));
    }

    public function test_operatorname_star_with_a_limit_reads_back_the_same_after_writing(): void
    {
        self::assertSame('\underset{x}{\operatorname{argmax}} f', self::roundTrip('\operatorname*{argmax}_x f'));
        self::assertSame('\underset{\theta}{\operatorname{argmax}} f', self::roundTrip('\operatorname*{argmax}_{\theta} f'));
    }

    // M2

    #[DataProvider('mixedVariants')]
    public function test_a_name_in_one_font_group_is_written_as_one_group(string $tex, string $expected): void
    {
        self::assertSame($expected, self::roundTrip($tex));
    }

    /** @return iterable<string, array{string, string}> */
    public static function mixedVariants(): iterable
    {
        yield 'mathcal' => ['\mathcal{Ab1}', '\mathcal{Ab1}'];
        yield 'cal switch' => ['{\cal Ab1}', '\mathcal{Ab1}'];
        yield 'boldsymbol' => ['\boldsymbol{a1}', '\boldsymbol{a1}'];
        yield 'mathfrak' => ['\mathfrak{a1b}', '\mathfrak{a1b}'];
        yield 'leading digit' => ['\mathcal{1Ab}', '\mathcal{1Ab}'];
        yield 'separate groups stay apart' => ['\mathcal{A}\mathrm{1}', '\mathcal{A} \mathrm{1}'];
    }

    // M3

    public function test_a_braced_big_delimiter_keeps_its_ordinary_class(): void
    {
        self::assertSame('a {\bigl(} b', self::roundTrip('a {\bigl(} b'));
        self::assertSame('\xrightarrow[{\bigl(}]{f}', self::roundTrip('\xrightarrow[{\big(}]{f}'));
        self::assertSame('\bigl( x \bigr)', self::roundTrip('\big( x \bigr)'));
    }

    // M4

    #[DataProvider('modulo')]
    public function test_modular_arithmetic_is_written_as_its_command(string $tex, string $expected): void
    {
        self::assertSame($expected, self::roundTrip($tex));
    }

    /** @return iterable<string, array{string, string}> */
    public static function modulo(): iterable
    {
        yield 'bmod' => ['a \bmod n', 'a \bmod n'];
        yield 'pmod' => ['a \equiv b \pmod{n}', 'a \equiv b \pmod{n}'];
        yield 'pod' => ['a \pod{n+1}', 'a \pod{n + 1}'];
        yield 'mod' => ['a \mod{n}', 'a \mod{n}'];
        yield 'alone' => ['\pmod{n}', '\pmod{n}'];
        yield 'a quad and parentheses are not a pod' => ['x \quad (n)', 'x \quad ( n )'];
    }

    // M5

    public function test_one_table_for_extensible_arrows_and_one_for_function_names(): void
    {
        self::assertSame('→', TexSymbols::xarrowChar('xrightarrow'));
        self::assertSame('⇄', TexSymbols::xarrowChar('xtofrom'));
        self::assertNull(TexSymbols::xarrowChar('xnothing'));
        self::assertTrue(TexSymbols::isFunctionCommand('sin'));
        self::assertTrue(TexSymbols::isFunctionCommand('limsup'));
        self::assertFalse(TexSymbols::isFunctionCommand('sgn'));
        self::assertSame('\operatorname{sgn}', TexWriter::write(new Identifier('sgn', null, true)));
        self::assertSame('\liminf', TexWriter::write(new Identifier('liminf', null, true)));
    }

    public function test_one_length_parser_for_the_mathml_attributes(): void
    {
        // minsize takes every length columnspacing does (named spaces here), a bare 0 is no size.
        $r = MathmlReader::read('<math><mrow><mo stretchy="true" minsize="thickmathspace">(</mo><mo minsize="0">)</mo></mrow></math>');
        self::assertCount(1, $r->notes());
        self::assertSame('minsize is not drawn', $r->notes()[0]->reason);
    }

    // M10

    public function test_a_note_from_a_macro_points_at_its_first_use(): void
    {
        self::assertSame(
            ['\color: colour is not drawn @offset 29'],
            self::notes('\newcommand{\c}{\color{red}} \c x \c y \color{blue} z'),
        );
        self::assertSame(['\textcolor: colour is not drawn @offset 0'], self::notes('\textcolor{red}{\textcolor{blue}{x}}'));
    }

    // D1

    public function test_mathml_spacing_survives_tex_to_mathml_to_layout(): void
    {
        $tex = Math::tex('a \mathbin{x} b \mathrel{y} c');
        $back = MathmlReader::read(MathmlWriter::write($tex))->node;
        $width = static fn ($n): float => Math::layout($n, 12.0, false)->width;
        self::assertEqualsWithDelta($width($tex), $width($back), 0.001);
        $op = MathmlReader::read('<math><mo lspace="0.2778em" rspace="0em">x</mo></math>')->node;
        self::assertInstanceOf(Operator::class, $op);
        self::assertEqualsWithDelta(5.0, $op->lspace, 0.01);
        self::assertSame(0.0, $op->rspace);
    }

    // D2

    public function test_a_multi_character_middle_does_not_claim_to_stretch(): void
    {
        self::assertFalse((new Middle('ab', 20.0))->stretches());
        self::assertTrue((new Middle('|', 20.0))->stretches());
        self::assertFalse((new Middle('|'))->stretches());
    }

    // D3

    public function test_equation_says_what_it_merges(): void
    {
        self::assertSame(
            ['\\\\: line break outside an environment @offset 0', '&: alignment tab outside an alignment environment @offset 0'],
            self::notes('\begin{equation} a & b \\\\ c \end{equation}'),
        );
        self::assertSame([], self::notes('\begin{equation} a + b \end{equation}'));
    }

    public function test_a_bad_starred_matrix_option_and_an_unclosed_end_are_noted(): void
    {
        self::assertSame(
            ['pmatrix*: column option must be [l], [c] or [r]; drawn as content @offset 16'],
            self::notes('\begin{pmatrix*}[x] a & b \end{pmatrix*}'),
        );
        self::assertSame([], self::notes('\begin{pmatrix*}[r] a & b \end{pmatrix*}'));
        self::assertSame(
            ['matrix: missing } after \end{matrix @offset 25'],
            self::notes('\begin{matrix} a & b \end{matrix'),
        );
    }

    public function test_a_single_cell_diagram_is_still_a_row(): void
    {
        $node = Math::tex('\begin{CD} A \end{CD}');
        self::assertSame('Papercut\Math\Node\Fallback', $node::class);
        self::assertInstanceOf(\Papercut\Math\Node\Row::class, $node->children()[0]);
    }

    // D4

    public function test_genfrac_notes_a_delimiter_it_does_not_know(): void
    {
        self::assertContains('\genfrac: unrecognised delimiter, drawn without it @offset 0', self::notes('\genfrac\alpha){0pt}{}{a}{b}'));
        self::assertSame([], self::notes('\genfrac(){0pt}{}{a}{b}'));
        self::assertSame([], self::notes('\genfrac.){0pt}{}{a}{b}'));
    }

    // D5, D6

    public function test_bent_arrows_do_not_stretch_over_labels(): void
    {
        foreach (mb_str_split('⬎⬏⬐⬑⮤⮥⮦⮧') as $bent) {
            self::assertFalse(UnderOver::isArrow($bent), $bent);
        }
        self::assertTrue(UnderOver::isArrow('→'));
    }

    public function test_bad_minsize_and_maxsize_are_both_named(): void
    {
        $r = MathmlReader::read('<math><mo minsize="-1em" maxsize="oops">(</mo></math>');
        self::assertEqualsCanonicalizing(['maxsize is not drawn', 'minsize is not drawn'], array_map(static fn ($n): string => $n->reason, $r->notes()));
    }

    // D7

    public function test_a_null_big_delimiter_writes_nothing(): void
    {
        self::assertSame('a b', self::roundTrip('a\big. b'));
    }

    // D8

    public function test_notes_of_a_dropped_trailing_row_come_in_source_order(): void
    {
        self::assertSame(
            ['\\\\: row spacing is not drawn @offset 17', '\color: colour is not drawn @offset 25'],
            self::notes('\begin{matrix} a \\\\[1em] \color{red} \end{matrix}'),
        );
    }

    // D9

    public function test_math_inside_text_inside_an_optional_argument_reads_its_own_brackets(): void
    {
        self::assertSame([], self::notes('\sqrt[\text{$\frac{a}]$}]{x}'));
    }

    // D10, D11

    public function test_note_offsets_are_in_the_callers_bytes_after_invalid_utf8(): void
    {
        $tex = "\u{e9} \xe2\x82 \xff \\foo";
        self::assertSame(8, strpos($tex, '\\foo'));
        $where = array_values(array_filter(TexReader::read($tex)->notes(), static fn ($n): bool => $n->element === '\foo'));
        self::assertSame('offset 8', $where[0]->where);
        self::assertSame('offset 1', TexReader::read("\xff\\foo")->notes()[1]->where);
        $clean = TexReader::read("\u{e9} \\foo")->notes();
        self::assertSame('offset 3', $clean[0]->where);
    }

    public function test_the_end_of_input_is_the_real_end_of_the_source(): void
    {
        $c = new TexCursor([['ch', "\u{e9}", 0]], 2);
        $c->p = 1;
        self::assertSame(2, $c->offset());
    }

    // D13

    public function test_operator_spacing_and_sizes_must_be_finite(): void
    {
        foreach (['lspace', 'rspace', 'minSize', 'maxSize'] as $arg) {
            foreach ([NAN, INF] as $bad) {
                try {
                    new Operator('+', ...[$arg => $bad]);
                    self::fail("$arg $bad");
                } catch (\InvalidArgumentException $e) {
                    self::assertStringContainsString($arg, $e->getMessage());
                }
            }
        }
    }

    public function test_script_style_drops_explicit_spacing_per_side_and_keeps_an_ops(): void
    {
        $sup = static fn (Operator $o): float => Math::layout(Math::sup('x', Math::row('a', $o, 'b')), 12.0, false)->width;
        $none = $sup(new Operator('x', lspace: 0, rspace: 0));
        $c = new Context(Math::stixTwoMath(), 12);
        // A relation's spaces are dropped in a script, an op's (1 mu) stay.
        self::assertEqualsWithDelta($none, $sup(new Operator('x', lspace: 5, rspace: 5)), 0.001);
        self::assertEqualsWithDelta($none + $c->mu(1) * 0.7 * 2, $sup(new Operator('x', lspace: 1, rspace: 1)), 0.2);
        // One side explicit: only that side is zeroed.
        $left = $sup(new Operator('=', lspace: 5));
        $both = $sup(new Operator('=', lspace: 5, rspace: 5));
        self::assertEqualsWithDelta($left, $both, 0.001);
    }

    // D14

    public function test_a_function_names_thin_space_is_trailing_space_scripts_sit_before(): void
    {
        $box = Math::layout(Math::sup(new Identifier('sin', null, true), '2'), 12.0, false);
        $plain = Math::layout(new Identifier('sin', null, true), 12.0, false);
        $c = new Context(Math::stixTwoMath(), 12);
        self::assertEqualsWithDelta($c->mu(3), $plain->trail, 1e-9);
        self::assertEqualsWithDelta($c->mu(3), $box->trail, 1e-9);
        $sinInk = $plain->width - $plain->trail;
        $xs = array_map(static fn ($i): float => $i->x, array_values(array_filter($box->items, static fn ($i): bool => $i instanceof \Papercut\Math\Layout\Glyph)));
        self::assertNotEmpty($xs);
        self::assertEqualsWithDelta($sinInk, $xs[array_key_last($xs)] ?? 0.0, 1.0);
    }
}
