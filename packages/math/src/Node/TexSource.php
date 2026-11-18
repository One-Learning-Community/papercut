<?php

declare(strict_types=1);

namespace Quoin\Math\Node;

use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;

/**
 * Content with the TeX it was read from, for a construct the reader takes in but does not draw (a box, a color, a
 * strike-through, an equation number). It lives outside the Fallback that notes the construct, because a Fallback is
 * only made for the first note of its kind. Drawing and every other writer see through it.
 *
 * With a $close string, $tex is the head and the TeX writer writes `$tex . content . $close`, so what is inside is
 * written the way the writer writes any content. With $close null, $tex is the whole source, written as it stands.
 */
final class TexSource extends Node
{
    public function __construct(public readonly Node $content, public readonly string $tex, public readonly ?string $close = null)
    {
    }

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not public API.
     */
    public function children(): array
    {
        return [$this->content];
    }

    /** @internal Engine detail, not public API. */
    public function layout(Context $c): Box
    {
        return $this->content->layout($c);
    }
}
