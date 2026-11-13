<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

final readonly class AssemblyPart
{
    public function __construct(
        public int $gid,
        public int $startConnector,
        public int $endConnector,
        public int $fullAdvance,
        public bool $extender,
    ) {
    }
}
