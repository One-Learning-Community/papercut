<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Run;
use Papercut\Math\Utf8;

/** mtext: literal upright text; spaces are kept. */
final class Text extends Node
{
    public readonly string $text;

    /** Invalid UTF-8 in $text is replaced silently here; the Math builders also record a note. */
    public function __construct(string $text)
    {
        $this->text = Utf8::clean($text);
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        return Run::build($c, array_map(fn ($ch) => [mb_ord($ch), $ch], mb_str_split($this->text)));
    }
}
