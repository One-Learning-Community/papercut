<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Render;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Render\MathmlWriter;

/** Arrows with no label are written long enough for a browser to draw them as the PDF does. */
final class MathmlArrowTest extends TestCase
{
    public function test_a_labelless_arrow_is_a_long_arrow(): void
    {
        self::assertStringContainsString('<mo>⟶</mo>', MathmlWriter::write(Math::tex('\ce{A -> B}')));
        self::assertStringContainsString('<mo>⟵</mo>', MathmlWriter::write(Math::tex('\ce{A <- B}')));
        self::assertStringContainsString('<mo>⟷</mo>', MathmlWriter::write(Math::tex('\ce{A <-> B}')));
    }

    public function test_a_labelless_equilibrium_arrow_stretches_over_a_space_of_the_minimum_width(): void
    {
        $xml = MathmlWriter::write(Math::tex('\ce{A <=> B}'));
        self::assertStringContainsString('<mover><mo>⇌</mo><mspace width="1.469em"/></mover>', $xml);
    }
}
