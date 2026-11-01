<?php

declare(strict_types=1);

namespace Papercut\Site;

/** GD helpers for the gallery images: crop away blank page, and set pages side by side. */
final class Raster
{
    /** Crops to the bounding box of everything that isn't white, then adds $pad white pixels on every side. */
    public static function trim(\GdImage $img, int $pad): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        [$left, $top, $right, $bottom] = [$w, $h, -1, -1];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                if ((($rgb >> 16) & 0xFF) < 250 || (($rgb >> 8) & 0xFF) < 250 || ($rgb & 0xFF) < 250) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }
        if ($right < 0) {
            return $img; // blank: nothing to crop to
        }
        $cw = $right - $left + 1 + 2 * $pad;
        $ch = $bottom - $top + 1 + 2 * $pad;
        $out = self::white($cw, $ch);
        imagecopy($out, $img, $pad, $pad, $left, $top, $right - $left + 1, $bottom - $top + 1);

        return $out;
    }

    /**
     * Places pages left to right, top-aligned, $gap pixels apart, each framed by a $border-pixel #e4e4e7 line.
     *
     * @param list<\GdImage> $pages
     */
    public static function sideBySide(array $pages, int $gap, int $border): \GdImage
    {
        $width = 0;
        $height = 0;
        foreach ($pages as $i => $page) {
            $width += imagesx($page) + 2 * $border + ($i > 0 ? $gap : 0);
            $height = max($height, imagesy($page) + 2 * $border);
        }
        $out = self::white($width, $height);
        $line = imagecolorallocate($out, 0xE4, 0xE4, 0xE7);
        $x = 0;
        foreach ($pages as $page) {
            $pw = imagesx($page) + 2 * $border;
            $ph = imagesy($page) + 2 * $border;
            if ($line !== false) {
                imagefilledrectangle($out, $x, 0, $x + $pw - 1, $ph - 1, $line);
            }
            imagecopy($out, $page, $x + $border, $border, 0, 0, imagesx($page), imagesy($page));
            $x += $pw + $gap;
        }

        return $out;
    }

    /** Centres the image horizontally on a white canvas at least $width wide, so images from same-width pages share one scale. */
    public static function centerOn(\GdImage $img, int $width): \GdImage
    {
        if (imagesx($img) >= $width) {
            return $img;
        }
        $out = self::white($width, imagesy($img));
        imagecopy($out, $img, intdiv($width - imagesx($img), 2), 0, 0, 0, imagesx($img), imagesy($img));

        return $out;
    }

    private static function white(int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor(max(1, $w), max(1, $h));
        $white = imagecolorallocate($img, 255, 255, 255);
        if ($white !== false) {
            imagefill($img, 0, 0, $white);
        }

        return $img;
    }
}
