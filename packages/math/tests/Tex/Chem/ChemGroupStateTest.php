<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Row;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\TexReader;

/** What a `\ce` group leaves behind: the font it read under, and the note for a brace left open. */
final class ChemGroupStateTest extends TestCase
{
    public function test_a_font_switch_inside_a_chemistry_label_does_not_leak(): void
    {
        $tree = Math::tex('\ce{A ->[$\rm heat$] B} x');
        self::assertInstanceOf(Row::class, $tree);
        TreeAssert::assertSameTree(Math::tex('x'), $tree->children[count($tree->children) - 1]);
    }

    public function test_a_group_closed_only_by_an_inner_brace_is_unclosed(): void
    {
        $reasons = static fn (string $tex): array => array_map(static fn ($n): string => $n->reason, TexReader::read($tex)->notes());
        self::assertContains('group without a closing brace', $reasons('\ce{A{B}'));
        self::assertNotContains('group without a closing brace', $reasons('\ce{A{B}}'));
    }
}
