<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use PHPUnit\Framework\TestCase;

final class ArchitectureTest extends TestCase
{
    /** The builder, nodes, boxes and writers are the public API; the layout state, font readers and tokenizer are the engine. */
    private const INTERNAL = [
        'Font/AssemblyPart.php', 'Font/Binary.php', 'Font/Coverage.php', 'Font/GlyphConstruction.php', 'Font/Gsub.php',
        'Font/MathKern.php', 'Font/MathTable.php', 'Layout/Context.php', 'Layout/Run.php', 'Layout/Stretcher.php',
        'Node/Forms.php', 'OperatorDictionary.php', 'Render/Xml.php', 'Styles.php', 'Tokenizer.php',
    ];

    public function test_engine_types_are_internal_and_the_rest_are_not(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $internal = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (preg_match('/\/\*\*(?:(?!\*\/).)*@internal(?:(?!\*\/).)*\*\/\s*(?:final |abstract |readonly )*(?:class|interface|enum|trait) /s', $code) === 1) {
                $internal[] = substr($file->getPathname(), strlen($src) + 1);
            }
        }
        sort($internal);
        $expected = self::INTERNAL;
        sort($expected);
        self::assertSame($expected, $internal);
    }
}
