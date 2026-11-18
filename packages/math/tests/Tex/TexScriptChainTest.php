<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Render\TexWriter;

/** An empty group after scripts continues them (tensor indices); after anything else it carries prescripts. */
final class TexScriptChainTest extends TestCase
{
    /** @return list<Scripts> */
    private static function scripts(Node $n): array
    {
        $out = $n instanceof Scripts ? [$n] : [];
        foreach ($n->children() as $child) {
            $out = [...$out, ...self::scripts($child)];
        }

        return $out;
    }

    public function test_an_empty_group_after_scripts_is_not_a_prescript_of_the_next_atom(): void
    {
        $tree = Math::tex('R^a{}_{b} x');
        foreach (self::scripts($tree) as $s) {
            self::assertNull($s->preSub);
            self::assertNull($s->preSup);
        }
        self::assertSame('R^a {}_b x', TexWriter::write(Math::tex('R^a{}_{b} x')));
        // The idiom still works after something that is not scripted.
        self::assertCount(1, array_filter(self::scripts(Math::tex('R {}_b x')), static fn (Scripts $s): bool => $s->preSub !== null));
        self::assertCount(1, array_filter(self::scripts(Math::tex('{}^{14}_6 C')), static fn (Scripts $s): bool => $s->preSub !== null));
    }

    public function test_a_chain_of_empty_group_scripts_stays_on_its_base(): void
    {
        foreach (self::scripts(Math::tex('T^{a}{}_{b}{}^{c} V_a')) as $s) {
            self::assertNull($s->preSub);
            self::assertNull($s->preSup);
        }
    }
}
