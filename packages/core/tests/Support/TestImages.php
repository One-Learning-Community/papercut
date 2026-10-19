<?php

declare(strict_types=1);

namespace Papercut\Tests\Support;

final class TestImages
{
    /**
     * @param int<1, max> $w
     * @param int<1, max> $h
     */
    public static function png(int $w = 40, int $h = 20, bool $alpha = false): string
    {
        $im = imagecreatetruecolor($w, $h) ?: throw new \RuntimeException('gd failure');
        if ($alpha) {
            imagesavealpha($im, true);
            imagefill($im, 0, 0, (int) imagecolorallocatealpha($im, 255, 0, 0, 64));
        } else {
            imagefill($im, 0, 0, (int) imagecolorallocate($im, 30, 90, 200));
        }
        ob_start();
        imagepng($im);
        return (string) ob_get_clean();
    }
}
