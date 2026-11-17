<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Render\SvgRenderer;
use Quoin\Math\Tex\TexReader;

/**
 * Deeply nested layouts. Offsets are summed from the outside in (Box::each), which can differ from the old per-level translation
 * by 0.001 pt in a coordinate; these snapshots record the output as it is.
 */
final class SvgSnapshotTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function formulas(): iterable
    {
        yield 'continued-fraction' => ['continued-fraction', '\frac{1}{1+\frac{1}{1+\frac{1}{x}}}'];
        yield 'nested-subscripts' => ['nested-subscripts', 'a_{i_{j_{k}}}'];
        yield 'tall-fences' => ['tall-fences', '\left(\left\|\sqrt[3]{\frac{\frac{1}{2}}{\frac{3}{4}}}\right\|\right)\xrightarrow{abcdefghijkl}'];
    }

    #[DataProvider('formulas')]
    public function test_snapshot(string $name, string $tex): void
    {
        $svg = (new SvgRenderer(Math::stixTwoMath()))->render(Math::layout(TexReader::read($tex)->node, 12.0, true));
        $path = __DIR__ . "/../snapshots/svg/$name.svg";
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($path, $svg . "\n");
        }
        self::assertFileExists($path);
        self::assertSame(rtrim((string) file_get_contents($path)), $svg);
    }
}
