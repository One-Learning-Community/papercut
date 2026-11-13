<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

/** One MathKern table: kern values stepped by correction height, all in font units. */
final readonly class MathKern
{
    /**
     * @param list<int> $heights correction heights, ascending
     * @param list<int> $values kern values; one more than heights
     */
    public function __construct(public array $heights, public array $values)
    {
    }

    public static function read(Binary $b, int $o): self
    {
        $n = $b->u16($o);
        $heights = $values = [];
        for ($i = 0; $i < $n; $i++) {
            $heights[] = $b->i16($o + 2 + $i * 4);
        }
        for ($i = 0; $i <= $n; $i++) {
            $values[] = $b->i16($o + 2 + $n * 4 + $i * 4);
        }

        return new self($heights, $values);
    }

    /** The kern at a height: the value of the first step whose correction height lies above it. */
    public function at(float $height): int
    {
        foreach ($this->heights as $i => $h) {
            if ($h > $height) {
                return $this->values[$i];
            }
        }

        return $this->values[count($this->heights)];
    }
}
