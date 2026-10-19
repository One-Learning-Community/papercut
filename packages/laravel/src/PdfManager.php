<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Document;
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Preview\PreviewRegistry;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\OverflowMode;
use Papercut\RenderOptions;
use Papercut\Style\{Style, Theme};
use Papercut\Text\FontRegistry;
use Papercut\Value\Color;

final class PdfManager
{
    private ?RenderOptions $options = null;

    public function __construct(private readonly NodeRegistry $registry, private readonly PreviewRegistry $previews)
    {
    }

    /** @param \Closure|array<string, mixed> $data */
    public function preview(string $view, \Closure|array $data = []): void
    {
        $this->previews->register($view, $data);
    }

    /** @param array<string, mixed> $data */
    public function view(string $view, array $data = []): PendingPdf
    {
        $document = $this->registry->scope(function () use ($view, $data): object {
            $html = view($view, $data)->render();
            $segments = array_values(array_filter(
                $this->registry->segments($html),
                static fn (object|string $s): bool => !is_string($s) || trim($s) !== '',
            ));

            if (count($segments) !== 1 || !$segments[0] instanceof Document) {
                throw new InvalidStructure('A Papercut view must have exactly one <x-pdf::document> root; found ' . $this->describe($segments) . " in view [$view]");
            }
            if ($this->registry->remaining() > 0) {
                if (preg_match('/\[\[pc:[0-9a-f]{16}:\d+\]\]/i', $html) === 1) {
                    throw new InvalidStructure("A slot containing <x-pdf::*> tags was modified as a string (e.g. strtoupper(\$slot)) in view [$view]");
                }
                throw new InvalidStructure("Some <x-pdf::*> elements were rendered outside the document in view [$view]");
            }

            return $segments[0];
        }, $view);
        assert($document instanceof Document);

        return $this->make($document);
    }

    public function make(Document $doc): PendingPdf
    {
        return new PendingPdf($doc, $this->options());
    }

    public function options(): RenderOptions
    {
        return $this->options ??= $this->buildOptions();
    }

    private function buildOptions(): RenderOptions
    {
        $backend = new PhpPdfBackend(self::cacheDir());
        $fonts = FontRegistry::withDefaults($backend);
        foreach (FontConfig::faces() as $face) {
            $fonts->register($face['family'], $face['path'], $face['weight'], $face['style']);
        }

        $cfg = (array) config('papercut.theme', []);
        try {
            $color = isset($cfg['color']) ? Color::parse((string) $cfg['color']) : null;
        } catch (InvalidValue $e) {
            throw $e->within('in config papercut.theme.color');
        }
        $theme = Theme::default()->with(
            fontFamily: isset($cfg['font_family']) ? (string) $cfg['font_family'] : null,
            fontSize: self::positive($cfg, 'font_size'),
            color: $color,
            lineHeight: self::positive($cfg, 'line_height'),
        );
        /** @var array<string, array<string, mixed>> $styles */
        $styles = (array) config('papercut.styles', []);
        foreach ($styles as $name => $attrs) {
            try {
                $theme = $theme->withStyle((string) $name, Style::fromAttributes($attrs));
            } catch (InvalidValue|UnknownAttribute $e) {
                throw $e->within("in config papercut.styles.$name");
            }
        }

        $overflow = config('papercut.overflow');
        $key = is_string($overflow) ? strtolower($overflow) : $overflow;
        if ($key !== null && $key !== 'throw' && $key !== 'clip') {
            throw new \InvalidArgumentException('Invalid papercut.overflow: ' . (is_scalar($overflow) ? var_export($overflow, true) : get_debug_type($overflow)) . '; expected throw, clip or null');
        }
        $mode = match ($key) {
            'throw' => OverflowMode::Throw,
            'clip' => OverflowMode::Clip,
            default => app()->environment('local', 'testing') ? OverflowMode::Throw : OverflowMode::Clip,
        };

        return new RenderOptions(
            backend: $backend,
            fonts: $fonts,
            theme: $theme,
            overflow: $mode,
            fallbackFamilies: FontConfig::fallbacks(),
            cacheDir: self::cacheDir(),
            onWarning: static function (string $msg): void {
                logger()->warning('[papercut] ' . $msg);
            },
        );
    }

    private static function cacheDir(): string|false|null
    {
        $dir = config('papercut.cache_dir');
        if ($dir === null || $dir === false || (is_string($dir) && $dir !== '')) {
            return $dir;
        }
        throw new \InvalidArgumentException('Invalid papercut.cache_dir: ' . (is_scalar($dir) ? var_export($dir, true) : get_debug_type($dir)) . '; expected a directory path, false or null');
    }

    /** @param array<mixed> $cfg */
    private static function positive(array $cfg, string $key): ?float
    {
        if (!isset($cfg[$key])) {
            return null;
        }
        $v = $cfg[$key];
        if (!is_numeric($v) || (float) $v <= 0) {
            throw new \InvalidArgumentException("Invalid papercut.theme.$key: " . (is_scalar($v) ? var_export($v, true) : get_debug_type($v)) . '; expected a number greater than 0');
        }

        return (float) $v;
    }

    /** @param list<object|string> $segments */
    private function describe(array $segments): string
    {
        if ($segments === []) {
            return 'nothing';
        }
        if (count($segments) > 1) {
            return count($segments) . ' nodes';
        }
        $only = $segments[0];
        if (is_string($only)) {
            return 'text';
        }
        $short = substr((string) strrchr('\\' . $only::class, '\\'), 1);

        return 'a <' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short)) . '>';
    }
}
