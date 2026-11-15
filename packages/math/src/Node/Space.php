<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

/** mspace: horizontal space of $em times the font size, no height. A negative width pulls the next item back. */
final class Space extends Node
{
    public function __construct(public readonly float $em)
    {
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        return new Box($this->em * $c->size());
    }
}
