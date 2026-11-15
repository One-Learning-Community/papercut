<?php

declare(strict_types=1);

namespace Papercut\Math;

/** How much a note's element cost: drawn some other way, or not drawn at all. */
enum NoteLevel
{
    case Approximated;
    case Dropped;
}
