<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Exception\ImageUnreadable;

/** Where an image or SVG src points, for the <image> and <svg> tags. */
final class ImagePath
{
    private function __construct()
    {
    }

    /**
     * Relative paths must stay inside the image base (textual containment: symlinks inside the base, e.g. public/storage, are followed). Absolute paths (including stream wrappers and
     * Windows drive paths) are trusted: never feed untrusted input to src.
     */
    public static function resolve(string $src): string
    {
        if (str_contains($src, "\0")) {
            throw new ImageUnreadable('Image path contains a NUL byte');
        }
        $absolute = $src !== '' && ($src[0] === '/' || $src[0] === '\\'
            || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $src) === 1
            || preg_match('#^[A-Za-z]:[\\\\/]#', $src) === 1);
        if ($absolute) {
            $path = $src;
        } else {
            $base = config('papercut.image_path');
            $base = is_string($base) && $base !== '' ? $base : public_path();
            $stack = [];
            foreach (preg_split('#[/\\\\]#', $src) ?: [] as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }
                if ($segment === '..') {
                    if ($stack === []) {
                        throw new ImageUnreadable("Image path escapes the image directory: $src");
                    }
                    array_pop($stack);
                } else {
                    $stack[] = $segment;
                }
            }
            $path = rtrim($base, '/') . ($stack === [] ? '' : '/' . implode('/', $stack));
        }
        if (!is_file($path)) {
            throw new ImageUnreadable("Image not found: $path");
        }

        return $path;
    }
}
