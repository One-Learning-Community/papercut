<?php

declare(strict_types=1);

namespace Quoin\Math\Layout;

use Quoin\Math\Font\TrueTypeFont;

/**
 * Immutable layout state: font, base size, script level and math style (display or inline/text).
 * $cramped is TeX's cramped style (denominators, radicands, subscripts): superscripts sit lower in it.
 * $scriptAlternates turns the font's `ssty` script-size glyph forms on or off.
 * $fallbackAdvance, when set, is asked for the advance of a character the font has no glyph for.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final readonly class Context
{
    /** Font size at this level, in points (asked for on every glyph, so worked out once). */
    private float $size;

    /** Points per font unit at this level. */
    private float $unit;

    public function __construct(
        public TrueTypeFont $font,
        public float $baseSize,
        public int $level = 0,
        public bool $display = true,
        public bool $tight = false,
        public bool $cramped = false,
        public bool $scriptAlternates = true,
        /** @var (\Closure(string, float): ?float)|null advance in points of a character the font lacks, at a size; null keeps the .notdef advance */
        public ?\Closure $fallbackAdvance = null,
    ) {
        $m = $font->math();
        $this->size = $baseSize * match (true) {
            $level <= 0 => 1.0,
            $level === 1 => $m->constant('scriptPercentScaleDown') / 100,
            default => $m->constant('scriptScriptPercentScaleDown') / 100,
        };
        $this->unit = $this->size / $font->unitsPerEm;
    }

    /** Font size for this script level, in points. */
    public function size(): float
    {
        return $this->size;
    }

    /** Points per font unit at this level. */
    public function unit(): float
    {
        return $this->unit;
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
        return new self($this->font, $this->baseSize, $this->level + $by, false, true, $this->cramped || $cramped, $this->scriptAlternates, $this->fallbackAdvance);
    }

    /** The same context in display or inline (text) style. */
    public function withDisplay(bool $display): self
    {
        return new self($this->font, $this->baseSize, $this->level, $display, $this->tight, $this->cramped, $this->scriptAlternates, $this->fallbackAdvance);
    }

    /** The same context in cramped style (radicands). */
    public function cramped(): self
    {
        return new self($this->font, $this->baseSize, $this->level, $this->display, $this->tight, true, $this->scriptAlternates, $this->fallbackAdvance);
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
            $this->fallbackAdvance,
        );
    }
}
