<?php

declare(strict_types=1);

namespace Papercut\Backend;

/**
 * A backend that can say whether it is able to write a tagged PDF. One that does not implement it is assumed to be.
 *
 * @internal Part of Papercut's PDF writer. Custom backends aren't supported, and it may change in any release.
 */
interface TaggingCapability
{
    /** @return list<string> what the backend lacks to write a tagged PDF; empty when it can */
    public function taggingMissing(): array;

    /**
     * The headings of the last tagged render in structure order, each once, with the page of its first part.
     *
     * @return list<array{level: int, text: string, page: int, key: string}>
     */
    public function headings(): array;
}
