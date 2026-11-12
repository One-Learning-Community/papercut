<?php

declare(strict_types=1);

namespace Papercut\Paint;

/** SPIKE: ops that belong to one structure element. @internal */
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
