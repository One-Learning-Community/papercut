<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Mathml;

use Papercut\Math\Math;
use Papercut\Math\Mathml\MathmlReader;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Grid;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\NoteLevel;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\TextWriter;
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
        foreach (['columnlines' => 'solid', 'rowlines' => 'solid', 'frame' => 'solid', 'columnspacing' => '2em', 'rowspacing' => '2em', 'columnwidth' => 'auto', 'equalrows' => 'true', 'equalcolumns' => 'true', 'align' => 'top', 'groupalign' => 'left'] as $a => $v) {
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

    /** @dataProvider ignored */
    #[\PHPUnit\Framework\Attributes\DataProvider('ignored')]
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

    /** @dataProvider written */
    #[\PHPUnit\Framework\Attributes\DataProvider('written')]
    public function test_write_then_read_round_trips(Node $node): void
    {
        $back = Math::mathml(MathmlWriter::write($node));
        self::assertSame(self::shape($node), self::shape($back));
    }
}
