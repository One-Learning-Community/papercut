<?php

declare(strict_types=1);

namespace Papercut\Tests;

use PHPUnit\Framework\TestCase;

final class ArchitectureTest extends TestCase
{
    public function test_only_phppdf_backend_imports_dskripchenko(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if (str_starts_with($path, $src . '/Backend/PhpPdf/')) {
                continue;
            }
            if (str_contains((string) file_get_contents($path), 'Dskripchenko')) {
                $offenders[] = substr($path, strlen($src) + 1);
            }
        }
        self::assertSame([], $offenders, 'Only src/Backend/PhpPdf may reference Dskripchenko\\*');
    }
}
