<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Image, Node, OverflowMode, Text};
use Papercut\Backend\{Backend, ImageHandle};
use Papercut\Style\{ComputedStyle, Style, Theme};
use Papercut\Text\{FontRegistry, Itemizer};

/**
 * Shared state for one layout run: fonts, theme, images, warnings, the
 * current node path and the overflow mode.
 */
final class LayoutContext
{
    /** @var list<string> */
    private array $path = [];
    private ?Itemizer $itemizer = null;
    /** @var array<string, ImageHandle> */
    private array $images = [];
    /** Path of the deepest node that returned DoesNotFit under fresh constraints; the paginator resets it. */
    public private(set) ?string $lastFailurePath = null;

    public function __construct(
        public readonly FontRegistry $fonts,
        public readonly Theme $theme,
        public readonly Backend $backend,
        public readonly OverflowMode $overflow,
        private readonly ?\Closure $onWarning,
        /** @var list<string> */
        private readonly array $fallbackFamilies = [],
    ) {
    }

    public function itemizer(): Itemizer
    {
        return $this->itemizer ??= new Itemizer($this->fonts, function (string $msg): void {
            $this->warn($msg);
        }, $this->fallbackFamilies);
    }

    /** $withPath false: the message already names its node. */
    public function warn(string $msg, bool $withPath = true): void
    {
        if ($this->onWarning === null) {
            return;
        }
        $path = $withPath ? $this->path() : '';
        ($this->onWarning)($path === '' ? $msg : sprintf('[%s] %s', $path, $msg));
    }

    /** $name may carry a sibling index, e.g. "view[2]". */
    public function pushPath(string $name): void
    {
        $this->path[] = $name;
    }

    public function popPath(): void
    {
        array_pop($this->path);
    }

    public function path(): string
    {
        return implode(' > ', $this->path);
    }

    public function setLastFailurePath(?string $path): void
    {
        $this->lastFailurePath = $path;
    }

    public function image(Image $node): ImageHandle
    {
        $source = $node->source();
        $value = $source['value'];
        if ($value instanceof ImageHandle) {
            return $value;
        }
        if ($source['kind'] === 'file') {
            return $this->images['file:' . $value] ??= $this->backend->loadImageFile($value);
        }
        return $this->images['bytes:' . sha1($value)] ??= $this->backend->loadImageBytes($value);
    }

    /**
     * Theme preset (Text h1-h4/p), then the named theme style, then the
     * node's own style, all applied on top of the parent's computed style.
     */
    public function resolveStyle(Node $node, ComputedStyle $parent): ComputedStyle
    {
        $style = new Style();
        if ($node instanceof Text && $node->preset() !== null) {
            $preset = $node->preset();
            $style = $style->merge($preset === 'p' ? $this->theme->paragraph() : $this->theme->heading((int) substr($preset, 1)));
        }
        if (method_exists($node, 'styleName')) {
            $name = $node->styleName();
            if (is_string($name)) {
                $style = $style->merge($this->theme->named($name));
            }
        }
        return $parent->child($style->merge($node->style()));
    }
}
