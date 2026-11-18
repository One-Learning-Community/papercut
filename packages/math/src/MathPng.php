<?php

declare(strict_types=1);

namespace Quoin\Math;

use Quoin\Math\Render\Xml;

/**
 * A formula drawn as PNG by {@see MathRender::png()}, with what an `<img>` needs to show it at its size and on the baseline.
 * Width, height and vertical-align are CSS pixels: the image has `scale` pixels for each.
 */
final readonly class MathPng
{
    /**
     * @param ?float $verticalAlign CSS vertical-align in pixels, negative below the baseline; null for a display formula, which sits on its own line
     * @param string $alt the formula as text, the default alt of {@see img()}
     */
    public function __construct(
        public string $bytes,
        public float $width,
        public float $height,
        public ?float $verticalAlign,
        public string $alt,
    ) {
    }

    public function dataUri(): string
    {
        return 'data:image/png;base64,' . base64_encode($this->bytes);
    }

    /** An `<img>` of this image at $src (a URL, `cid:` reference or {@see dataUri()}), sized and, inline, set on the baseline. */
    public function img(string $src, ?string $alt = null): string
    {
        return sprintf(
            '<img src="%s" width="%s" height="%s" alt="%s"%s>',
            self::attr($src),
            Xml::number($this->width),
            Xml::number($this->height),
            self::attr($alt ?? $this->alt),
            $this->verticalAlign === null ? '' : ' style="vertical-align:' . Xml::number($this->verticalAlign) . 'px"',
        );
    }

    private static function attr(string $s): string
    {
        return htmlspecialchars(Xml::strip($s), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
