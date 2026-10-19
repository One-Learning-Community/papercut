<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Backend\Backend;
use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Style\Theme;
use Papercut\Text\FontRegistry;

final readonly class RenderOptions
{
    /**
     * @param list<string> $fallbackFamilies families tried, in order, for glyphs the primary font lacks
     * @param string|false|null $cacheDir directory for the default backend's font kerning cache
     *        (null = sys_get_temp_dir()/papercut-cache, false = no disk cache); ignored when $backend is given
     */
    public function __construct(
        private ?Backend $backend = null,
        private ?FontRegistry $fonts = null,
        private ?Theme $theme = null,
        public OverflowMode $overflow = OverflowMode::Throw,
        public ?\Closure $onWarning = null,
        public array $fallbackFamilies = [],
        public string|false|null $cacheDir = null,
    ) {
    }

    /**
     * Returns the given backend, or a new PhpPdfBackend on each call.
     * Callers (Document::render) should call this once and reuse the result.
     */
    public function backend(): Backend
    {
        return $this->backend ?? new PhpPdfBackend($this->cacheDir);
    }

    public function fonts(Backend $backend): FontRegistry
    {
        return $this->fonts ?? FontRegistry::withDefaults($backend);
    }

    public function theme(): Theme
    {
        return $this->theme ?? Theme::default();
    }
}
