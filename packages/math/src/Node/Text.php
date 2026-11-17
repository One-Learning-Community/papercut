<?php

declare(strict_types=1);

namespace Quoin\Math\Node;

use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;
use Quoin\Math\Layout\Run;
use Quoin\Math\Utf8;

/** mtext: literal upright text; spaces are kept. */
final class Text extends Node
{
    public readonly string $text;

    /** Invalid UTF-8 in $text is replaced silently here; the Math builders also record a note. */
    public function __construct(string $text)
    {
        $this->text = Utf8::clean($text);
    }

    /** @internal Engine detail, not public API. */
    public function layout(Context $c): Box
    {
        return Run::build($c, array_map(fn ($ch) => [mb_ord($ch), $ch], mb_str_split($this->text)));
    }
}
