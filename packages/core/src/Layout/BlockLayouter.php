<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Link, Node, PageBreak, Spacer, View};
use Papercut\Style\{Align, ComputedStyle, Justify};
use Papercut\Value\Length;

/**
 * Column-direction Views (and Links): children stacked top to bottom with
 * margins and gap, fragmented between children or inside a splitting child.
 *
 * A box with a definite height (explicit height, or a stretched
 * definiteHeight) is monolithic: it never splits, and its free space goes to
 * grow children, then to justify.
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

        $fixed = $first ? self::definiteHeight($style, $c) : null;
        if ($fixed !== null && $c->height !== null && $fixed > $c->height + self::EPSILON) {
            return LayoutResult::doesNotFit();
        }

        $available = null;
        if ($fixed === null && $c->height !== null) {
            $available = $c->height - ($first ? BoxGeometry::topEdge($style) : 0.0) - BoxGeometry::bottomEdge($style);
        }

        /** @var list<array{node: Node, style: ComputedStyle, index: int, width: float, fresh: bool, fragment: Fragment, x: float, y: float, height: float}> $placed */
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
            $mt = $cs->margin->top->points;
            $mb = $cs->margin->bottom->points;
            $ml = $cs->margin->left->points;
            $slot = max(0.0, $contentWidth - $cs->margin->horizontal());
            $align = $cs->alignSelf ?? $style->alignItems;
            $width = $slot;
            if ($align !== Align::Stretch && !$child instanceof Spacer) {
                $width = min($engine->intrinsic($child, $style, $i)['max'], $slot);
            }
            $fresh = $c->fresh && $placed === [];
            $height = $available === null ? null : max(0.0, $available - $cursor - $gap - $mt);

            $res = $engine->layout($child, $style, new Constraints($width, $height, $fresh), $resuming ? $resumeToken : null, $i);
            if ($res->status === Status::DoesNotFit || $res->fragment === null) {
                if ($placed === []) {
                    return LayoutResult::doesNotFit();
                }
                $prev = $placed[count($placed) - 1];
                if ($prev['index'] === $i - 1 && $prev['style']->keepWithNext) {
                    if (count($placed) > 1) {
                        array_pop($placed);
                        $breakAt = new BreakToken(index: $i - 1);
                        break;
                    }
                    if (!$c->fresh) {
                        return LayoutResult::doesNotFit();
                    }
                }
                $breakAt = new BreakToken(index: $i);
                break;
            }

            $f = $res->fragment;
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
            ];
            $cursor = $y + $f->height + $mb;

            if ($res->status === Status::Partial && $res->token !== null) {
                $breakAt = new BreakToken(index: $i, child: $res->token);
                break;
            }
            if ($cs->breakAfter && !$monolithic && $i < $count - 1) {
                $breakAt = new BreakToken(index: $i + 1);
                break;
            }
        }

        if ($breakAt !== null && $style->keepTogether && !$c->fresh) {
            return LayoutResult::doesNotFit();
        }

        $contentHeight = $cursor;
        if ($fixed !== null) {
            $contentHeight = max(0.0, $fixed - BoxGeometry::topEdge($style) - BoxGeometry::bottomEdge($style));
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

    /** Border-box height the box must have: explicit height, else a stretched definiteHeight. */
    private static function definiteHeight(ComputedStyle $style, Constraints $c): ?float
    {
        $h = BoxGeometry::resolve($style->height, $c->definiteHeight) ?? $c->definiteHeight;
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
     * @param list<array{node: Node, style: ComputedStyle, index: int, width: float, fresh: bool, fragment: Fragment, x: float, y: float, height: float}> $placed
     * @return list<array{node: Node, style: ComputedStyle, index: int, width: float, fresh: bool, fragment: Fragment, x: float, y: float, height: float}>
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
                    if ($res->fragment !== null) {
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
