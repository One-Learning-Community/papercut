<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Tex;

use Papercut\Math\Render\TexWriter;
use Papercut\Math\Tests\TreeAssert;
use Papercut\Math\Tex\TexReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Reader and writer corners: null delimiters as arguments, ] in labels, control space, KaTeX-safe spellings. */
final class SmallFixesTest extends TestCase
{
    #[DataProvider('nullDelimiters')]
    public function test_a_null_delimiter_is_a_valid_empty_argument(string $tex, string $written): void
    {
        $r = TexReader::read($tex);
        self::assertSame([], $r->notes());
        self::assertSame($written, TexWriter::write($r->node));
    }

    /** @return iterable<string, array{string, string}> */
    public static function nullDelimiters(): iterable
    {
        yield 'script' => ['x^\big.', 'x^{}'];
        yield 'sqrt' => ['\sqrt\big.', '\sqrt{}'];
        yield 'frac' => ['\frac\big.\big.', '\frac{}{}'];
        yield 'in a row' => ['a\big. b', 'a b'];
    }

    public function test_a_missing_script_argument_is_still_noted(): void
    {
        self::assertNotSame([], TexReader::read('x^')->notes());
    }

    /** @return array<string, array{string}> */
    public static function stable(): array
    {
        $out = [];
        foreach ([
            '\xleftarrow[\Bigr]]{}', '\sqrt[\text{$]$}]{x}', '\xleftarrow[\xleftarrow[x]{}]{}', '\begin{pmatrix}~&1\\\\2&~\end{pmatrix}', '\xleftarrow[a\rbrack b]{}', '\sqrt[\left[x\right]]{y}',
            '\operatorname*{argmax}_{\_}', '\xleftarrow[\_]{}', 'x_{\_}', '\underline{x}', 'a\ b', '\ ', 'a\ ', 'x^{\ }',
            '\xleftarrow[{\displaystyle a}{b}]{}', '\sqrt[{\textstyle a} b]{x}', '{\displaystyle \lim}_i', '{\textstyle \sum}^2_i',
            '\operatorname*{liminf}_x^2', '2\mathrm{Fe}', '\mathrm{Fe}(\mathrm{CN})_6', '\mathrm{sin} x', '\operatorname{foo}', '\bmod', '\operatorname*{foo}_x', '\lim_x',
        ] as $tex) {
            $out[$tex] = [$tex];
        }

        return $out;
    }

    #[DataProvider('stable')]
    public function test_write_read_write_is_stable_and_the_tree_equal(string $tex): void
    {
        $a = TexReader::read($tex);
        $w = TexWriter::write($a->node);
        $b = TexReader::read($w);
        self::assertSame([], $b->notes(), $w);
        TreeAssert::assertSameTree($a->node, $b->node);
        self::assertSame($w, TexWriter::write($b->node));
    }

    public function test_a_closing_bracket_in_a_label_is_not_braced(): void
    {
        self::assertSame('\xleftarrow[\Bigr\rbrack]{}', TexWriter::write(TexReader::read('\xleftarrow[\Bigr]]{}')->node));
        // A nested optional argument is braced, so KaTeX does not end the label at its `]`.
        self::assertSame('\sqrt[{\sqrt[3]{x}}]{y}', TexWriter::write(TexReader::read('\sqrt[\sqrt[3]{x}]{y}')->node));
        self::assertSame('\begin{pmatrix}\  & 1 \\\\ 2 & \ \end{pmatrix}', TexWriter::write(TexReader::read('\begin{pmatrix}~&1\\\\2&~\end{pmatrix}')->node));
        self::assertStringNotContainsString('{{', TexWriter::write(TexReader::read('\sqrt[\text{$]$}]{x}')->node));
    }

    public function test_an_underscore_under_part_is_not_an_underline(): void
    {
        self::assertStringNotContainsString('underline', TexWriter::write(TexReader::read('\xleftarrow[\_]{}')->node));
        self::assertStringNotContainsString('underline', TexWriter::write(TexReader::read('\operatorname*{argmax}_{\_}')->node));
        self::assertSame('\underline{x}', TexWriter::write(TexReader::read('\underline{x}')->node));
    }

    public function test_control_space_is_written_as_control_space(): void
    {
        self::assertSame('a \  b', TexWriter::write(TexReader::read('a\ b')->node));
        self::assertSame('\ ', TexWriter::write(TexReader::read('\ ')->node));
    }

    public function test_katex_safe_spellings(): void
    {
        self::assertSame('\xleftarrow[{{\displaystyle a} b}]{}', TexWriter::write(TexReader::read('\xleftarrow[{\displaystyle a}{b}]{}')->node));
        self::assertSame('{{\displaystyle \lim}}_i', TexWriter::write(TexReader::read('{\displaystyle \lim}_i')->node));
    }

    public function test_underscored_names_use_underset_unless_they_take_limits(): void
    {
        self::assertSame('\underset{x}{\operatorname{foo}}', TexWriter::write(TexReader::read('\operatorname*{foo}_x')->node));
        self::assertSame('\liminf_x^2', TexWriter::write(TexReader::read('\operatorname*{liminf}_x^2')->node));
    }

    public function test_function_names_and_plain_upright_names_write_differently(): void
    {
        self::assertSame('\mathrm{Fe}', TexWriter::write(TexReader::read('\\mathrm{Fe}')->node));
        self::assertSame('\mathrm{sin}', TexWriter::write(TexReader::read('\\mathrm{sin}')->node));
        self::assertSame('\sin', TexWriter::write(TexReader::read('\\sin')->node));
        self::assertSame('\operatorname{foo}', TexWriter::write(TexReader::read('\\operatorname{foo}')->node));
    }

    public function test_the_invalid_utf8_note_is_named_like_a_tex_note(): void
    {
        $n = TexReader::read("a\xffb")->notes()[0];
        self::assertSame('input', $n->element);
        self::assertSame('offset 1', $n->where);
    }
}
