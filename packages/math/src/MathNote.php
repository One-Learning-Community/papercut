<?php

declare(strict_types=1);

namespace Papercut\Math;

/** What a reader could not render faithfully: the element, why, and where in the formula it was. */
final readonly class MathNote
{
    public function __construct(
        public NoteLevel $level,
        public string $element,
        public string $reason,
        public string $where = '',
    ) {
    }
}
