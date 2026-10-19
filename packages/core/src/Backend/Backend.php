<?php

declare(strict_types=1);

namespace Papercut\Backend;

use Papercut\Paint\DocumentMeta;
use Papercut\Paint\PageDisplayList;

interface Backend
{
    /** @throws \Papercut\Exception\FontNotFound */
    public function loadFont(string $path): FontHandle;

    /** @throws \Papercut\Exception\ImageUnreadable */
    public function loadImageFile(string $path): ImageHandle;

    /** @throws \Papercut\Exception\ImageUnreadable */
    public function loadImageBytes(string $bytes): ImageHandle;

    /** @param list<PageDisplayList> $pages */
    public function render(DocumentMeta $meta, array $pages): string;
}
