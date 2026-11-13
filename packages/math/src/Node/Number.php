<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Run;

/** mn: digits with an optional decimal point, drawn upright. Also holds Unicode super/subscript digits as ordinary characters. */
final class Number extends Node
{
    public function __construct(public readonly string $text)
    {
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        return Run::build($c, array_map(fn ($ch) => [mb_ord($ch), $ch], mb_str_split($this->text)));
    }
}
