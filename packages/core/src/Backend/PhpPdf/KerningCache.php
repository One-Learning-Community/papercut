<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Font\Ttf\{KerningTable, TtfFile};

/**
 * Persists a font's GPOS pair-kerning table on disk, so a fresh process does
 * not re-parse GPOS (tens of milliseconds per font) on its first measurement.
 *
 * Entries are keyed by the font's real path, mtime and size, the php-pdf
 * version and this cache's format, and written atomically (temp file +
 * rename). Every failure (unwritable or foreign-owned directory, corrupt
 * file, changed php-pdf internals) falls back silently to php-pdf's own
 * lazy, in-memory parse; the measured and drawn kerning is the same either way.
 */
final class KerningCache
{
    private const int FORMAT = 1;
    private const string MARKER = 'papercut-kern';

    /** $dir null disables the cache. */
    public function __construct(private readonly ?string $dir)
    {
    }

    public static function defaultDir(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'papercut-cache';
    }

    /** Fills $ttf's kerning table from the cache, or parses it and stores it. Never throws. */
    public function prime(TtfFile $ttf, string $realPath): void
    {
        // php-pdf shares parsed fonts process-wide: an already parsed table is kept as is.
        if ($this->dir === null || !self::injectable($ttf) || self::parsed($ttf)) {
            return;
        }
        try {
            $file = $this->file($realPath);
            if ($file === null) {
                return;
            }
            $pairs = $this->read($file);
            if ($pairs !== null) {
                self::inject($ttf, $pairs);
                return;
            }
            $table = $ttf->kerningTable();
            $this->write($file, $table === null ? [] : self::pairsOf($table));
        } catch (\Throwable) {
            // a cache problem never fails a render: php-pdf parses lazily instead
        }
    }

    private function file(string $realPath): ?string
    {
        clearstatcache(true, $realPath);
        $mtime = @filemtime($realPath);
        $size = @filesize($realPath);
        if ($mtime === false || $size === false) {
            return null;
        }
        $key = sha1(implode("\0", [self::FORMAT, $realPath, $mtime, $size, self::libraryVersion()]));
        return $this->dir . DIRECTORY_SEPARATOR . 'kern-' . $key . '.ser';
    }

    /** @return ?array<int, array<int, int>> */
    private function read(string $file): ?array
    {
        if (!is_file($file) || !$this->trustedDir()) {
            return null;
        }
        $bytes = @file_get_contents($file);
        if ($bytes === false) {
            return null;
        }
        $payload = @unserialize($bytes, ['allowed_classes' => false]);
        if (!is_array($payload) || ($payload[self::MARKER] ?? null) !== self::FORMAT || !is_array($payload['pairs'] ?? null)) {
            return null;
        }
        /** @var array<int, array<int, int>> */
        return $payload['pairs'];
    }

    /** @param array<int, array<int, int>> $pairs */
    private function write(string $file, array $pairs): void
    {
        $dir = (string) $this->dir;
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return;
        }
        if (!$this->trustedDir() || !is_writable($dir)) {
            return;
        }
        $tmp = @tempnam($dir, 'tmp-');
        if ($tmp === false) {
            return;
        }
        $bytes = serialize([self::MARKER => self::FORMAT, 'pairs' => $pairs]);
        if (@file_put_contents($tmp, $bytes) !== strlen($bytes) || !@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    /** A directory owned by someone else (e.g. planted in a shared temp dir) is never read or written. */
    private function trustedDir(): bool
    {
        if (!function_exists('posix_geteuid')) {
            return true;
        }
        $owner = @fileowner((string) $this->dir);
        return $owner === false || $owner === posix_geteuid();
    }

    private static function libraryVersion(): string
    {
        if (class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('dskripchenko/php-pdf')) {
            return (string) \Composer\InstalledVersions::getVersion('dskripchenko/php-pdf');
        }
        return 'unknown';
    }

    /** False when php-pdf's lazy-parse internals moved: the cache is then skipped. */
    private static function injectable(TtfFile $ttf): bool
    {
        return self::has($ttf::class, 'kerningTable', 'kerningParsed') && self::has(KerningTable::class, 'pairs', 'pairCount');
    }

    private static function has(string $class, string ...$properties): bool
    {
        foreach ($properties as $property) {
            if (!property_exists($class, $property)) {
                return false;
            }
        }
        return true;
    }

    private static function parsed(TtfFile $ttf): bool
    {
        return \Closure::bind(fn (): bool => $this->kerningParsed, $ttf, TtfFile::class)();
    }

    /** @return array<int, array<int, int>> */
    private static function pairsOf(KerningTable $table): array
    {
        /** @var array<int, array<int, int>> */
        return \Closure::bind(fn (): array => $this->pairs, $table, KerningTable::class)();
    }

    /** @param array<int, array<int, int>> $pairs */
    private static function inject(TtfFile $ttf, array $pairs): void
    {
        $table = null;
        if ($pairs !== []) {
            $table = new KerningTable();
            \Closure::bind(function () use ($pairs): void {
                $this->pairs = $pairs;
                $this->pairCount = array_sum(array_map('count', $pairs));
            }, $table, KerningTable::class)();
        }
        \Closure::bind(function () use ($table): void {
            $this->kerningTable = $table;
            $this->kerningParsed = true;
        }, $ttf, TtfFile::class)();
    }
}
