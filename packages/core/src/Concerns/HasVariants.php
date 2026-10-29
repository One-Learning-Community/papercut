<?php

declare(strict_types=1);

namespace Papercut\Concerns;

use InvalidArgumentException;

/**
 * Variants for decorators: `variant('large')` makes every part the decorator styles also apply
 * `<part>.large` when the theme defines it. A variant the theme does not style is a no-op.
 */
trait HasVariants
{
    /** @var list<string> */
    private array $variants = [];

    /** Adds one or more space-separated variant words, each made of a-z, 0-9 and hyphens. */
    public function variant(string $names): static
    {
        $words = preg_split('/\s+/', trim($names), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            throw new InvalidArgumentException('A variant must not be empty.');
        }
        foreach ($words as $word) {
            if (preg_match('/^[a-z0-9-]+$/', $word) !== 1) {
                throw new InvalidArgumentException("A variant is a lowercase word of a-z, 0-9 and hyphens, got '$word'.");
            }
        }
        $clone = clone $this;
        array_push($clone->variants, ...$words);
        return $clone;
    }

    /** @return list<string> */
    public function variants(): array
    {
        return $this->variants;
    }

    /**
     * A part's style names: the built-in name, an optional tone name, then an optional name per variant.
     * Names ending in `?` are skipped by the theme when it does not define them.
     */
    protected function part(string $name, ?string $tone = null): string
    {
        $names = $tone === null ? $name : "$name $name.$tone";
        foreach ($this->variants as $variant) {
            $names .= " $name.$variant?";
        }
        return $names;
    }
}
