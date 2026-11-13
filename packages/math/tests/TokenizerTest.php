<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Math;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Row;
use Papercut\Math\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function functionNames(): array
    {
        $out = [];
        foreach (['sin', 'cos', 'tan', 'log', 'ln', 'exp', 'lim', 'max', 'min'] as $f) {
            $out[$f] = [$f];
        }

        return $out;
    }

    #[DataProvider('functionNames')]
    public function test_function_names_upright(string $name): void
    {
        $t = Tokenizer::tokenize($name);
        self::assertCount(1, $t);
        self::assertInstanceOf(Identifier::class, $t[0]);
        self::assertSame($name, $t[0]->name);
        self::assertTrue($t[0]->upright);
    }

    public function test_function_name_followed_by_letter_splits(): void
    {
        $t = Tokenizer::tokenize('sinx');
        self::assertCount(2, $t);
        self::assertInstanceOf(Identifier::class, $t[0]);
        self::assertInstanceOf(Identifier::class, $t[1]);
        self::assertSame('sin', $t[0]->name);
        self::assertTrue($t[0]->upright);
        self::assertSame('x', $t[1]->name);
        self::assertFalse($t[1]->upright);
    }

    public function test_unicode_scripts_stay_attached_to_base(): void
    {
        $row = Math::row('b²');
        self::assertInstanceOf(Row::class, $row);
        self::assertCount(2, $row->children);
        $c = new Context(Math::stixTwoMath(), 12, 0, true);
        $bWidth = $row->children[0]->layout($c)->width;
        $glyphs = Math::layout($row, 12)->items;
        self::assertInstanceOf(Glyph::class, $glyphs[1]);
        self::assertSame('²', $glyphs[1]->text);
        self::assertEqualsWithDelta($bWidth, $glyphs[1]->x, 1e-9);
    }

    public function test_whitespace_only_is_empty_row(): void
    {
        $row = Math::row('  ');
        self::assertInstanceOf(Row::class, $row);
        self::assertSame([], $row->children);
        self::assertSame(0.0, Math::layout($row, 12)->width);
    }
}
