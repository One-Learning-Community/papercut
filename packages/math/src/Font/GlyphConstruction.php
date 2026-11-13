<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

/** Size variants of a glyph (ascending) and an optional assembly recipe, bottom/left part first. */
final readonly class GlyphConstruction
{
    /**
     * @param list<array{int,int}> $variants [glyph id, advance measurement in font units]
     * @param list<AssemblyPart>|null $parts
     */
    public function __construct(public array $variants, public ?array $parts)
    {
    }
}
