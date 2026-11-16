<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Math;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Grid;
use Papercut\Math\Node\Row;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MathMatrixTest extends TestCase
{
    public function test_matrix_builds_a_fenced_grid(): void
    {
        $m = Math::matrix([['a', 'b'], ['c', 'd']]);
        self::assertInstanceOf(Fenced::class, $m);
        self::assertSame('(', $m->open);
        self::assertSame(')', $m->close);
        self::assertInstanceOf(Grid::class, $m->content);
        self::assertCount(2, $m->content->rows);
        self::assertCount(2, $m->content->rows[0]);
        self::assertSame(['center', 'center'], $m->content->columnAlign);
    }

    /** @return array<string, array{string, string, string}> */
    public static function fences(): array
    {
        return [
            'paren' => ['paren', '(', ')'],
            'bracket' => ['bracket', '[', ']'],
            'brace' => ['brace', '{', '}'],
            'bar' => ['bar', '|', '|'],
            'doublebar' => ['doublebar', "\u{2016}", "\u{2016}"],
            'none' => ['none', '', ''],
        ];
    }

    #[DataProvider('fences')]
    public function test_each_fence(string $name, string $open, string $close): void
    {
        $m = Math::matrix([['1', '0'], ['0', '1']], $name);
        if ($name === 'none') {
            self::assertInstanceOf(Grid::class, $m);

            return;
        }
        self::assertInstanceOf(Fenced::class, $m);
        self::assertSame($open, $m->open);
        self::assertSame($close, $m->close);
        self::assertInstanceOf(Grid::class, $m->content);
    }

    public function test_align_one_value_or_a_list(): void
    {
        $rows = [['a', 'b'], ['c', 'd']];
        self::assertSame(['left', 'left'], self::grid(Math::matrix($rows, 'none', 'left'))->columnAlign);
        self::assertSame(['left', 'right'], self::grid(Math::matrix($rows, 'none', ['left', 'right']))->columnAlign);
        self::assertSame(['right', 'right'], self::grid(Math::matrix($rows, 'none', ['right']))->columnAlign);
    }

    public function test_cases_is_a_left_brace_around_a_left_aligned_grid(): void
    {
        $c = Math::cases([['x', 'x > 0'], ['-x', 'x < 0']]);
        self::assertSame('{', $c->open);
        self::assertSame('', $c->close);
        self::assertInstanceOf(Grid::class, $c->content);
        self::assertSame(['left', 'left'], $c->content->columnAlign);
        self::assertCount(2, $c->content->rows);
    }

    public function test_cells_are_tokenized(): void
    {
        $g = self::grid(Math::matrix([['x²', '-1'], [Math::sqrt('2'), '']], 'none'));
        self::assertEquals(Math::row('x²'), $g->rows[0][0]);
        self::assertEquals(Math::row('-1'), $g->rows[0][1]);
        self::assertInstanceOf(\Papercut\Math\Node\Root::class, $g->rows[1][0]);
        self::assertEquals(new Row([]), $g->rows[1][1]);
    }

    /** @return array<string, array{\Closure(): mixed, string}> */
    public static function invalid(): array
    {
        return [
            'no rows' => [static fn () => Math::matrix([]), 'Math::matrix() needs at least one row'],
            'empty row' => [static fn () => Math::matrix([[]]), 'Math::matrix() needs at least one row'],
            'row not an array' => [static fn () => Math::matrix(self::untyped(['a'])), 'Math::matrix() needs at least one row'],
            'unknown fence' => [static fn () => Math::matrix([['a']], 'x'), 'paren, bracket, brace, bar, doublebar, none'],
            'unknown alignment' => [static fn () => Math::matrix([['a']], 'paren', 'middle'), 'left, center, right'],
            'unknown alignment in list' => [static fn () => Math::matrix([['a']], 'paren', ['left', 'middle']), 'left, center, right'],
            'empty alignment list' => [static fn () => Math::matrix([['a']], 'paren', []), 'left, center, right'],
            'cases no rows' => [static fn () => Math::cases([]), 'Math::cases() needs at least one row'],
        ];
    }

    /** @param \Closure(): mixed $call */
    #[DataProvider('invalid')]
    public function test_matrix_validation(\Closure $call, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $call();
    }

    public function test_invalid_utf8_in_a_cell_is_noted(): void
    {
        $m = Math::matrix([["a\xFF", 'b']]);
        self::assertGreaterThan(0.0, Math::layout($m)->width);
        self::assertCount(1, Math::notes($m));
    }

    public function test_matrix_lays_out_beside_an_equals(): void
    {
        $grid = Math::matrix([['1', '0'], ['0', '1']], 'none');
        $box = Math::layout($grid, 12.0, false);
        $axis = Math::stixTwoMath()->math()->constant('axisHeight') * 12.0 / 1000;
        self::assertEqualsWithDelta(2 * $axis, $box->ascent - $box->descent, 1e-9);
        $both = Math::layout(Math::row('A =', Math::matrix([['1', '0'], ['0', '1']])), 12.0, false);
        self::assertGreaterThan($box->width, $both->width);
    }

    private static function untyped(mixed $value): mixed
    {
        return $value;
    }

    private static function grid(\Papercut\Math\Node\Node $n): Grid
    {
        self::assertInstanceOf(Grid::class, $n);

        return $n;
    }
}
