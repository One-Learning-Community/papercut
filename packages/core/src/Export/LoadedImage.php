<?php

declare(strict_types=1);

namespace Papercut\Export;

use Papercut\Backend\Backend;
use Papercut\Exception\ImageUnreadable;
use Papercut\Image;

/** An image the backend accepted, with the bytes and facts HTML needs. */
final readonly class LoadedImage
{
    private function __construct(
        public string $bytes,
        public string $mime,
        public int $widthPx,
        public int $heightPx,
    ) {
    }

    /** @throws ImageUnreadable with the backend's message (the PDF wording) */
    public static function load(Image $image, Backend $backend): self
    {
        ['kind' => $kind, 'value' => $value] = $image->source();
        if ($kind === 'file') {
            $bytes = is_file($value) && is_readable($value) ? file_get_contents($value) : false;
            if ($bytes === false) {
                throw new ImageUnreadable(sprintf('Cannot read image "%s": file not found or not readable', $value));
            }
            // Read first for the exact PDF wording on missing files; the backend then validates by path like PDF does.
            $handle = $backend->loadImageFile($value);
            $unsupported = sprintf('Cannot read image "%s": Unsupported image format (expected PNG or JPEG).', $value);
        } else {
            $bytes = $value;
            $handle = $backend->loadImageBytes($bytes);
            $unsupported = 'Cannot read image bytes: Unsupported image format (expected PNG or JPEG).';
        }

        // The unsupported arm only fires for non-default backends: PdfImage already rejects other formats.
        $mime = match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            default => throw new ImageUnreadable($unsupported),
        };

        return new self($bytes, $mime, $handle->widthPx(), $handle->heightPx());
    }
}
