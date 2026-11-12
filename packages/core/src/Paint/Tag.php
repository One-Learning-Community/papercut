<?php

declare(strict_types=1);

namespace Papercut\Paint;

/**
 * A structure role for a fragment, for tagged PDF. $key is the node path, so a node split
 * over pages can be recognised as the same element.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final readonly class Tag
{
    public const string ARTIFACT = 'Artifact';

    /** Artifact types: page furniture, layout-only content and page backgrounds. */
    public const string PAGINATION = 'Pagination';
    public const string LAYOUT = 'Layout';
    public const string BACKGROUND = 'Background';

    public function __construct(
        public string $role,
        public string $key,
        public ?string $alt = null,
        /** @var array<string, string|int|float> extra structure attributes, e.g. ['O' => 'Table', 'Scope' => 'Column'] */
        public array $attributes = [],
        /** for an artifact: one of the artifact types above */
        public string $artifactType = self::LAYOUT,
        /** key of the row element this cell belongs to when it is painted outside it (a cell spanning rows), or null */
        public ?string $home = null,
        /** for a table cell: its column among its row's cells */
        public ?int $column = null,
    ) {
    }

    public static function artifact(string $key, string $type = self::LAYOUT): self
    {
        return new self(self::ARTIFACT, $key, artifactType: $type);
    }

    /** A copy that belongs in the row element $rowKey. */
    public function homedIn(string $rowKey): self
    {
        return new self($this->role, $this->key, $this->alt, $this->attributes, $this->artifactType, $rowKey, $this->column);
    }

    /** Table cells stay in the structure when they have nothing to draw, so a row keeps all its cells. */
    public function keepsWhenEmpty(): bool
    {
        return $this->role === 'TD' || $this->role === 'TH';
    }
}
