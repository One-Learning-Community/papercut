<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Grid;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\TexWriter;

/** An equation number: kept by the writers, written once, and dropped with the row it stood alone in. */
final class EquationNumberTest extends TestCase
{
    private static function write(string $tex): string
    {
        return TexWriter::write(Math::tex($tex));
    }

    /** @return list<Scripts> */
    private static function scripts(Node $n): array
    {
        $out = $n instanceof Scripts ? [$n] : [];
        foreach ($n->children() as $child) {
            $out = [...$out, ...self::scripts($child)];
        }

        return $out;
    }

    public function test_prescripts_do_not_swallow_an_equation_number(): void
    {
        self::assertStringContainsString('\tag{1}', self::write('{}^a\tag{1}'));
        foreach (self::scripts(Math::tex('{}^a\tag{1}')) as $s) {
            self::assertStringNotContainsString('tag', TexWriter::write($s->base));
        }
    }

    public function test_only_the_first_equation_number_is_written(): void
    {
        self::assertSame('x \tag{a}', self::write('x\tag{a}\tag{b}'));
        self::assertSame('\tag{a} x', self::write('\tag{a} x \tag{b}'));
        self::assertSame(1, substr_count(self::write('\tag{a}\tag{b}'), '\tag'));
    }

    public function test_a_tag_after_a_display_style_switch_is_kept(): void
    {
        self::assertStringContainsString('\tag{1}', self::write('\displaystyle x \tag{1}'));
    }

    public function test_a_tag_only_row_at_the_end_of_an_environment_is_dropped(): void
    {
        $tree = Math::tex('\begin{gather} a \\\\ \tag{2} \end{gather}');
        $rows = 0;
        $walk = static function (Node $n) use (&$walk, &$rows): void {
            if ($n instanceof Grid) {
                $rows = count($n->rows);
            }
            foreach ($n->children() as $c) {
                $walk($c);
            }
        };
        $walk($tree);
        self::assertSame(1, $rows);
        self::assertSame(1, substr_count(MathmlWriter::write($tree), '<mtr>'));
    }
}
