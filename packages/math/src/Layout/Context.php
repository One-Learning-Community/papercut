<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

use Papercut\Math\Font\TrueTypeFont;

/**
 * Immutable layout state: font, base size, script level and math style (display or inline/text).
 * $cramped is TeX's cramped style (denominators, radicands, subscripts): superscripts sit lower in it.
 * $scriptAlternates turns the font's `ssty` script-size glyph forms on or off.
 */
final readonly class Context
{
    public function __construct(
        public TrueTypeFont $font,
        public float $baseSize,
        public int $level = 0,
        public bool $display = true,
        public bool $tight = false,
        public bool $cramped = false,
        public bool $scriptAlternates = true,
    ) {
    }

    /** Font size for this script level, in points. */
    public function size(): float
    {
        $m = $this->font->math();

        return $this->baseSize * match (true) {
            $this->level <= 0 => 1.0,
            $this->level === 1 => $m->constant('scriptPercentScaleDown') / 100,
            default => $m->constant('scriptScriptPercentScaleDown') / 100,
        };
    }

    /** Points per font unit at this level. */
    public function unit(): float
    {
        return $this->size() / $this->font->unitsPerEm;
    }

    /** A MATH constant in points at this level. */
    public function k(string $name): float
    {
        return $this->font->math()->constant($name) * $this->unit();
    }

    /** MathML spacing: n/18 em at this level. */
    public function mu(float $n): float
    {
        return $n / 18 * $this->size();
    }

    /** One script level further in (or $by); $cramped forces cramped style, as subscripts do. */
    public function script(int $by = 1, bool $cramped = false): self
    {
        return new self($this->font, $this->baseSize, $this->level + $by, false, true, $this->cramped || $cramped, $this->scriptAlternates);
    }

    /** The same context in cramped style (radicands). */
    public function cramped(): self
    {
        return new self($this->font, $this->baseSize, $this->level, $this->display, $this->tight, true, $this->scriptAlternates);
    }

    /**
     * Context for fraction numerators/denominators: display fractions keep the size, text fractions shrink.
     * A denominator is cramped.
     */
    public function fractionChild(bool $denominator = false): self
    {
        return new self(
            $this->font,
            $this->baseSize,
            $this->display ? $this->level : $this->level + 1,
            false,
            $this->tight,
            $this->cramped || $denominator,
            $this->scriptAlternates,
        );
    }
}
