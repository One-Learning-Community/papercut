<?php

declare(strict_types=1);

namespace Papercut\Tests\Backend;

use Papercut\{Document, Page, RenderOptions, Text};
use Dskripchenko\PhpPdf\Font\Ttf\TtfFile;
use Papercut\Backend\PhpPdf\{KerningCache, PhpPdfBackend};
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class KerningCacheTest extends TestCase
{
    private const string INTER = __DIR__ . '/../../resources/fonts/Inter/Inter-Regular.ttf';

    private string $dir;
    /** A private copy of Inter, so php-pdf's process-wide font cache has not parsed it yet. */
    private string $font;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/papercut-kern-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $fonts = $this->dir . '-fonts';
        @mkdir($fonts);
        $this->font = $fonts . '/Inter-Regular.ttf';
        copy(self::INTER, $this->font);
    }

    /** Simulates a fresh process: php-pdf keeps parsed fonts (and their kerning) in a static cache. */
    private static function forgetParsedFonts(): void
    {
        (new \ReflectionProperty(TtfFile::class, 'cache'))->setValue(null, []);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        @unlink($this->font);
        @rmdir($this->dir . '-fonts');
        self::forgetParsedFonts();
    }

    /** @return list<string> */
    private function cacheFiles(): array
    {
        return glob($this->dir . '/kern-*') ?: [];
    }

    public function test_default_dir_is_under_the_system_temp_dir(): void
    {
        self::assertSame(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'papercut-cache', KerningCache::defaultDir());
    }

    public function test_first_load_writes_the_pair_table_and_measurements_match_an_uncached_font(): void
    {
        $uncached = (new PhpPdfBackend(kerningCacheDir: false))->loadFont(self::INTER);
        $cold = (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        self::assertCount(1, $this->cacheFiles());
        self::forgetParsedFonts();
        $warm = (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        self::assertCount(1, $this->cacheFiles());
        foreach (['AV', 'To', 'Wavy Tea', 'hello'] as $text) {
            self::assertSame($uncached->advance($text, 12.0), $cold->advance($text, 12.0), $text);
            self::assertSame($uncached->advance($text, 12.0), $warm->advance($text, 12.0), $text);
        }
        self::assertLessThan($uncached->advance('A', 12.0) + $uncached->advance('V', 12.0), $warm->advance('AV', 12.0));
    }

    public function test_a_warm_load_reads_the_pair_table_from_the_cache(): void
    {
        (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        [$file] = $this->cacheFiles();
        // Replace the cached table with one that kerns nothing: a warm load must use it.
        $payload = unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        self::assertIsArray($payload);
        $payload['pairs'] = [];
        file_put_contents($file, serialize($payload));
        $plain = (new PhpPdfBackend(kerningCacheDir: false))->loadFont(self::INTER);
        self::forgetParsedFonts();
        $warm = (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        self::assertSame($plain->advance('A', 12.0) + $plain->advance('V', 12.0), $warm->advance('AV', 12.0));
    }

    public function test_a_table_php_pdf_already_parsed_in_this_process_is_kept(): void
    {
        (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        [$file] = $this->cacheFiles();
        file_put_contents($file, serialize(['papercut-kern' => 1, 'pairs' => []]));
        $plain = (new PhpPdfBackend(kerningCacheDir: false))->loadFont(self::INTER);
        $font = (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        self::assertSame($plain->advance('AV', 12.0), $font->advance('AV', 12.0));
    }

    public function test_corrupt_cache_files_are_ignored_and_replaced(): void
    {
        (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        [$file] = $this->cacheFiles();
        file_put_contents($file, 'garbage');
        $plain = (new PhpPdfBackend(kerningCacheDir: false))->loadFont(self::INTER);
        self::forgetParsedFonts();
        $font = (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        self::assertSame($plain->advance('AV', 12.0), $font->advance('AV', 12.0));
        self::assertNotSame('garbage', file_get_contents($file));
    }

    public function test_the_key_changes_with_the_font_file_mtime(): void
    {
        (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        touch($this->font, time() + 10);
        (new PhpPdfBackend(kerningCacheDir: $this->dir))->loadFont($this->font);
        self::assertCount(2, $this->cacheFiles());
    }

    public function test_an_unwritable_cache_dir_falls_back_silently(): void
    {
        file_put_contents($this->dir, 'not a directory');
        try {
            $plain = (new PhpPdfBackend(kerningCacheDir: false))->loadFont(self::INTER);
            $font = (new PhpPdfBackend(kerningCacheDir: $this->dir . '/sub'))->loadFont($this->font);
            self::assertSame($plain->advance('AV', 12.0), $font->advance('AV', 12.0));
        } finally {
            unlink($this->dir);
        }
    }

    public function test_render_options_choose_the_cache_dir_and_output_is_identical_cold_warm_and_uncached(): void
    {
        $doc = Document::make(Page::a4()->body(Text::make('AVATAR Wavy Tea, To you.')->size(pt(18)), Text::make('Bold VAT')->bold()))
            ->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $uncached = $doc->render(new RenderOptions(cacheDir: false));
        self::forgetParsedFonts();
        $cold = $doc->render(new RenderOptions(cacheDir: $this->dir));
        self::assertCount(2, $this->cacheFiles()); // regular + bold
        self::forgetParsedFonts();
        $warm = $doc->render(new RenderOptions(cacheDir: $this->dir));
        self::assertSame($uncached, $cold);
        self::assertSame($uncached, $warm);
    }
}
