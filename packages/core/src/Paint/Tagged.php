<?php

declare(strict_types=1);

namespace Papercut\Paint;

/**
 * Ops that belong to one structure element.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final readonly class Tagged implements Op
{
    /** @param list<Op> $ops */
    public function __construct(
        public Tag $tag,
        /** @var list<Op> */
        public array $ops,
    ) {
    }
}
