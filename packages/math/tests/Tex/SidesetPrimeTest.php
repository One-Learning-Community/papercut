<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tests\TreeAssert;

final class SidesetPrimeTest extends TestCase
{
    public function test_sideset_reads_the_prime_command_like_the_prime_character(): void
    {
        TreeAssert::assertSameTree(Math::tex("\\sideset{'}{}\\sum"), Math::tex('\sideset{\prime}{}\sum'), ignoreSource: true);
        self::assertStringContainsString('^\prime', TexWriter::write(Math::tex('\sideset{\prime}{}\sum')));
    }
}
