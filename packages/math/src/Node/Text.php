<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Run;

/** mtext: literal upright text; spaces are kept. */
final class Text extends Node
{
    public function __construct(public readonly string $text)
    {
    }

    public function layout(Context $c): Box
    {
        return Run::build($c, array_map(fn ($ch) => [mb_ord($ch), $ch], mb_str_split($this->text)));
    }
}
