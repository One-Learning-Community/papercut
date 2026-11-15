<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

abstract class Node
{
    /**
     * The child nodes in reading order.
     *
     * @return list<Node>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function children(): array
    {
        return [];
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    abstract public function layout(Context $c): Box;
}
