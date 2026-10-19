<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Backend\Backend;
use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Style\Theme;
use Papercut\Text\FontRegistry;

final readonly class RenderOptions
{
    public function __construct(
        private ?Backend $backend = null,
        private ?FontRegistry $fonts = null,
        private ?Theme $theme = null,
        public OverflowMode $overflow = OverflowMode::Throw,
        public ?\Closure $onWarning = null,
    ) {
    }

    /**
     * Returns the given backend, or a new PhpPdfBackend on each call.
     * Callers (Document::render) should call this once and reuse the result.
     */
    public function backend(): Backend
    {
        return $this->backend ?? new PhpPdfBackend();
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
