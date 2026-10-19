<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\{Document, Page, RenderOptions};
use Papercut\Tests\Support\FakeBackend;
use Papercut\Text\FontRegistry;
use PHPUnit\Framework\TestCase;

final class FallbackFamiliesTest extends TestCase
{
    /**
     * @param list<string> $fallbacks
     * @return list<string>
     */
    private function warningsFor(array $fallbacks): array
    {
        $dir = __DIR__ . '/../../resources/fonts/Inter';
        $backend = new FakeBackend(coverAll: ['Inter-Italic.ttf']);
        $fonts = FontRegistry::withDefaults($backend)->register('Cjk', "$dir/Inter-Italic.ttf");
        $warnings = [];
        Document::make(Page::a4()->body('Hello 日本語'))->render(new RenderOptions(
            backend: $backend,
            fonts: $fonts,
            onWarning: static function (string $m) use (&$warnings): void { $warnings[] = $m; },
            fallbackFamilies: $fallbacks,
        ));
        return $warnings;
    }

    public function testMissingGlyphsWarnWithoutFallback(): void
    {
        $this->assertNotSame([], $this->warningsFor([]));
    }

    public function testFallbackFamilyCoverageIsUsed(): void
    {
        $this->assertSame([], $this->warningsFor(['Cjk']));
    }
}
