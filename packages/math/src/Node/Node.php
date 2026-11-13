<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

abstract class Node
{
    /** @internal Engine detail, not part of papercut/math's public API. */
    abstract public function layout(Context $c): Box;
}
