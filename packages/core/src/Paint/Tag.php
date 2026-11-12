<?php

declare(strict_types=1);

namespace Papercut\Paint;

/**
 * SPIKE: a structure role for a fragment, for tagged PDF. $key is the node path, so a node split
 * over pages can be recognised as the same element; an ancestor's key prefixes its descendants'.
 *
 * @internal
 */
final readonly class Tag
{
    public const string ARTIFACT = 'Artifact';

    public function __construct(
        public string $role,
        public string $key,
        public ?string $alt = null,
        /** extra /A attributes, raw PDF, e.g. "<< /O /Table /Scope /Column >>" */
        public ?string $attributes = null,
    ) {
    }
}
