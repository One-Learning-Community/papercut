<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\MathNote;

/** Content a reader could not render faithfully: what stands in for it (or nothing) and the note saying why. */
final class Fallback extends Node
{
    public function __construct(public readonly ?Node $content, public readonly MathNote $note)
    {
    }

    /** @return list<Node> */
    public function children(): array
    {
        return $this->content === null ? [] : [$this->content];
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        return $this->content === null ? new Box() : $this->content->layout($c);
    }
}
