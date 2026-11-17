<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Tex;

use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Grid;
use Papercut\Math\Node\Node;
use Papercut\Math\NoteLevel;
use Papercut\Math\Tests\TreeAssert;
use Papercut\Math\Tex\TexReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TexEnvironmentTest extends TestCase
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

    private static function grid(Node $n): Grid
    {
        while ($n instanceof Fallback && $n->content !== null || $n instanceof Fenced) {
            $n = $n->content;
        }
        self::assertInstanceOf(Grid::class, $n);

        return $n;
    }

    public function test_aligned(): void
    {
        $rows = [['x', '= 1'], ['x + y', '= 2']];
        self::same(Math::aligned($rows), '\begin{aligned} x &= 1 \\\\ x + y &= 2 \end{aligned}');
        self::same(Math::aligned([['', '= 1'], ['x', '= 2']]), '\begin{aligned} &= 1 \\\\ x &= 2 \end{aligned}');
        self::same(Math::aligned($rows), '\begin{split} x &= 1 \\\\ x + y &= 2 \end{split}');
        self::same(Math::aligned($rows), '\begin{alignedat}{2} x &= 1 \\\\ x + y &= 2 \end{alignedat}');
    }

    public function test_align_sets_display(): void
    {
        $r = TexReader::read('\begin{align*} x &= 1 \end{align*}');
        self::assertTrue($r->display);
        TreeAssert::assertSameTree(Math::aligned([['x', '= 1']]), $r->node);
        self::assertTrue(TexReader::read('\begin{align} x &= 1 \end{align}')->display);
        self::assertTrue(TexReader::read('\begin{alignat}{2} x &= 1 \end{alignat}')->display);
        self::assertFalse(TexReader::read('\begin{aligned} x &= 1 \end{aligned}')->display);
    }

    /** @return iterable<string, array{string, string}> */
    public static function matrices(): iterable
    {
        yield 'pmatrix' => ['pmatrix', 'paren'];
        yield 'bmatrix' => ['bmatrix', 'bracket'];
        yield 'Bmatrix' => ['Bmatrix', 'brace'];
        yield 'vmatrix' => ['vmatrix', 'bar'];
        yield 'Vmatrix' => ['Vmatrix', 'doublebar'];
        yield 'matrix' => ['matrix', 'none'];
    }

    #[DataProvider('matrices')]
    public function test_matrix_family(string $env, string $fence): void
    {
        self::same(Math::matrix([['a', 'b'], ['c', 'd']], $fence), '\begin{' . $env . '} a & b \\\\ c & d \end{' . $env . '}');
    }

    public function test_smallmatrix(): void
    {
        $node = Math::tex('\begin{smallmatrix} a & b \end{smallmatrix}');
        TreeAssert::assertSameTree(Math::matrix([['a', 'b']], 'none'), Fallback::unwrap($node));
        $notes = self::notes('\begin{smallmatrix} a & b \end{smallmatrix}');
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('smallmatrix', $notes[0]->element);
        self::assertSame('smallmatrix drawn at normal size', $notes[0]->reason);
    }

    public function test_cases(): void
    {
        self::same(Math::cases([['x', 'a'], ['y', 'b']]), '\begin{cases} x & a \\\\ y & b \end{cases}');
        $d = self::grid(Math::tex('\begin{dcases} x & a \end{dcases}'));
        self::assertTrue($d->display);
        $f = Math::tex('\begin{rcases} x & a \end{rcases}');
        self::assertInstanceOf(Fenced::class, $f);
        self::assertSame(['', '}'], [$f->open, $f->close]);
        self::assertSame(['left', 'left'], self::grid($f)->columnAlign);
    }

    public function test_gathered(): void
    {
        $g = self::grid(Math::tex('\begin{gathered} a \\\\ b + c \end{gathered}'));
        self::assertSame(['center'], $g->columnAlign);
        self::assertTrue($g->display);
        self::assertCount(2, $g->rows);
        self::assertTrue(TexReader::read('\begin{gather*} a \\\\ b \end{gather*}')->display);
    }

    public function test_array(): void
    {
        $tex = '\begin{array}{r|l} a & b \\\\ c & d \end{array}';
        $notes = self::notes($tex);
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Dropped, $notes[0]->level);
        self::assertSame(['right', 'left'], self::grid(Math::tex($tex))->columnAlign);
        self::assertSame([], self::notes('\begin{array}{rcl} a & b & c \end{array}'));
        self::assertSame(['right', 'center', 'left'], self::grid(Math::tex('\begin{array}{rcl} a & b & c \end{array}'))->columnAlign);
    }

    public function test_array_notes(): void
    {
        $n = self::notes('\begin{array}{@{}cp{2cm}} a & b \end{array}');
        self::assertCount(2, $n);
        self::assertSame([NoteLevel::Approximated, 'array', '@{…} column separators are not drawn'], [$n[0]->level, $n[0]->element, $n[0]->reason]);
        self::assertSame([NoteLevel::Approximated, 'array', 'p{…} columns are drawn left-aligned without their width'], [$n[1]->level, $n[1]->element, $n[1]->reason]);
    }

    public function test_substack(): void
    {
        $node = Math::tex('\sum_{\substack{i < n \\\\ j > 1}} x');
        self::assertSame([], self::notes('\sum_{\substack{i < n \\\\ j > 1}} x'));
        $found = null;
        $walk = static function (mixed $n) use (&$walk, &$found): void {
            if ($n instanceof Grid) {
                $found = $n;
            }
            if ($n instanceof Node) {
                foreach (get_object_vars($n) as $v) {
                    if (is_array($v)) {
                        array_walk_recursive($v, $walk);
                    } else {
                        $walk($v);
                    }
                }
            }
        };
        $walk($node);
        self::assertInstanceOf(Grid::class, $found);
        self::assertSame(['center'], $found->columnAlign);
        self::assertCount(2, $found->rows);
    }

    public function test_rules_and_tags(): void
    {
        $n = self::notes('\begin{array}{c} a \\\\ \hline b \end{array}');
        self::assertCount(1, $n);
        self::assertSame(NoteLevel::Dropped, $n[0]->level);
        self::assertSame('\hline', $n[0]->element);
        self::assertSame(NoteLevel::Dropped, self::notes('x \tag{1}')[0]->level);
        self::assertSame('\tag', self::notes('x \tag*{1}')[0]->element);
        self::assertSame([], self::notes('x \label{eq:a}'));
        self::assertSame([], self::notes('x \nonumber \notag'));
        self::assertSame('\hline', self::notes('\hdashline')[0]->element);
    }

    public function test_colour_and_decorations(): void
    {
        foreach (['\color{red}{x}', '\textcolor{red}{x}', '{\color{red} x}'] as $tex) {
            $n = self::notes($tex);
            self::assertCount(1, $n, $tex);
            self::assertSame(NoteLevel::Approximated, $n[0]->level);
            self::assertSame('colour is not drawn', $n[0]->reason);
        }
        TreeAssert::assertSameTree(Math::row('x'), Fallback::unwrap(Math::tex('\textcolor{red}{x}')));
        self::assertSame('\textcolor', self::notes('\textcolor{red}{x}')[0]->element);
        foreach (['\boxed{x}', '\fbox{x}', '\cancel{x}', '\bcancel{x}', '\xcancel{x}', '\sout{x}'] as $tex) {
            $n = self::notes($tex);
            self::assertCount(1, $n, $tex);
            self::assertSame(NoteLevel::Approximated, $n[0]->level);
        }
        foreach (['\sideset{_a}{_b}\sum', '\prescript{a}{b}{X}', '\tensor{T}{^a_b}', '\mathchoice{a}{b}{c}{d}', '\newenvironment{foo}{a}{b}', '\begin{CD} A @>f>> B \end{CD}'] as $tex) {
            $n = self::notes($tex);
            self::assertNotEmpty($n, $tex);
            self::assertSame(NoteLevel::Approximated, $n[0]->level, $tex);
            self::assertSame('not supported', $n[0]->reason, $tex);
        }
    }

    public function test_outside_environment(): void
    {
        $n = self::notes('a & b');
        self::assertSame(NoteLevel::Dropped, $n[0]->level);
        $n = self::notes('a \\\\ b');
        self::assertCount(1, $n);
        self::assertSame(NoteLevel::Approximated, $n[0]->level);
        $g = self::grid(Math::tex('a \\\\ b'));
        self::assertSame(['center'], $g->columnAlign);
        self::assertCount(2, $g->rows);
    }

    public function test_unbalanced(): void
    {
        $tex = '\begin{aligned} x &= 1';
        $n = self::notes($tex);
        self::assertCount(1, $n);
        self::assertSame(NoteLevel::Approximated, $n[0]->level);
        self::assertSame('missing \end{aligned}', $n[0]->reason);
        TreeAssert::assertSameTree(Math::aligned([['x', '= 1']]), Fallback::unwrap(Math::tex($tex)));
        $n = self::notes('\end{pmatrix}');
        self::assertCount(1, $n);
        self::assertSame(NoteLevel::Dropped, $n[0]->level);
        $n = self::notes('\begin{pmatrix} a & b \end{aligned}');
        self::assertCount(1, $n);
        self::assertSame(NoteLevel::Approximated, $n[0]->level);
        self::assertSame('closed by \end{aligned}', $n[0]->reason);
    }

    public function test_left_in_cells(): void
    {
        // An unclosed \left ends at the cell's & and \\.
        $g = self::grid(Math::tex('\begin{matrix} \left( a & b \\\\ c & d \end{matrix}'));
        self::assertSame([2, 2], [count($g->rows), $g->columns]);
        // Inside braces the & does not end the \left.
        $g = self::grid(Math::tex('\begin{matrix} {\left( a & b} & c \end{matrix}'));
        self::assertSame([1, 2], [count($g->rows), $g->columns]);
        // An unclosed \left stops at \end without eating it.
        $node = Math::tex('\begin{matrix} \left( a \end{matrix} b');
        $notes = self::notes('\begin{matrix} \left( a \end{matrix} b');
        self::assertSame(['\left'], array_map(static fn (MathNote $n): string => $n->element, $notes));
        self::assertInstanceOf(\Papercut\Math\Node\Row::class, $node);
        self::assertSame(1, self::grid($node->children[0])->columns);
        self::assertCount(2, $node->children);
        $notes = self::notes('\begin{matrix} \left( a \middle| b \right) \end{matrix}');
        self::assertSame([], $notes);
        // A closed \left in a cell is untouched.
        self::same(Math::matrix([[Math::fenced('(', ')', 'a'), 'b']], 'none'), '\begin{matrix} \left( a \right) & b \end{matrix}');
    }

    public function test_trailing_row_break_and_nesting(): void
    {
        self::assertCount(2, self::grid(Math::tex('\begin{matrix} a \\\\ b \\\\ \end{matrix}'))->rows);
        $g = self::grid(Math::tex('\begin{matrix} \begin{matrix} a & b \end{matrix} & c \end{matrix}'));
        self::assertSame([1, 2], [count($g->rows), $g->columns]);
    }

    public function test_trailing_row_keeps_its_notes(): void
    {
        $n = self::notes('\begin{array}{c} a \\\\ \hline \end{array}');
        self::assertSame(['\hline'], array_map(static fn (MathNote $n): string => $n->element, $n));
        self::assertCount(1, self::grid(Math::tex('\begin{array}{c} a \\\\ \hline \end{array}'))->rows);
        $n = self::notes('x \\\\ \hline');
        self::assertContains('\hline', array_map(static fn (MathNote $n): string => $n->element, $n));
        $n = self::notes('\begin{matrix} a \\\\ \tag{1} \end{matrix}');
        self::assertSame(['\tag'], array_map(static fn (MathNote $n): string => $n->element, $n));
    }

    /** @return iterable<string, array{string, string}> */
    public static function starredMatrices(): iterable
    {
        foreach (['pmatrix' => 'paren', 'bmatrix' => 'bracket', 'Bmatrix' => 'brace', 'vmatrix' => 'bar', 'Vmatrix' => 'doublebar', 'matrix' => 'none'] as $env => $fence) {
            yield $env => [$env, $fence];
        }
    }

    #[DataProvider('starredMatrices')]
    public function test_starred_matrices(string $env, string $fence): void
    {
        self::same(Math::matrix([['a', 'b']], $fence, 'right'), '\begin{' . $env . '*}[r] a & b \end{' . $env . '*}');
        self::same(Math::matrix([['a', 'b']], $fence, 'left'), '\begin{' . $env . '*}[l] a & b \end{' . $env . '*}');
        self::same(Math::matrix([['a', 'b']], $fence), '\begin{' . $env . '*} a & b \end{' . $env . '*}');
    }

    public function test_subarray_and_darray(): void
    {
        $tex = '\sum_{\begin{subarray}{l} i < n \\\\ j > 1 \end{subarray}} x';
        self::assertSame([], self::notes($tex));
        $g = self::grid(Math::tex('\begin{subarray}{l} a \\\\ b \end{subarray}'));
        self::assertSame(['left'], $g->columnAlign);
        self::assertFalse($g->display);
        $g = self::grid(Math::tex('\begin{darray}{rl} a & b \end{darray}'));
        self::assertSame(['right', 'left'], $g->columnAlign);
        self::assertTrue($g->display);
    }

    public function test_drcases(): void
    {
        $f = Math::tex('\begin{drcases} x & a \end{drcases}');
        self::assertInstanceOf(Fenced::class, $f);
        self::assertSame(['', '}'], [$f->open, $f->close]);
        self::assertTrue(self::grid($f)->display);
        self::assertSame([], self::notes('\begin{drcases} x & a \end{drcases}'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function smallMatrices(): iterable
    {
        foreach (['psmallmatrix' => 'paren', 'bsmallmatrix' => 'bracket', 'Bsmallmatrix' => 'brace', 'vsmallmatrix' => 'bar', 'Vsmallmatrix' => 'doublebar'] as $env => $fence) {
            yield $env => [$env, $fence];
        }
    }

    #[DataProvider('smallMatrices')]
    public function test_small_matrices(string $env, string $fence): void
    {
        $tex = '\begin{' . $env . '} a & b \end{' . $env . '}';
        TreeAssert::assertSameTree(Math::matrix([['a', 'b']], $fence), Fallback::unwrap(Math::tex($tex)));
        $n = self::notes($tex);
        self::assertCount(1, $n);
        self::assertSame(NoteLevel::Approximated, $n[0]->level);
        self::assertSame('smallmatrix drawn at normal size', $n[0]->reason);
    }

    public function test_equation(): void
    {
        foreach (['equation', 'equation*'] as $env) {
            $r = TexReader::read('\begin{' . $env . '} x = 1 \end{' . $env . '}');
            self::assertTrue($r->display);
            self::assertSame([], $r->notes());
            TreeAssert::assertSameTree(Math::row('x = 1'), $r->node);
        }
    }

    public function test_unbraced_column_count_and_empty_name(): void
    {
        self::same(Math::aligned([['x', '= 1']]), '\begin{alignedat}2 x &= 1 \end{alignedat}');
        $n = self::notes('\begin{} a');
        self::assertSame('\begin', $n[0]->element);
        self::assertSame(NoteLevel::Dropped, $n[0]->level);
    }
}
