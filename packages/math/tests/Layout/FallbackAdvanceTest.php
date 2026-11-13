<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\{Box, Glyph};
use Papercut\Math\Math;
use PHPUnit\Framework\TestCase;

final class FallbackAdvanceTest extends TestCase
{
    /** @return list<Glyph> */
    private static function glyphs(Box $box): array
    {
        return array_values(array_filter($box->items, static fn ($i): bool => $i instanceof Glyph));
    }

    public function test_provider_widens_the_box_and_moves_the_next_glyph(): void
    {
        $plain = Math::layout(Math::text('密度'), 12.0, true, null, false);
        $asked = [];
        $box = Math::layout(Math::text('密度'), 12.0, true, null, false, function (string $char, float $size) use (&$asked): float {
            $asked[] = [$char, $size];
            return 12.0;
        });
        $g = self::glyphs($box);
        $this->assertSame([['密', 12.0], ['度', 12.0]], $asked);
        $this->assertEqualsWithDelta(12.0, $g[1]->x - $g[0]->x, 0.001);
        $this->assertEqualsWithDelta(24.0, $box->width, 0.001);
        $this->assertGreaterThan($plain->width, $box->width);
    }

    public function test_null_keeps_the_notdef_advance_and_covered_characters_are_not_asked(): void
    {
        $plain = Math::layout(Math::text('密x'), 12.0, true, null, false);
        $asked = [];
        $box = Math::layout(Math::text('密x'), 12.0, true, null, false, function (string $char) use (&$asked): ?float {
            $asked[] = $char;
            return null;
        });
        $this->assertSame(['密'], $asked);
        $this->assertEqualsWithDelta($plain->width, $box->width, 0.001);
    }

    public function test_provider_survives_into_scripts(): void
    {
        $box = Math::layout(Math::sup('x', Math::text('密')), 12.0, true, null, false, static fn (string $c, float $s): float => 100.0);
        $this->assertGreaterThan(100.0, $box->width);
    }
}
