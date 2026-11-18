<?php

declare(strict_types=1);

namespace Quoin\Math;

use Quoin\Math\Font\Font;
use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Node\Node;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\PngRenderer;
use Quoin\Math\Render\RunFormat;
use Quoin\Math\Render\SvgRenderer;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Render\Xml;
use Quoin\Math\Render\TextWriter;
use Quoin\Math\Tex\TexReader;

/**
 * A formula with every output one call away: `MathRender::fromTex('\pi r^2')->svg()`.
 *
 * It starts in the style the source asked for (`$$…$$`, `\[…\]`, `display="block"`), otherwise inline, and
 * {@see display()} or {@see inline()} change that. The defaults suit running text on the web: inline, 16 px,
 * currentColor. They differ from {@see Math::layout()}, which defaults to display style at 12 pt.
 * Reading never throws on bad input; what could not be drawn is in {@see notes()}.
 */
final readonly class MathRender
{
    private function __construct(private Node $node, private bool $display, private ?Font $font = null)
    {
    }

    /** A formula read from TeX (see {@see TexReader::read()}). */
    public static function fromTex(string $tex): self
    {
        $result = TexReader::read($tex);

        return new self($result->node, $result->display);
    }

    /** A formula read from MathML, a string or a `math` element of a parsed document (see {@see MathmlReader::read()}). */
    public static function fromMathml(string|\Dom\Element $input): self
    {
        $result = MathmlReader::read($input);

        return new self($result->node, $result->display);
    }

    /** A formula built with {@see Math} or read elsewhere. It starts inline. */
    public static function of(Node $node): self
    {
        return new self($node, false);
    }

    /** Display style, for a formula on its own line. */
    public function display(): self
    {
        return new self($this->node, true, $this->font);
    }

    /** Text style, for a formula inside a line. */
    public function inline(): self
    {
        return new self($this->node, false, $this->font);
    }

    /** The font SVG and PNG are drawn in; STIX Two Math by default. */
    public function font(Font $font): self
    {
        return new self($this->node, $this->display, $font);
    }

    public function isDisplay(): bool
    {
        return $this->display;
    }

    public function node(): Node
    {
        return $this->node;
    }

    /** @return list<MathNote> what the reader could not draw faithfully */
    public function notes(): array
    {
        return Math::notes($this->node);
    }

    /** MathML Core, with display="block" in display style. */
    public function mathml(): string
    {
        return MathmlWriter::write($this->node, $this->display);
    }

    /**
     * Word's Office Math: an m:oMath inline, or an m:oMathPara aligned left, center or right in display style.
     *
     * @throws \InvalidArgumentException for an alignment other than left, center or right, in either style
     */
    public function omml(string $align = 'center', ?RunFormat $format = null): string
    {
        OmmlWriter::checkAlignment($align);

        return $this->display ? OmmlWriter::oMathPara($this->node, $align, $format) : OmmlWriter::oMath($this->node, $format);
    }

    public function tex(): string
    {
        return TexWriter::write($this->node);
    }

    /** Linear Unicode text, for alt text and search. */
    public function text(): string
    {
        return TextWriter::write($this->node);
    }

    /**
     * An SVG of the formula, $size CSS pixels to the em, with role="img" and an aria-label of {@see text()} (or $label).
     * An empty $label hides it from screen readers instead (aria-hidden), for a caller that supplies MathML beside it.
     * Inline, it carries the vertical-align that puts its baseline on the line's.
     *
     * @throws \InvalidArgumentException when $size is not above zero and finite, or $pad is negative or not finite
     */
    public function svg(float $size = 16.0, string $color = 'currentColor', float $pad = 0.0, ?string $label = null): string
    {
        self::checkSize($size, $pad);
        $font = $this->layoutFont();
        $box = Math::layout($this->node, $size, $this->display, $font);
        $svg = (new SvgRenderer($font, $color))->render($box, $pad);
        if (! str_starts_with($svg, '<svg ')) {
            throw new \LogicException('The SVG renderer no longer starts its output with the root element');
        }
        $label ??= $this->text();
        $attrs = $label === ''
            ? ' aria-hidden="true"'
            : ' role="img" aria-label="' . Xml::text($label) . '"';
        if (! $this->display) {
            $attrs .= ' style="vertical-align:' . Xml::number(-($box->descent + $pad)) . 'px"';
        }

        return '<svg' . $attrs . substr($svg, 4);
    }

    /**
     * A PNG of the formula, $size CSS pixels to the em and $scale image pixels to each CSS pixel, on a transparent background.
     * $color is an ImageMagick color (#rrggbb, rgb(...) or a name).
     *
     * @throws Exception\MissingExtension when Imagick is not loaded
     * @throws \InvalidArgumentException for a color ImageMagick does not know, a $size or $scale not above zero and finite,
     *                                   a $pad negative or not finite, or an image over PngRenderer::MAX_PIXELS
     */
    public function png(float $size = 16.0, float $scale = 2.0, string $color = '#000000', float $pad = 0.0): MathPng
    {
        self::checkSize($size, $pad);
        $font = $this->layoutFont();
        $box = Math::layout($this->node, $size, $this->display, $font);
        $bytes = (new PngRenderer($font, $color))->render($box, $pad, $scale);
        [$w, $h] = PngRenderer::pixelSize($box, $pad, $scale);
        $height = $h / $scale;
        $drop = round($height - PngRenderer::baselineOffset($box, $pad, $scale) / $scale, 3);

        return new MathPng($bytes, $w / $scale, $height, $this->display ? null : ($drop == 0.0 ? 0.0 : -$drop), $this->text());
    }

    private static function checkSize(float $size, float $pad): void
    {
        if (! is_finite($size) || $size <= 0.0) {
            throw new \InvalidArgumentException('Math size must be above zero and finite, got ' . var_export($size, true));
        }
        if (! is_finite($pad) || $pad < 0.0) {
            throw new \InvalidArgumentException('Math pad must be zero or more and finite, got ' . var_export($pad, true));
        }
    }

    private function layoutFont(): Font
    {
        return $this->font ?? Math::stixTwoMath();
    }
}
