<?php

declare(strict_types=1);

namespace Papercut\Backend;

/**
 * A backend that can say whether it is able to write a tagged PDF. One that does not implement it is treated as unable: a tagged export warns and writes an untagged PDF.
 *
 * @internal Part of Papercut's PDF writer. Custom backends aren't supported, and it may change in any release.
 */
interface TaggingCapability
{
    /** @return list<string> what the backend lacks to write a tagged PDF; empty when it can */
    public function taggingMissing(): array;

    /**
     * The heading elements of the last tagged render in structure order. A heading that continues on another page
     * is one entry per page piece (same key): the structure holds one element for each, and the level check needs all of them.
     *
     * @return list<array{level: int, text: string, page: int, key: string}>
     */
    public function headings(): array;
}
