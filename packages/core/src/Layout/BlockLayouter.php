<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Link, Node, PageBreak, Spacer, View};
use Papercut\Style\{Align, ComputedStyle, Direction, Justify};
use Papercut\Value\Length;

/**
 * Column-direction Views (and Links): children stacked top to bottom with
 * margins and gap, fragmented between children or inside a splitting child.
 *
 * A box with an explicit height is monolithic: it never splits, and its free
 * space goes to grow children, then to justify. An Auto-height box laid out
 * with a definiteHeight (flex stretch) stays splittable; when it completes in
 * a single slice it fills at least that height the same way.
 */
final class BlockLayouter implements Layouter
{
    private const float EPSILON = 1e-9;

    public function layout(Node $node, ComputedStyle $style, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult
    {
        $children = self::children($node);
        $count = count($children);
        $first = $token === null;
        $borderWidth = BoxGeometry::borderBoxWidth($style, $c->width);
        $contentWidth = BoxGeometry::contentWidth($style, $c->width);

        $fixed = $first ? self::explicitHeight($style, $c) : null;
        $stretch = $first && $fixed === null ? self::clampHeight($style, $c, $c->definiteHeight) : null;
        if ($fixed !== null && $c->height !== null && $fixed > $c->height + self::EPSILON) {
            return LayoutResult::doesNotFit();
        }

        $available = null;
        if ($fixed === null && $c->height !== null) {
            $available = $c->height - ($first ? BoxGeometry::topEdge($style) : 0.0) - BoxGeometry::bottomEdge($style);
        }

        /** @var list<array{node: Node, style: ComputedStyle, index: int, width: float, fresh: bool, fragment: Fragment, x: float, y: float, height: float, before: float}> $placed */
        $placed = [];
        $cursor = 0.0;
        $start = min(max(0, $token->index ?? 0), $count);
        $resumeToken = $token?->child;
        $monolithic = $fixed !== null;
        $breakAt = null;

        for ($i = $start; $i < $count; $i++) {
            $child = LayoutEngine::expand($children[$i]);
            $resuming = $resumeToken !== null && $i === $start;

            if ($child instanceof PageBreak) {
                if ($monolithic || $i === $count - 1) {
                    continue;
                }
                if ($placed === []) {
                    if ($c->fresh) {
                        continue;
                    }
                    return LayoutResult::doesNotFit();
                }
                $breakAt = new BreakToken(index: $i + 1);
                break;
            }

            $cs = $engine->styleOf($child, $style, $i);
            if ($cs->breakBefore && !$resuming && !$monolithic) {
                if ($placed !== []) {
                    $breakAt = new BreakToken(index: $i);
                    break;
                }
                if (!$c->fresh) {
                    return LayoutResult::doesNotFit();
                }
            }

            $gap = $placed === [] ? 0.0 : $style->gap;
            $mt = $resuming ? 0.0 : $cs->margin->top->points;
            $mb = $cs->margin->bottom->points;
            $ml = $cs->margin->left->points;
            $slot = max(0.0, $contentWidth - $cs->margin->horizontal());
            $align = $cs->alignSelf ?? $style->alignItems;
            $width = $slot;
            if ($align !== Align::Stretch && !$child instanceof Spacer) {
                $width = min($engine->intrinsic($child, $style, $i)['max'], $slot);
            }
            // Inside a monolithic box every child is laid out as if on a fresh page, so leading forced breaks are ignored.
            $fresh = $monolithic || ($c->fresh && $placed === []);
            $height = $available === null ? null : max(0.0, $available - $cursor - $gap - $mt);

            $res = $engine->layout($child, $style, new Constraints($width, $height, $fresh), $resuming ? $resumeToken : null, $i);
            if ($monolithic && $res->status === Status::Partial && $res->fragment !== null && $res->token !== null) {
                $res = LayoutResult::complete(self::continueInPlace($res->fragment, $res->token, $child, $width, $i, $style, $engine));
            }
            if ($res->status === Status::DoesNotFit || $res->fragment === null) {
                if ($monolithic) {
                    // unbounded and fresh: cannot happen for well-behaved layouters, but never drop content silently
                    $engine->context()->warn(sprintf('Content dropped inside fixed-height box: %s[%d]', $child->debugName(), $i));
                    continue;
                }
                if ($placed === []) {
                    return LayoutResult::doesNotFit();
                }
                // keep-with-next: walk back over a chain of keepWithNext children placed just before i.
                $k = count($placed);
                $next = $i;
                while ($k > 0 && $placed[$k - 1]['index'] === $next - 1 && $placed[$k - 1]['style']->keepWithNext) {
                    $k--;
                    $next = $placed[$k]['index'];
                }
                if ($k === 0 && !$c->fresh) {
                    return LayoutResult::doesNotFit();
                }
                if ($k > 0 && $k < count($placed)) {
                    $cursor = $placed[$k]['before'];
                    $breakAt = new BreakToken(index: $placed[$k]['index']);
                    $placed = array_slice($placed, 0, $k);
                    break;
                }
                $breakAt = new BreakToken(index: $i);
                break;
            }

            $f = $res->fragment;
            $before = $cursor;
            $y = $cursor + $gap + $mt;
            $placed[] = [
                'node' => $child,
                'style' => $cs,
                'index' => $i,
                'width' => $width,
                'fresh' => $fresh,
                'fragment' => $f,
                'x' => self::alignX($align, $ml, $slot, $f->width),
                'y' => $y,
                'height' => $f->height,
                'before' => $before,
            ];
            $cursor = $y + $f->height;

            if ($res->status === Status::Partial && $res->token !== null) {
                $breakAt = new BreakToken(index: $i, child: $res->token);
                break;
            }
            // Margin-bottom is truncated at the page end, never pushing the box past c.height.
            $cursor = $available === null ? $cursor + $mb : max($cursor, min($cursor + $mb, $available));
            if (!$monolithic && $i < $count - 1 && self::forcesBreakAfter($child, $cs, $engine)) {
                $breakAt = new BreakToken(index: $i + 1);
                break;
            }
        }

        if ($breakAt !== null && $style->keepTogether && !$c->fresh) {
            return LayoutResult::doesNotFit();
        }

        $contentHeight = $cursor;
        $target = $fixed ?? ($breakAt === null ? $stretch : null);
        if ($target !== null) {
            $inner = max(0.0, $target - BoxGeometry::topEdge($style) - BoxGeometry::bottomEdge($style));
            $contentHeight = $fixed !== null ? $inner : max($cursor, $inner);
            $placed = self::distribute($placed, $contentHeight - $cursor, $style, $engine);
        }

        $last = $breakAt === null;
        $f = new Fragment($borderWidth, BoxGeometry::outerHeight($style, $contentHeight, $first, $last));
        BoxGeometry::decorate($f, $style, $first, $last);
        $ox = $style->borderWidth->left->points + $style->padding->left->points;
        $oy = $first ? BoxGeometry::topEdge($style) : 0.0;
        foreach ($placed as $p) {
            $f->addChild($ox + $p['x'], $oy + $p['y'], $p['fragment']);
        }

        return $breakAt === null ? LayoutResult::complete($f) : LayoutResult::partial($f, $breakAt);
    }

    public function intrinsic(Node $node, ComputedStyle $style, LayoutEngine $engine): array
    {
        if ($style->width instanceof Length) {
            return ['min' => $style->width->points, 'max' => $style->width->points];
        }
        $min = 0.0;
        $max = 0.0;
        foreach (self::children($node) as $i => $child) {
            $child = LayoutEngine::expand($child);
            if ($child instanceof Spacer || $child instanceof PageBreak) {
                continue;
            }
            $margin = $engine->styleOf($child, $style, $i)->margin->horizontal();
            $size = $engine->intrinsic($child, $style, $i);
            $min = max($min, $size['min'] + $margin);
            $max = max($max, $size['max'] + $margin);
        }
        $edges = $style->padding->horizontal() + $style->borderWidth->horizontal();
        return ['min' => $min + $edges, 'max' => $max + $edges];
    }

    /** @return list<\Papercut\Node|\Papercut\Component> */
    private static function children(Node $node): array
    {
        if ($node instanceof View || $node instanceof Link) {
            return $node->children();
        }
        throw new \LogicException(sprintf('BlockLayouter cannot lay out %s.', $node::class));
    }

    /** Border-box height from an explicit Length, or a Percent of definiteHeight. */
    private static function explicitHeight(ComputedStyle $style, Constraints $c): ?float
    {
        return self::clampHeight($style, $c, BoxGeometry::resolve($style->height, $c->definiteHeight));
    }

    /**
     * True when laying out $node ends with a forced break: its own breakAfter,
     * or (recursively) a trailing PageBreak / breakAfter as its last child.
     */
    private static function forcesBreakAfter(Node $node, ComputedStyle $style, LayoutEngine $engine): bool
    {
        if ($style->breakAfter) {
            return true;
        }
        if (!($node instanceof View || $node instanceof Link) || $style->height instanceof Length) {
            return false;
        }
        $children = $node->children();
        if ($children === [] || $style->direction !== Direction::Column) {
            return false;
        }
        $i = count($children) - 1;
        $last = LayoutEngine::expand($children[$i]);
        if ($last instanceof PageBreak) {
            return true;
        }
        return self::forcesBreakAfter($last, $engine->styleOf($last, $style, $i), $engine);
    }

    /**
     * A monolithic box never splits: a child's forced-break slices are laid
     * out one after another (fresh, unbounded, no gap) into one wrapper.
     */
    private static function continueInPlace(Fragment $firstSlice, BreakToken $token, Node $child, float $width, int $index, ComputedStyle $style, LayoutEngine $engine): Fragment
    {
        $slices = [$firstSlice];
        for ($guard = 0; $guard < 10_000; $guard++) {
            $res = $engine->layout($child, $style, new Constraints($width, null, true), $token, $index);
            if ($res->fragment === null) {
                break;
            }
            $partial = $res->status === Status::Partial && $res->token !== null;
            if ($partial && $res->token == $token) {
                break; // no progress: the repeated slice is dropped
            }
            $slices[] = $res->fragment;
            if (!$partial) {
                break;
            }
            $token = $res->token;
        }
        $wrapper = new Fragment(max(array_map(static fn (Fragment $f): float => $f->width, $slices)), 0.0);
        $y = 0.0;
        foreach ($slices as $slice) {
            $wrapper->addChild(0.0, $y, $slice);
            $y += $slice->height;
        }
        $wrapper->height = $y;
        return $wrapper;
    }

    private static function clampHeight(ComputedStyle $style, Constraints $c, ?float $h): ?float
    {
        if ($h === null) {
            return null;
        }
        $max = BoxGeometry::resolve($style->maxHeight, $c->definiteHeight);
        if ($max !== null) {
            $h = min($h, $max);
        }
        $min = BoxGeometry::resolve($style->minHeight, $c->definiteHeight);
        if ($min !== null) {
            $h = max($h, $min);
        }
        return max(0.0, $h);
    }

    private static function alignX(Align $align, float $marginLeft, float $slot, float $width): float
    {
        return $marginLeft + match ($align) {
            Align::Center => ($slot - $width) / 2,
            Align::End => $slot - $width,
            Align::Start, Align::Stretch => 0.0,
        };
    }

    /**
     * Gives free space to grow children (re-laid out at their new definite
     * height), or else shifts children per justify.
     *
     * @param list<array{node: Node, style: ComputedStyle, index: int, width: float, fresh: bool, fragment: Fragment, x: float, y: float, height: float, before: float}> $placed
     * @return list<array{node: Node, style: ComputedStyle, index: int, width: float, fresh: bool, fragment: Fragment, x: float, y: float, height: float, before: float}>
     */
    private static function distribute(array $placed, float $free, ComputedStyle $style, LayoutEngine $engine): array
    {
        $n = count($placed);
        if ($free <= self::EPSILON || $n === 0) {
            return $placed;
        }
        $totalGrow = array_sum(array_map(static fn (array $p): float => $p['style']->grow, $placed));
        if ($totalGrow > 0.0) {
            $shift = 0.0;
            foreach ($placed as $k => $p) {
                $p['y'] += $shift;
                if ($p['style']->grow > 0.0) {
                    $extra = $free * $p['style']->grow / $totalGrow;
                    $target = $p['height'] + $extra;
                    $res = $engine->layout($p['node'], $style, new Constraints($p['width'], null, $p['fresh'], $target), null, $p['index']);
                    if ($res->status === Status::Complete && $res->fragment !== null) {
                        $p['fragment'] = $res->fragment;
                    }
                    $p['height'] = $target;
                    $shift += $extra;
                }
                $placed[$k] = $p;
            }
            return $placed;
        }

        [$lead, $between] = match ($style->justify) {
            Justify::Start => [0.0, 0.0],
            Justify::End => [$free, 0.0],
            Justify::Center => [$free / 2, 0.0],
            Justify::SpaceBetween => $n > 1 ? [0.0, $free / ($n - 1)] : [0.0, 0.0],
            Justify::SpaceAround => [$free / $n / 2, $free / $n],
            Justify::SpaceEvenly => [$free / ($n + 1), $free / ($n + 1)],
        };
        foreach ($placed as $k => $p) {
            $placed[$k]['y'] += $lead + $between * $k;
        }
        return $placed;
    }
}
