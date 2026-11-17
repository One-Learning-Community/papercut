<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\DisplayItem;

/** A display item that counts how often it is copied. */
final class CountedItem implements DisplayItem
{
    public static int $copies = 0;

    public function __construct(public float $x = 0.0, public float $y = 0.0)
    {
    }

    public function translated(float $dx, float $dy): static
    {
        self::$copies++;

        return new self($this->x + $dx, $this->y + $dy);
    }
}
