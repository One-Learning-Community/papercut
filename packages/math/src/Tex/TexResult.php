<?php

declare(strict_types=1);

namespace Quoin\Math\Tex;

use Quoin\Math\Math;
use Quoin\Math\MathNote;
use Quoin\Math\Node\Node;

/** A formula read from TeX: the tree, and whether the source asked for display (block) style. */
final readonly class TexResult
{
    public function __construct(public Node $node, public bool $display)
    {
    }

    /** @return list<MathNote> what the reader could not render faithfully */
    public function notes(): array
    {
        return Math::notes($this->node);
    }
}
