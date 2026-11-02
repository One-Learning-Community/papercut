<?php

declare(strict_types=1);

namespace Papercut\Tests;

use PHPUnit\Framework\TestCase;

final class ArchitectureTest extends TestCase
{
    /** Backend and Paint are internal: no custom backends. These two stay public so a backend can be configured. */
    public function test_backend_and_paint_types_are_internal(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $public = ['Backend/PhpPdf/PhpPdfBackend.php', 'Backend/PhpPdf/FontStateCache.php'];
        $missing = [];
        foreach (['Backend', 'Paint'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $rel = substr($file->getPathname(), strlen($src) + 1);
                if ($file->getExtension() !== 'php' || in_array($rel, $public, true)) {
                    continue;
                }
                $code = (string) file_get_contents($file->getPathname());
                if (preg_match('/\/\*\*(?:(?!\*\/).)*@internal(?:(?!\*\/).)*\*\/\s*(?:#\[[^\n]*\]\s*)*(?:final |abstract |readonly )*(?:class|interface|enum|trait) /s', $code) !== 1) {
                    $missing[] = $rel;
                }
            }
        }
        sort($missing);
        self::assertSame([], $missing, 'Mark these @internal, or add them to the public list with a reason');
    }

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
            if (stripos((string) file_get_contents($path), 'Dskripchenko') !== false) {
                $offenders[] = substr($path, strlen($src) + 1);
            }
        }
        self::assertSame([], $offenders, 'Only src/Backend/PhpPdf may reference Dskripchenko\\*');
    }

    public function test_only_the_bacon_adapter_imports_bacon_qr_code(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || str_starts_with($path, $src . '/Barcode/Bacon/')) {
                continue;
            }
            if (stripos((string) file_get_contents($path), 'BaconQrCode') !== false) {
                $offenders[] = substr($path, strlen($src) + 1);
            }
        }
        self::assertSame([], $offenders, 'Only src/Barcode/Bacon may reference BaconQrCode\\*, since the package is optional');
    }

    public function test_tree_and_style_do_not_depend_on_layout_or_backend(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $offenders = [];
        foreach (['Tree', 'Style'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $code = (string) file_get_contents($file->getPathname());
                if (preg_match('/Papercut\\\\(Layout|Backend)\b/', $code) === 1) {
                    $offenders[] = substr($file->getPathname(), strlen($src) + 1);
                }
            }
        }
        self::assertSame([], $offenders, 'src/Tree and src/Style must not reference Papercut\\Layout or Papercut\\Backend');
    }

    public function test_core_never_imports_the_docx_package(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (str_contains($code, 'Papercut\\Docx') || str_contains($code, 'PhpDocx')) {
                $offenders[] = substr($file->getPathname(), strlen($src) + 1);
            }
        }
        self::assertSame([], $offenders, 'Core must not depend on papercut/docx or php-docx');
    }
}
