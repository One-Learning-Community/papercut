<?php

declare(strict_types=1);

namespace Papercut\Docx\Package;

/** What DocxPatcher writes into php-docx's output. */
final readonly class PatchSpec
{
    public function __construct(
        public string $fontFamily,
        public int $fontSizeHalfPoints,
        public ?string $lang,
        /** Every ZIP entry's time, read as UTC. */
        public \DateTimeInterface $time,
        /** Swap the left and right margins on even pages (`w:mirrorMargins`). */
        public bool $mirrorMargins = false,
        /** The lists of the export; DocxPatcher writes word/numbering.xml and each list paragraph's `w:numPr`. */
        public ?Numbering $numbering = null,
    ) {
    }
}
