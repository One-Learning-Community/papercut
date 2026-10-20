<?php

declare(strict_types=1);

namespace Papercut\Html;

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Exception\ImageUnreadable;
use Papercut\Image;
use Papercut\Paint\Link;

/** Decides what goes in an img src: the caller's URL when it is safe, otherwise the image itself as a data URI. */
final class ImageSource
{
    /**
     * @param (\Closure(Image): ?string)|null $imageUrl
     * @param (\Closure(): void)|null $onRefused called when the callback's URL is unsafe and the image is embedded instead
     */
    public static function uri(Image $image, ?\Closure $imageUrl, ?\Closure $onRefused = null): string
    {
        if ($imageUrl !== null) {
            $url = $imageUrl($image);
            if ($url !== null && self::safe($url)) {
                return $url;
            }
            if ($url !== null && $onRefused !== null) {
                $onRefused();
            }
        }

        ['kind' => $kind, 'value' => $value] = $image->source();
        if ($kind === 'file') {
            $bytes = is_file($value) && is_readable($value) ? file_get_contents($value) : false;
            if ($bytes === false) {
                throw new ImageUnreadable(sprintf('Cannot read image "%s": file not found or not readable', $value));
            }
            $mime = self::mime($bytes) ?? throw new ImageUnreadable(sprintf('Cannot read image "%s": Unsupported image format (expected PNG or JPEG).', $value));
        } else {
            $bytes = $value;
            $mime = self::mime($bytes) ?? throw new ImageUnreadable('Cannot read image bytes: Unsupported image format (expected PNG or JPEG).');
        }

        if (@getimagesizefromstring($bytes) === false) {
            // Ask the PDF backend's own parser, so both formats refuse a corrupt image with the same exception and text.
            $backend = new PhpPdfBackend(false);
            $kind === 'file' ? $backend->loadImageFile($value) : $backend->loadImageBytes($bytes);
            throw new ImageUnreadable($kind === 'file' ? sprintf('Cannot read image "%s": Invalid image data', $value) : 'Cannot read image bytes: Invalid image data');
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private static function mime(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            default => null,
        };
    }

    /** An allowed link scheme, a data:image/ URI, or a relative path. Control characters and edge whitespace are refused: browsers drop them inside schemes. */
    private static function safe(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url) === 1 || trim($url) !== $url) {
            return false;
        }

        return Link::allowed($url)
            || preg_match('~^data:image/~i', $url) === 1
            || preg_match('~^(?![a-z][a-z0-9+.-]*:|[/\\\\]{2}|\\\\)~i', $url) === 1;
    }
}
