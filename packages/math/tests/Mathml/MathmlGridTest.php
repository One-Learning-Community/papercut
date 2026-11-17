<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Mathml;

use Quoin\Math\Math;
use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Node\Fallback;
use Quoin\Math\Node\Fenced;
use Quoin\Math\Node\Grid;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Row;
use Quoin\Math\NoteLevel;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\TextWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MathmlGridTest extends TestCase
{
    private const CELLS = '<mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr>';

    private static function table(string $attrs = ''): string
    {
        return '<mtable ' . $attrs . '>' . self::CELLS . '</mtable>';
    }

    private static function grid(string $xml): Grid
    {
        $n = Fallback::unwrap(MathmlReader::read($xml)->node);
        while ($n instanceof Fallback || $n instanceof Fenced) {
            $n = Fallback::unwrap($n instanceof Fenced ? $n->content : $n);
        }
        self::assertInstanceOf(Grid::class, $n);

        return $n;
    }

    public function test_plain_table_is_a_grid_with_no_note(): void
    {
        $r = MathmlReader::read('<math>' . self::table() . '</math>');
        self::assertInstanceOf(Grid::class, $r->node);
        self::assertCount(2, $r->node->rows);
        self::assertSame(2, $r->node->columns);
        self::assertSame([], $r->notes());
    }

    public function test_matrix_with_mo_fences_is_a_fenced_grid(): void
    {
        $r = MathmlReader::read('<math><mo>(</mo>' . self::table() . '<mo>)</mo></math>');
        self::assertInstanceOf(Fenced::class, $r->node);
        self::assertInstanceOf(Grid::class, $r->node->content);
        self::assertSame([], $r->notes());
    }

    public function test_lone_opening_brace_before_a_table_is_cases(): void
    {
        foreach (['', '<mo></mo>'] as $tail) {
            $r = MathmlReader::read('<math><mrow><mo>{</mo>' . self::table() . $tail . '</mrow></math>');
            self::assertInstanceOf(Fenced::class, $r->node);
            self::assertSame('{', $r->node->open);
            self::assertSame('', $r->node->close);
            self::assertInstanceOf(Grid::class, $r->node->content);
        }
        $r = MathmlReader::read('<math><mrow><mo>(</mo><mi>x</mi></mrow></math>');
        self::assertInstanceOf(Row::class, $r->node);
        self::assertInstanceOf(Operator::class, $r->node->children[0]);
    }

    public function test_mfenced_table(): void
    {
        $r = MathmlReader::read('<math><mfenced>' . self::table() . '</mfenced></math>');
        self::assertInstanceOf(Fenced::class, $r->node);
        self::assertInstanceOf(Grid::class, $r->node->content);
    }

    public function test_columnalign_on_the_table(): void
    {
        self::assertSame(['left', 'right'], self::grid('<math>' . self::table('columnalign="left right"') . '</math>')->columnAlign);
        $three = '<mtable %s><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd><mtd><mi>c</mi></mtd></mtr></mtable>';
        self::assertSame(['left', 'left', 'left'], self::grid('<math>' . sprintf($three, 'columnalign="left"') . '</math>')->columnAlign);
        self::assertSame(['left', 'right', 'left'], self::grid('<math>' . sprintf($three, 'columnalign="left right"') . '</math>')->columnAlign);
    }

    public function test_rowalign_and_displaystyle(): void
    {
        $g = self::grid('<math>' . self::table('rowalign="top bottom" displaystyle="true"') . '</math>');
        self::assertSame('top', $g->rowAlign);
        self::assertTrue($g->display);
    }

    /** @return array<string, array{string, string, string}> */
    public static function ignored(): array
    {
        $out = [];
        foreach (['columnlines' => 'solid', 'rowlines' => 'solid', 'frame' => 'solid', 'rowspacing' => '2em', 'columnwidth' => 'auto', 'equalrows' => 'true', 'equalcolumns' => 'true', 'align' => 'top', 'groupalign' => 'left'] as $a => $v) {
            $out["mtable $a"] = ['mtable', $a, $v];
        }
        foreach (['rowspan' => '2', 'columnspan' => '2'] as $a => $v) {
            $out["mtd $a"] = ['mtd', $a, $v];
        }
        foreach (['columnalign' => 'left', 'rowalign' => 'top'] as $a => $v) {
            $out["mtr $a"] = ['mtr', $a, $v];
            $out["mtd $a"] = ['mtd', $a, $v];
        }

        return $out;
    }

    public function test_mathml_columnspacing_read(): void
    {
        $r = MathmlReader::read('<math>' . self::table('columnspacing="0em 2em"') . '</math>');
        self::assertSame([2.0], self::grid('<math>' . self::table('columnspacing="2em"') . '</math>')->columnSpacing);
        self::assertSame([0.0], self::grid('<math>' . self::table('columnspacing="0em 2em"') . '</math>')->columnSpacing);
        self::assertSame([], $r->notes());
    }

    #[DataProvider('ignored')]
    public function test_ignored_attributes_note(string $el, string $attr, string $value): void
    {
        $xml = match ($el) {
            'mtable' => '<math><mtable ' . $attr . '="' . $value . '"><mtr><mtd><mi>a</mi></mtd></mtr></mtable></math>',
            'mtr' => '<math><mtable><mtr><mtd><mi>a</mi></mtd></mtr><mtr ' . $attr . '="' . $value . '"><mtd><mi>b</mi></mtd></mtr></mtable></math>',
            default => '<math><mtable><mtr><mtd><mi>a</mi></mtd></mtr><mtr><mtd><mi>b</mi></mtd><mtd ' . $attr . '="' . $value . '"><mi>c</mi></mtd></mtr></mtable></math>',
        };
        $notes = MathmlReader::read($xml)->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame($el, $notes[0]->element);
        self::assertSame($attr . ' is not drawn', $notes[0]->reason);
        self::assertSame(match ($el) {
            'mtable' => 'math > mtable[0]',
            'mtr' => 'math > mtable[0] > mtr[1]',
            default => 'math > mtable[0] > mtr[1] > mtd[1]',
        }, $notes[0]->where);
    }

    public function test_cell_path_example(): void
    {
        $xml = '<math><mtable><mtr><mtd><mi>a</mi></mtd></mtr><mtr><mtd rowspan="2"><mi>b</mi></mtd></mtr></mtable></math>';
        self::assertSame('math > mtable[0] > mtr[1] > mtd[0]', MathmlReader::read($xml)->notes()[0]->where);
    }

    public function test_harmless_attribute_values_do_not_note(): void
    {
        $xml = '<math><mtable columnlines="none" rowlines="none"><mtr><mtd rowspan="1" columnspan="1"><mi>a</mi></mtd></mtr></mtable></math>';
        self::assertSame([], MathmlReader::read($xml)->notes());
    }

    public function test_notes_dedupe_per_element_and_attribute(): void
    {
        $xml = '<math><mtable>' . str_repeat('<mtr><mtd rowspan="2"><mi>a</mi></mtd></mtr>', 50) . '</mtable></math>';
        self::assertCount(1, MathmlReader::read($xml)->notes());
    }

    public function test_mlabeledtr_keeps_its_note_and_drops_the_label(): void
    {
        $r = MathmlReader::read('<math><mtable><mlabeledtr><mtd><mtext>LABEL</mtext></mtd><mtd><mi>a</mi></mtd></mlabeledtr></mtable></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame('mlabeledtr', $notes[0]->element);
        self::assertSame('row label not drawn', $notes[0]->reason);
        self::assertStringNotContainsString('LABEL', TextWriter::write($r->node));
        self::assertStringContainsString('a', TextWriter::write($r->node));
    }

    public function test_an_opener_inside_a_matched_pair_still_fences_its_table(): void
    {
        $r = MathmlReader::read('<math><mo>[</mo><mo>{</mo>' . self::table() . '<mo>]</mo></math>');
        self::assertInstanceOf(Fenced::class, $r->node);
        self::assertSame('[', $r->node->open);
        self::assertSame(']', $r->node->close);
        $inner = $r->node->content;
        if ($inner instanceof Row) {
            self::assertCount(1, $inner->children);
            $inner = $inner->children[0];
        }
        self::assertInstanceOf(Fenced::class, $inner);
        self::assertSame('{', $inner->open);
        self::assertSame('', $inner->close);
        self::assertInstanceOf(Grid::class, $inner->content);
    }

    public function test_many_lone_openers_before_tables_read_in_linear_time_and_all_fold(): void
    {
        $best = static function (int $n): float {
            $xml = '<math>' . str_repeat('<mo>{</mo>' . self::table(), $n) . '</math>';
            $times = [];
            for ($k = 0; $k < 3; $k++) {
                $t = microtime(true);
                MathmlReader::read($xml);
                $times[] = microtime(true) - $t;
            }

            return min($times);
        };
        $small = $best(4000);
        $large = $best(16000);
        // Linear is about 4; the old splice-per-opener pass was about 16.
        self::assertLessThan(6.0, $large / $small);

        $r = MathmlReader::read('<math>' . str_repeat('<mo>{</mo>' . self::table(), 2000) . '</math>');
        self::assertInstanceOf(Row::class, $r->node);
        self::assertCount(2000, $r->node->children);
        foreach ($r->node->children as $child) {
            self::assertInstanceOf(Fenced::class, $child);
            self::assertSame('{', $child->open);
            self::assertSame('', $child->close);
            self::assertInstanceOf(Grid::class, $child->content);
        }
    }

    public function test_a_lone_opener_before_a_fallback_wrapped_grid_folds(): void
    {
        $r = MathmlReader::read('<math><mo>{</mo>' . self::table('frame="solid"') . '</math>');
        self::assertInstanceOf(Fenced::class, $r->node);
        self::assertSame('{', $r->node->open);
        self::assertInstanceOf(Fallback::class, $r->node->content);
        self::assertInstanceOf(Grid::class, Fallback::unwrap($r->node->content));
    }

    public function test_nested_tables_read_as_nested_grids(): void
    {
        $inner = self::table();
        $r = MathmlReader::read('<math><mtable><mtr><mtd>' . $inner . '</mtd><mtd><mi>x</mi></mtd></mtr></mtable></math>');
        self::assertInstanceOf(Grid::class, $r->node);
        $cell = $r->node->rows[0][0];
        while ($cell instanceof Row && count($cell->children) === 1) {
            $cell = $cell->children[0];
        }
        self::assertInstanceOf(Grid::class, $cell);
        self::assertSame(2, $cell->columns);
    }

    public function test_a_matched_pair_wins_over_a_lone_opener_fold(): void
    {
        $r = MathmlReader::read('<math><mo>(</mo>' . self::table() . '<mi>x</mi><mo>)</mo></math>');
        self::assertInstanceOf(Fenced::class, $r->node);
        self::assertSame('(', $r->node->open);
        self::assertSame(')', $r->node->close);
        self::assertInstanceOf(Row::class, $r->node->content);
        self::assertInstanceOf(Grid::class, $r->node->content->children[0]);
        self::assertCount(2, $r->node->content->children);
    }

    public function test_a_lone_paren_before_a_table_fences_it(): void
    {
        $r = MathmlReader::read('<math><mo>(</mo>' . self::table() . '</math>');
        self::assertInstanceOf(Fenced::class, $r->node);
        self::assertSame('(', $r->node->open);
        self::assertSame('', $r->node->close);
        self::assertInstanceOf(Grid::class, $r->node->content);
    }

    public function test_a_lone_bar_before_a_table_stays_plain(): void
    {
        $r = MathmlReader::read('<math><mo>|</mo>' . self::table() . '</math>');
        self::assertInstanceOf(Row::class, $r->node);
        self::assertInstanceOf(Operator::class, $r->node->children[0]);
        self::assertInstanceOf(Grid::class, $r->node->children[1]);
    }

    public function test_absurd_attribute_values_neither_throw_nor_warn(): void
    {
        $align = implode(' ', array_fill(0, 5000, 'left'));
        $r = MathmlReader::read('<math><mtable columnalign="' . $align . '"><mtr><mtd rowspan="' . str_repeat('9', 30) . '"><mi>a</mi></mtd></mtr></mtable></math>');
        self::assertCount(1, $r->notes());
        self::assertSame('rowspan is not drawn', $r->notes()[0]->reason);
    }

    public function test_attribute_on_mlabeledtr_names_that_element(): void
    {
        $r = MathmlReader::read('<math><mtable><mlabeledtr columnalign="left"><mtd/><mtd/></mlabeledtr></mtable></math>');
        $found = array_values(array_filter($r->notes(), static fn ($n): bool => $n->reason === 'columnalign is not drawn'));
        self::assertCount(1, $found);
        self::assertSame('mlabeledtr', $found[0]->element);
        self::assertStringEndsWith('mlabeledtr[0]', $found[0]->where);
    }

    public function test_attributes_on_the_dropped_label_cell_do_not_note(): void
    {
        $label = MathmlReader::read('<math><mtable><mlabeledtr><mtd rowspan="2"><mtext>L</mtext></mtd><mtd><mi>a</mi></mtd></mlabeledtr></mtable></math>');
        self::assertSame(['row label not drawn'], array_map(static fn ($n): string => $n->reason, $label->notes()));
        $body = MathmlReader::read('<math><mtable><mlabeledtr><mtd><mtext>L</mtext></mtd><mtd rowspan="2"><mi>a</mi></mtd></mlabeledtr></mtable></math>');
        self::assertContains('rowspan is not drawn', array_map(static fn ($n): string => $n->reason, $body->notes()));
    }

    public function test_ragged_and_empty_tables(): void
    {
        foreach ([
            '<mtable/>',
            '<mtable><mtr/></mtable>',
            '<mtable><mtr><mtd/></mtr></mtable>',
            '<mtable><mtr><mtd><mi>a</mi></mtd></mtr><mtr><mtd><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>',
        ] as $t) {
            $r = MathmlReader::read('<math>' . $t . '</math>');
            self::assertSame([], $r->notes(), $t);
            self::assertInstanceOf(Grid::class, $r->node);
            foreach ($r->node->rows as $row) {
                self::assertCount($r->node->columns, $row);
            }
            Math::layout($r->node);
            TextWriter::write($r->node);
        }
        $g = self::grid('<math><mtable><mtr><mtd><mi>a</mi></mtd></mtr><mtr><mtd/><mtd/><mtd/></mtr></mtable></math>');
        self::assertSame(3, $g->columns);
    }

    public function test_garbage_attribute_values(): void
    {
        $g = self::grid('<math><mtable columnalign="decimalpoint foo" rowalign="none" displaystyle="maybe" rowspan="abc"><mtr><mtd rowspan="abc"><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr></mtable></math>');
        self::assertSame(['center', 'center'], $g->columnAlign);
        self::assertSame('baseline', $g->rowAlign);
        self::assertFalse($g->display);
        $g = self::grid('<math><mtable columnalign=""><mtr><mtd><mi>a</mi></mtd></mtr></mtable></math>');
        self::assertSame(['center'], $g->columnAlign);
        self::assertSame([], MathmlReader::read('<math><mtable><mtr><mtd rowspan="abc"><mi>a</mi></mtd></mtr></mtable></math>')->notes());
    }

    public function test_wiris_filler_cells(): void
    {
        $r = MathmlReader::read('<math><mtable><mtr><mtd><mo>&#160;</mo></mtd><mtd><mi>a</mi></mtd></mtr><mtr><mtd><mi>b</mi></mtd><mtd><mo>&#160;</mo></mtd></mtr></mtable></math>');
        Math::layout($r->node);
        self::assertStringContainsString('<mtable', MathmlWriter::write($r->node));
        self::assertStringContainsString('<m:m>', OmmlWriter::oMath($r->node));
        self::assertStringNotContainsString("\n", TextWriter::write($r->node));
    }

    /** @return array<string, array{Node}> */
    public static function written(): array
    {
        return [
            'bar matrix' => [Math::matrix([['a', 'b'], ['c', 'd']], 'bar')],
            'cases' => [Math::cases([['x', 'y'], ['z', 'w']])],
            'aligned grid' => [new Grid([[Math::mathml('<mi>a</mi>'), Math::mathml('<mi>b</mi>')]], ['left', 'right'], 'top', true)],
        ];
    }

    private static function shape(Node $n): string
    {
        $n = Fallback::unwrap($n);
        $s = $n::class;
        if ($n instanceof Fenced) {
            $s .= '[' . $n->open . $n->close . ']' . self::shape($n->content);
        } elseif ($n instanceof Grid) {
            $s .= json_encode([$n->columnAlign, $n->rowAlign, $n->display, $n->columns]);
            foreach ($n->rows as $row) {
                foreach ($row as $cell) {
                    $s .= '(' . self::shape($cell) . ')';
                }
            }
        } else {
            $s .= TextWriter::write($n);
        }

        return $s;
    }

    #[DataProvider('written')]
    public function test_write_then_read_round_trips(Node $node): void
    {
        $back = Math::mathml(MathmlWriter::write($node));
        self::assertSame(self::shape($node), self::shape($back));
    }

    public function test_unparseable_columnspacing_is_one_em_and_noted(): void
    {
        foreach (['10%' => 1.0, 'auto' => 1.0, '0em auto' => 0.0] as $v => $gap) {
            $xml = '<math>' . self::table('columnspacing="' . $v . '"') . '</math>';
            self::assertSame([$gap], self::grid($xml)->columnSpacing, $v);
            self::assertSame(['columnspacing is not drawn'], array_map(static fn ($n): string => $n->reason, MathmlReader::read($xml)->notes()), $v);
        }
        self::assertSame([0.0], self::grid('<math>' . self::table('columnspacing="0"') . '</math>')->columnSpacing);
    }
}
