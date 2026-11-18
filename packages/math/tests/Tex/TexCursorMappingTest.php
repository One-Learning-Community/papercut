<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Tex\TexCursor;
use Quoin\Math\Tex\TexLexer;

final class TexCursorMappingTest extends TestCase
{
    public function test_the_cursor_maps_source_positions_to_tokens(): void
    {
        $tokens = TexLexer::lex('\alpha b \\ \x y');
        $c = new TexCursor($tokens, 0);
        [$src, $starts, $ends] = $c->mapped(0, count($tokens));

        // One spelling rule: the source is the same whether or not the positions are wanted.
        self::assertSame($c->source(0, count($tokens)), $src);
        self::assertSame('\alpha b \\ \x y', $src);
        // `\alpha` ends at 6, before the space the source puts after it.
        self::assertSame(1, $ends[6]);
        self::assertSame(0, $starts[0]);
        self::assertSame(count($tokens), $starts[mb_strlen($src)]);
    }
}
