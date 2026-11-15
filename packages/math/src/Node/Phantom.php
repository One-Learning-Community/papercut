<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

/** mphantom: takes the content's width, height and depth and draws nothing. */
final class Phantom extends Node
{
    public function __construct(public readonly Node $content)
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
        $inner = $this->content->layout($c);

        return new Box($inner->width, $inner->ascent, $inner->descent);
    }
}
