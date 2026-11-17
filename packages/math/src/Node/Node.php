<?php

declare(strict_types=1);

namespace Quoin\Math\Node;

use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;

abstract class Node
{
    /**
     * The child nodes in reading order.
     *
     * @return list<Node>
     *
     * @internal Engine detail, not public API.
     */
    public function children(): array
    {
        return [];
    }

    /** @internal Engine detail, not public API. */
    abstract public function layout(Context $c): Box;
}
