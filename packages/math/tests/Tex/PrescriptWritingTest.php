<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Number;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Render\TextWriter;
use Quoin\Math\Tests\TreeAssert;

/** How the writers set prescripts: after a script, and before a base that starts with a letter. */
final class PrescriptWritingTest extends TestCase
{
    public function test_prescripts_after_a_scripted_node_round_trip(): void
    {
        $tree = Math::row(Math::sup(new Identifier('x', false), new Number('2')), Math::prescripts(new Identifier('C', false), '6', '14'));
        $tex = TexWriter::write($tree);
        $back = Math::tex($tex);
        TreeAssert::assertSameTree($tree, $back);
        self::assertSame($tex, TexWriter::write($back));
    }

    public function test_unicode_prescripts_are_set_off_from_a_preceding_script(): void
    {
        $tree = Math::row(Math::sup(new Identifier('x', false), new Number('2')), Math::prescripts(new Identifier('C', false), '6', '14'));
        $text = TextWriter::write($tree);
        self::assertStringContainsString('² ', $text);
        self::assertStringNotContainsString('²¹', $text);
    }

    public function test_a_prescript_ending_in_a_command_is_separated_from_the_base(): void
    {
        $tree = Math::tex('{}_{\beta}^{\gamma}C');
        $tex = TexWriter::write($tree);
        self::assertStringNotContainsString('\gammaC', $tex);
        TreeAssert::assertSameTree($tree, Math::tex($tex));

        $after = Math::row(Math::sup(new Identifier('x', false), new Number('2')), $tree);
        $written = TexWriter::write($after);
        self::assertStringNotContainsString('\gammaC', $written);
        TreeAssert::assertSameTree($after, Math::tex($written));
    }
}
