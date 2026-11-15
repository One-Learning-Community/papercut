<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

/** mstyle displaystyle: lays its content out in display or inline (text) style whatever the surrounding style is. */
final class DisplayStyle extends Node
{
    public function __construct(public readonly Node $content, public readonly bool $display)
    {
    }

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function children(): array
    {
        return [$this->content];
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        return $this->content->layout($c->withDisplay($this->display));
    }
}
