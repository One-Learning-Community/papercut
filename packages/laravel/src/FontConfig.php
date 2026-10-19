<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Text\FontStyle;

/** Reads the `papercut.fonts` config into flat, path-resolved face entries. */
final class FontConfig
{
    /** @return list<array{family: string, weight: int, style: FontStyle, path: string}> */
    public static function faces(): array
    {
        $out = [];
        foreach ((array) config('papercut.fonts', []) as $family => $weights) {
            foreach ((array) $weights as $weight => $styles) {
                foreach ((array) $styles as $style => $path) {
                    $out[] = [
                        'family' => (string) $family,
                        'weight' => (int) $weight,
                        'style' => $style === 'italic' ? FontStyle::Italic : FontStyle::Normal,
                        'path' => self::resolve((string) $path),
                    ];
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function fallbacks(): array
    {
        return array_values(array_map(strval(...), (array) config('papercut.fallback_fonts', [])));
    }

    private static function resolve(string $path): string
    {
        $absolute = str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;

        return $absolute ? $path : base_path($path);
    }
}
