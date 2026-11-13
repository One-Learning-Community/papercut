<?php

declare(strict_types=1);

namespace Papercut\Backend;

use Papercut\Export\PdfA;
use Papercut\Paint\DocumentMeta;
use Papercut\Paint\PageDisplayList;

/** @internal Part of Papercut's PDF writer. Custom backends aren't supported, and it may change in any release. */
interface Backend
{
    /** @throws \Papercut\Exception\FontNotFound */
    public function loadFont(string $path): FontHandle;

    /** @throws \Papercut\Exception\ImageUnreadable */
    public function loadImageFile(string $path): ImageHandle;

    /** @throws \Papercut\Exception\ImageUnreadable */
    public function loadImageBytes(string $bytes): ImageHandle;

    /**
     * @param list<PageDisplayList> $pages
     * @param bool $tagged write a structure tree from the pages' Tagged groups, with PDF/UA-1 identification
     * @param PdfA|null $pdfA write PDF/A at that level (the caller has already turned $tagged on for an "a" level)
     * @throws \InvalidArgumentException when $pages is empty
     * @throws \LogicException for a font/image handle from another backend or an unknown op type
     */
    public function render(DocumentMeta $meta, array $pages, bool $tagged = false, ?PdfA $pdfA = null): string;
}
