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

    /** @return list<FontFace> the bundled Inter faces, in registration order */
    public static function bundled(): array
    {
        $dir = __DIR__ . '/../../resources/fonts/Inter';
        return [
            new FontFace('Inter', "$dir/Inter-Regular.ttf", 400),
            new FontFace('Inter', "$dir/Inter-Italic.ttf", 400, FontStyle::Italic),
            new FontFace('Inter', "$dir/Inter-Bold.ttf", 700),
            new FontFace('Inter', "$dir/Inter-BoldItalic.ttf", 700, FontStyle::Italic),
        ];
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
