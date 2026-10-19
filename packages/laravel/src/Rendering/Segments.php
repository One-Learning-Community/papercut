<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Text;

final class Segments
{
    /**
     * @param list<object|string> $segs
     * @return list<object>
     */
    public static function nodes(array $segs, string $tag): array
    {
        $out = [];
        foreach ($segs as $seg) {
            if (is_string($seg)) {
                if (trim($seg) !== '') {
                    $out[] = Text::markup(trim($seg));
                }
            } else {
                $out[] = $seg;
            }
        }

        return $out;
    }

    /** @param list<object|string> $segs */
    public static function text(array $segs, string $tag): string
    {
        $text = '';
        foreach ($segs as $seg) {
            if (!is_string($seg)) {
                throw new InvalidStructure("<x-pdf::$tag> can only contain text and inline tags (<b>, <i>, <u>, <s>, <a>, <br>, <span>)");
            }
            $text .= $seg;
        }

        return $text;
    }
}
