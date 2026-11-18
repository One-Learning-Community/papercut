<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Number;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Space;
use Quoin\Math\Node\Text;
use Quoin\Math\Node\TexSource;
use Quoin\Math\NoteLevel;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\TexReader;

final class UnitReaderTest extends TestCase
{
    public function test_a_unit_outside_the_grammar_draws_as_its_source_with_one_note(): void
    {
        $result = TexReader::read('\pu{5 (kJ)}');
        $notes = $result->notes();
        self::assertCount(1, $notes);
        self::assertSame('\pu', $notes[0]->element);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        TreeAssert::assertSameTree(new Text('5 (kJ)'), Math::tex('\pu{5 (kJ)}'), ignoreSource: true);
        self::assertSame('\pu{5 (kJ)}', TexWriter::write(Math::tex('\pu{5 (kJ)}')));
    }

    public function test_a_unit_inside_other_constructs_writes_back(): void
    {
        $tex = '\frac{\pu{25 kJ}}{\pu{2 mol}} + \ce{H2O}';
        $tree = Math::tex($tex);
        self::assertSame($tex, TexWriter::write($tree));
        TreeAssert::assertSameTree($tree, Math::tex(TexWriter::write($tree)));
    }

    /** A comma decimal is one number: no TeX spelling reads to it, so the tree is asserted by node. */
    public function test_a_comma_decimal_is_one_number(): void
    {
        $tree = Math::tex('\pu{1,5 m}');
        self::assertInstanceOf(TexSource::class, $tree);
        $row = $tree->content;
        self::assertInstanceOf(Row::class, $row);
        self::assertEquals(new Number('1,5'), $row->children[0]);
        self::assertEquals(new Space(1 / 3), $row->children[1]);
    }

    public function test_a_number_alone_and_a_unit_alone(): void
    {
        TreeAssert::assertSameTree(Math::tex('25'), Math::tex('\pu{25}'), ignoreSource: true);
        TreeAssert::assertSameTree(Math::tex('\mathrm{kg}'), Math::tex('\pu{kg}'), ignoreSource: true);
        self::assertSame([], TexReader::read('\pu{}')->notes());
    }

    public function test_malformed_unit_groups_never_throw(): void
    {
        foreach (['\pu{', '\pu{{{', '\pu{^^^}', '\pu{1e}', '\pu{--1}', '\pu{°}', '\pu{5 .}', '\pu{5 /}', '\pu{^2}', '\pu{10^}', '\pu', '\pu{' . str_repeat('a/', 5000) . '}'] as $tex) {
            TexReader::read($tex);
            $this->addToAssertionCount(1);
        }
    }
}
