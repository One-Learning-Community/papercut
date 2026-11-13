<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

/** One drawing primitive. Coordinates are points, origin at the formula's left edge on its baseline, y grows DOWN. */
interface DisplayItem
{
    /** @internal Engine detail, not part of papercut/math's public API. */
    public function translated(float $dx, float $dy): static;
}
