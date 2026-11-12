<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Component, Image, Link, Node, QrCode, Svg, Text};
use Papercut\Toc\HeadingText;
use Papercut\Paint\Link as LinkOp;
use Papercut\Style\ComputedStyle;

/**
 * A Link lays out like a column block and adds a link annotation over each fragment.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class LinkLayouter implements Layouter
{
    public function __construct(private readonly BlockLayouter $block)
    {
    }

    public function layout(Node $node, ComputedStyle $style, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult
    {
        if (!$node instanceof Link) {
            throw new \LogicException(sprintf('LinkLayouter cannot lay out %s.', $node::class));
        }
        $res = $this->block->layout($node, $style, $c, $token, $engine);
        $res->fragment?->addOp(new LinkOp(0.0, 0.0, $res->fragment->width, $res->fragment->height, $node->uri(), self::say($node)));
        return $res;
    }

    /**
     * What a node says: its text, and the alternative text of its images.
     */
    private static function say(Node|Component $node): string
    {
        if ($node instanceof Text) {
            return HeadingText::plain($node);
        }
        if ($node instanceof Image) {
            return $node->altText() ?? '';
        }
        if ($node instanceof Svg) {
            return $node->description() ?? '';
        }
        if ($node instanceof QrCode) {
            return $node->label();
        }
        if ($node instanceof Component || !method_exists($node, 'children')) {
            return '';
        }
        $parts = [];
        foreach ($node->children() as $child) {
            if ($child instanceof Node) {
                $parts[] = self::say($child);
            }
        }
        return implode(' ', $parts);
    }

    public function intrinsic(Node $node, ComputedStyle $style, LayoutEngine $engine): array
    {
        return $this->block->intrinsic($node, $style, $engine);
    }
}
