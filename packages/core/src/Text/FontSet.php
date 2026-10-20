<?php

declare(strict_types=1);

namespace Papercut\Text;

/** Format-neutral font configuration: extra faces on top of the bundled Inter, and glyph fallback families. */
final readonly class FontSet
{
    /**
     * @param list<FontFace> $faces
     * @param list<string> $fallbacks families tried, in order, for glyphs the primary font lacks
     */
    public function __construct(
        public array $faces = [],
        public array $fallbacks = [],
    ) {
    }

    public function with(FontFace ...$faces): self
    {
        return new self([...$this->faces, ...array_values($faces)], $this->fallbacks);
    }

    public function withFallbacks(string ...$families): self
    {
        return new self($this->faces, [...$this->fallbacks, ...array_values($families)]);
    }
}
