<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Render\TextWriter;

/** Where scripts in `\ce` attach: command scripts, and prescripts after brackets or before the next atom. */
final class ChemScriptsTest extends TestCase
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

    public function test_an_unbraced_command_script_takes_the_whole_command(): void
    {
        $text = TextWriter::write(Math::tex('\ce{A^\alpha B -> C}'));
        self::assertStringNotContainsString('alpha', $text);
        self::assertStringStartsWith('A^α', $text);
        self::assertStringContainsString('→ C', $text);
        self::assertSame(1, substr_count($text, 'B'));
        self::assertSame(1, substr_count($text, 'C'));
    }

    public function test_an_unbraced_command_subscript_stops_at_the_command(): void
    {
        $tree = Math::tex('\ce{X_\beta + Y}');
        $text = TextWriter::write($tree);
        self::assertStringNotContainsString('beta', $text);
        self::assertSame(1, substr_count($text, 'Y'));
        $scripts = self::scripts($tree);
        self::assertCount(1, $scripts);
        self::assertInstanceOf(Identifier::class, $scripts[0]->sub);
        self::assertSame('β', $scripts[0]->sub->name);
    }

    /** @return iterable<string, array{string, string, string}> source, the base of the prescripts, the text */
    public static function prescripted(): iterable
    {
        yield 'after a bracket' => ['\ce{(^{14}C)}', 'C', '(¹⁴C)'];
        yield 'after a square bracket' => ['\ce{[^{60}Co(NH3)6]^3+}', 'Co', '[⁶⁰Co ( NH₃)₆]³⁺'];
        yield 'before a formula' => ['\ce{^{14}CO2}', 'C', '¹⁴CO₂'];
        yield 'both, before a symbol' => ['\ce{^{235}_{92}U + n}', 'U', '²³⁵₉₂U + n'];
    }

    #[DataProvider('prescripted')]
    public function test_prescripts_belong_to_the_atom_after_them(string $src, string $base, string $text): void
    {
        $tree = Math::tex($src);
        $pre = array_values(array_filter(self::scripts($tree), static fn (Scripts $s): bool => $s->preSub !== null || $s->preSup !== null));
        self::assertCount(1, $pre);
        self::assertInstanceOf(Identifier::class, $pre[0]->base);
        self::assertSame($base, $pre[0]->base->name);
        self::assertSame($text, TextWriter::write($tree));
    }
}
