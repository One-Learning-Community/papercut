<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\{ImageUnreadable, InvalidValue};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Style\ImageFit;

final class Image extends PdfComponent
{
    protected function ownAttributes(): array
    {
        return ['src', 'data', 'fit'];
    }

    protected function build(array $data): object
    {
        $own = $this->attrs($data)['own'];
        $src = $own['src'] ?? null;
        $bytes = $own['data'] ?? null;
        if (($src === null) === ($bytes === null)) {
            throw new InvalidStructure('<x-pdf::image> requires exactly one of src or data');
        }
        if (($src !== null && !is_string($src)) || ($bytes !== null && !is_string($bytes))) {
            throw new InvalidStructure('<x-pdf::image> src must be a string path');
        }

        if ($src !== null) {
            $node = \Papercut\Image::file(self::resolvePath($src));
        } else {
            $node = \Papercut\Image::bytes((string) $bytes);
        }

        if (isset($own['fit'])) {
            $fit = ImageFit::tryFrom((string) $own['fit'])
                ?? throw InvalidValue::for('fit', (string) $own['fit'])->because('expected contain, cover or fill')->withContext('fit')->within('on <x-pdf::image>');
            $node = $node->fit($fit);
        }

        return $this->styled($node, $data);
    }

    /**
     * Relative paths must stay inside the image base. Absolute paths (including stream wrappers and
     * Windows drive paths) are trusted: never feed untrusted input to src.
     */
    private static function resolvePath(string $src): string
    {
        $absolute = $src !== '' && ($src[0] === '/' || $src[0] === '\\'
            || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $src) === 1
            || preg_match('#^[A-Za-z]:[\\\\/]#', $src) === 1);
        if ($absolute) {
            $path = $src;
        } else {
            $base = config('papercut.image_path');
            $base = is_string($base) && $base !== '' ? $base : public_path();
            $path = rtrim($base, '/') . '/' . $src;
            $realBase = realpath($base);
            $real = realpath($path);
            if ($realBase !== false && $real !== false
                && !str_starts_with($real, rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                throw new ImageUnreadable("Image path escapes the image directory: $src");
            }
        }
        if (!is_file($path)) {
            throw new ImageUnreadable("Image not found: $path");
        }

        return $path;
    }
}
