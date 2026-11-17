<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Render;

use Quoin\Math\Math;
use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Node\Grid;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\TextWriter;
use Quoin\Math\Tests\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GridWritersTest extends TestCase
{
    private const NS = 'xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';

    /** @return iterable<string, array{string}> */
    public static function gridFixtures(): iterable
    {
        foreach (['matrix-2x2', 'determinant', 'cases', 'matrix-equals'] as $name) {
            yield $name => [$name];
        }
    }

    /** @return list<list<string>> */
    private static function abcd(): array
    {
        return [['a', 'b'], ['c', 'd']];
    }

    /**
     * @param list<list<string>> $rows
     *
     * @return list<list<\Quoin\Math\Node\Node>>
     */
    private static function cells(array $rows): array
    {
        return array_map(static fn (array $r): array => array_map(static fn (string $c) => Math::row($c), $r), $rows);
    }

    public function test_mathml_grid(): void
    {
        $xml = MathmlWriter::write(Math::matrix(self::abcd(), 'none'));
        self::assertStringContainsString('<mtable><mtr><mtd>', $xml);
        self::assertSame(2, substr_count($xml, '<mtr>'));
        self::assertSame(4, substr_count($xml, '<mtd>'));
        self::assertStringNotContainsString('columnalign', $xml);
        self::assertStringNotContainsString('rowalign', $xml);
        self::assertStringNotContainsString('displaystyle', $xml);

        self::assertStringContainsString('columnalign="left right"', MathmlWriter::write(Math::matrix(self::abcd(), 'none', ['left', 'right'])));
        self::assertStringContainsString('rowalign="top"', MathmlWriter::write(new Grid(self::cells(self::abcd()), [], rowAlign: 'top')));
        self::assertStringContainsString('displaystyle="true"', MathmlWriter::write(new Grid(self::cells(self::abcd()), [], display: true)));
    }

    #[DataProvider('gridFixtures')]
    public function test_mathml_output_is_well_formed(string $name): void
    {
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML(MathmlWriter::write(Fixtures::all()[$name])));
    }

    public function test_omml_matrix(): void
    {
        $xml = OmmlWriter::oMath(Math::matrix(self::abcd(), 'none'));
        self::assertStringContainsString('<m:m><m:mPr><m:plcHide m:val="1"/><m:mcs>', $xml);
        self::assertSame(2, substr_count($xml, '<m:mc><m:mcPr><m:count m:val="1"/><m:mcJc m:val="center"/></m:mcPr></m:mc>'));
        self::assertSame(2, substr_count($xml, '<m:mr>'));
        self::assertSame(4, substr_count($xml, '<m:e>'));

        self::assertStringContainsString('m:val="left"', OmmlWriter::oMath(Math::matrix(self::abcd(), 'none', 'left')));

        // LibreOffice shows a red "¿" and collapses the rows for an empty m:e, so an empty cell holds one space.
        $empty = OmmlWriter::oMath(Math::matrix([['1', ''], ['', '1']]));
        self::assertSame(2, substr_count($empty, '<m:t xml:space="preserve"> </m:t>'));
        self::assertStringNotContainsString('<m:e></m:e>', $empty);
        self::assertStringNotContainsString('<m:e/>', $empty);
        self::assertStringContainsString('<m:plcHide m:val="1"/>', $empty);
        self::assertStringNotContainsString('<m:t xml:space="preserve"> </m:t>', OmmlWriter::oMath(Math::matrix(self::abcd())));
    }

    public function test_omml_fenced_matrix_and_cases(): void
    {
        $paren = OmmlWriter::oMath(Math::matrix(self::abcd()));
        self::assertStringContainsString('<m:d><m:dPr><m:begChr m:val="("/><m:endChr m:val=")"/></m:dPr><m:e><m:m>', $paren);

        $cases = OmmlWriter::oMath(Math::cases(self::abcd()));
        self::assertStringContainsString('<m:d><m:dPr><m:begChr m:val="{"/><m:endChr m:val=""/></m:dPr><m:e><m:m>', $cases);
        self::assertSame(2, substr_count($cases, '<m:mcJc m:val="left"/>'));
    }

    #[DataProvider('gridFixtures')]
    public function test_omml_output_parses(string $name): void
    {
        $doc = \Dom\XMLDocument::createFromString('<w:root ' . self::NS . '>' . OmmlWriter::oMathPara(Fixtures::all()[$name], 'center') . '</w:root>');
        self::assertSame('oMathPara', $doc->documentElement?->firstElementChild?->localName);
    }

    public function test_text_is_one_line(): void
    {
        self::assertSame('(a b; c d)', TextWriter::write(Math::matrix(self::abcd())));
        self::assertSame('[a b; c d]', TextWriter::write(Math::matrix(self::abcd(), 'bracket')));
        self::assertSame('|a b; c d|', TextWriter::write(Math::matrix(self::abcd(), 'bar')));
        self::assertSame('{a, b; c, d', TextWriter::write(Math::cases(self::abcd())));
        foreach (['paren', 'bracket', 'brace', 'bar', 'doublebar', 'none'] as $fence) {
            self::assertStringNotContainsString("\n", TextWriter::write(Math::matrix(self::abcd(), $fence)));
        }
        self::assertStringNotContainsString("\n", TextWriter::write(Math::cases(self::abcd())));
    }

    public function test_cases_through_a_fallback_and_filler_rows(): void
    {
        $r = MathmlReader::read('<math><mrow><mo>{</mo><mtable frame="solid"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr></mtable></mrow></math>');
        self::assertSame('{a, b', TextWriter::write($r->node));
        self::assertSame('a c', TextWriter::write(Math::matrix([['', ''], ['a', 'c'], ['', '']], 'none')));
        self::assertSame('{a, c', TextWriter::write(Math::cases([['', ''], ['a', 'c']])));
    }

    public function test_an_unfenced_grid_in_a_fraction_is_wrapped_but_a_fenced_one_is_not(): void
    {
        $bare = Math::matrix(self::abcd(), 'none');
        self::assertSame('(a b; c d)/2', TextWriter::write(Math::frac($bare, '2')));
        self::assertSame('(a b; c d)/2', TextWriter::write(Math::frac(Math::matrix(self::abcd()), '2')));
        self::assertSame('a/2', TextWriter::write(Math::frac(Math::matrix([['a']], 'none'), '2')));
    }

    public function test_empty_cells_do_not_leave_double_spaces_or_stray_commas(): void
    {
        self::assertSame('a b', TextWriter::write(Math::matrix([['a', '', 'b']], 'none')));
        self::assertSame('{b; c, d', TextWriter::write(Math::cases([['', 'b'], ['c', 'd']])));
    }

    public function test_empty_grid_writes_nothing(): void
    {
        $grid = new Grid([]);
        self::assertSame('', TextWriter::write($grid));
        self::assertSame('<m:oMath></m:oMath>', OmmlWriter::oMath($grid));
        self::assertStringContainsString('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"></math>', MathmlWriter::write($grid));
    }

    public function test_nested_grids_write(): void
    {
        $inner = Math::matrix(self::abcd());
        $trees = [
            new Grid([[Math::row('x'), $inner]]),
            Math::frac($inner, 'b'),
            new Grid([[Math::fenced('(', ')', 'x'), Math::row('y')]]),
        ];
        foreach ($trees as $tree) {
            self::assertNotSame('', TextWriter::write($tree));
        }
        self::assertSame(2, substr_count(MathmlWriter::write($trees[0]), '<mtable'));
        self::assertSame(2, substr_count(OmmlWriter::oMath($trees[0]), '<m:m>'));
        $fraction = MathmlWriter::write($trees[1]);
        self::assertMatchesRegularExpression('#<mfrac><mrow><mo[^>]*>\(</mo><mtable.*</mtable><mo[^>]*>\)</mo></mrow>#s', $fraction);
        self::assertStringContainsString('<m:f>', OmmlWriter::oMath($trees[1]));
        self::assertStringContainsString('<m:num><m:d>', OmmlWriter::oMath($trees[1]));
        self::assertMatchesRegularExpression('#<m:num><m:d>.*<m:m>.*</m:num>#s', OmmlWriter::oMath($trees[1]));
    }

    public function test_mathml_column_spacing_and_aligned(): void
    {
        $xml = MathmlWriter::write(Math::aligned([['x', '= 1']]));
        self::assertStringContainsString('<mtable columnalign="right left right" columnspacing="0em 2em" displaystyle="true">', MathmlWriter::write(Math::aligned([['x', '= 1', 'y']])));
        self::assertStringContainsString('columnspacing="0em"', $xml);
        self::assertStringNotContainsString('columnspacing', MathmlWriter::write(Math::matrix(self::abcd(), 'none')));
    }

    public function test_omml_second_alignment_pair_is_separated_by_em_spaces(): void
    {
        $xml = OmmlWriter::oMath(Math::aligned([['x', '= 1', 'y', '= 2']]));
        self::assertSame(1, substr_count($xml, "\u{2003}\u{2003}"));
        self::assertMatchesRegularExpression('#&amp;</m:t></m:r><m:r>[^|]*?<m:t xml:space="preserve">\x{2003}\x{2003}</m:t></m:r><m:r>[^|]*?>y<#u', $xml);
        self::assertStringNotContainsString("\u{2003}", OmmlWriter::oMath(Math::aligned([['x', '= 1']])));
    }

    public function test_omml_alignat_pairs_get_no_automatic_space(): void
    {
        $xml = OmmlWriter::oMath(Math::aligned([['x', '= 1', 'y', '= 2']], 0.0));
        self::assertStringNotContainsString("\u{2003}", $xml);
    }

    public function test_omml_aligned_is_an_equation_array(): void
    {
        $xml = OmmlWriter::oMath(Math::aligned([['x', '= 1'], ['', '= 2']]));
        self::assertStringContainsString('<m:eqArr>', $xml);
        self::assertSame(2, substr_count($xml, '<m:e>'));
        self::assertSame(2, substr_count($xml, '&amp;</m:t></m:r>'));
        self::assertStringNotContainsString('<m:m>', $xml);
    }

    public function test_text_aligned_rows_joined_without_newline(): void
    {
        $text = TextWriter::write(Math::aligned([['x', '= 1'], ['', '= 2']]));
        self::assertSame('x = 1; = 2', $text);
    }

    public function test_omml_one_column_aligned_empty_row_is_not_an_empty_element(): void
    {
        foreach ([Math::aligned([['x'], ['']]), Math::aligned([['']])] as $g) {
            $xml = OmmlWriter::oMath($g);
            self::assertStringNotContainsString('<m:e></m:e>', $xml);
            self::assertStringContainsString('<m:t xml:space="preserve"> </m:t>', $xml);
        }
    }

    public function test_omml_odd_column_alignment_gets_the_pair_gap_only_between_pairs(): void
    {
        $xml = OmmlWriter::oMath(Math::aligned([['x', '= 1', 'y']]));
        self::assertSame(1, substr_count($xml, "\u{2003}\u{2003}"));
        self::assertSame(2, substr_count($xml, '&amp;'));
    }

    public function test_omml_fractional_pair_gap_uses_the_matching_space(): void
    {
        self::assertStringContainsString("\u{2002}", OmmlWriter::oMath(Math::aligned([['x', '= 1', 'y', '= 2']], 0.5)));
    }
}
