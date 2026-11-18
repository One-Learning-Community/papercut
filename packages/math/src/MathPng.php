<?php

declare(strict_types=1);

namespace Quoin\Math;

/**
 * A formula drawn as PNG by {@see MathRender::png()}, with what an `<img>` needs to show it at its size and on the baseline.
 * Width, height and vertical-align are CSS pixels: the image has `scale` pixels for each.
 */
final readonly class MathPng
{
    /**
     * @param float $verticalAlign CSS vertical-align in pixels, negative below the baseline
     * @param string $alt the formula as text, the default alt of {@see img()}
     */
    public function __construct(
        public string $bytes,
        public float $width,
        public float $height,
        public float $verticalAlign,
        private string $alt,
    ) {
    }

    public function dataUri(): string
    {
        return 'data:image/png;base64,' . base64_encode($this->bytes);
    }

    /** An `<img>` of this image at $src (a URL, `cid:` reference or {@see dataUri()}), sized and set on the baseline. */
    public function img(string $src, ?string $alt = null): string
    {
        return sprintf(
            '<img src="%s" width="%s" height="%s" alt="%s" style="vertical-align:%spx">',
            self::attr($src),
            self::number($this->width),
            self::number($this->height),
            self::attr($alt ?? $this->alt),
            self::number($this->verticalAlign),
        );
    }

    private static function attr(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function number(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
