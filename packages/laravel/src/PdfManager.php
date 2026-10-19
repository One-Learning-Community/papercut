<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Document;
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
                throw new InvalidStructure("Some <x-pdf::*> elements were rendered outside the document in view [$view]");
            }

            return $segments[0];
        });
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
        $backend = new PhpPdfBackend();
        $fonts = FontRegistry::withDefaults($backend);
        foreach (FontConfig::faces() as $face) {
            $fonts->register($face['family'], $face['path'], $face['weight'], $face['style']);
        }

        $cfg = (array) config('papercut.theme', []);
        $theme = Theme::default()->with(
            fontFamily: isset($cfg['font_family']) ? (string) $cfg['font_family'] : null,
            fontSize: isset($cfg['font_size']) ? (float) $cfg['font_size'] : null,
            color: isset($cfg['color']) ? Color::parse((string) $cfg['color']) : null,
            lineHeight: isset($cfg['line_height']) ? (float) $cfg['line_height'] : null,
        );
        /** @var array<string, array<string, mixed>> $styles */
        $styles = (array) config('papercut.styles', []);
        foreach ($styles as $name => $attrs) {
            $theme = $theme->withStyle((string) $name, Style::fromAttributes($attrs));
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
            onWarning: static function (string $msg): void {
                logger()->warning('[papercut] ' . $msg);
            },
        );
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
