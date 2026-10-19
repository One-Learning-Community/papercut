<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Node, PageBreak, Spacer};
use Papercut\Style\ComputedStyle;

/**
 * Spacer and PageBreak. Forced breaks and flex growth are the parent's job;
 * a Spacer here only takes its fixed (or stretched) height.
 */
final class LeafLayouter implements Layouter
{
    public function layout(Node $node, ComputedStyle $style, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult
    {
        if ($node instanceof PageBreak) {
            return LayoutResult::complete(new Fragment($c->width, 0.0));
        }
        if (!$node instanceof Spacer) {
            throw new \LogicException(sprintf('LeafLayouter cannot lay out %s.', $node::class));
        }
        $h = $c->definiteHeight ?? $node->fixedSize()->points ?? 0.0;
        if ($c->height !== null && $h > $c->height + 1e-9) {
            if (!$c->fresh) {
                return LayoutResult::doesNotFit();
            }
            $h = max(0.0, $c->height);
        }
        return LayoutResult::complete(new Fragment($c->width, $h));
    }

    public function intrinsic(Node $node, ComputedStyle $style, LayoutEngine $engine): array
    {
        $size = $node instanceof Spacer ? ($node->fixedSize()->points ?? 0.0) : 0.0;
        return ['min' => $size, 'max' => $size];
    }
}
