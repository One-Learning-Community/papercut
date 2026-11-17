<?php

declare(strict_types=1);

namespace Quoin\Math\Font;

/**
 * @internal Engine detail, not public API. It may change in any release.
 */
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
