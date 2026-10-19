<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\Paint\{Line, Rect};
use Papercut\Style\{BorderStyle, ComputedStyle};
use Papercut\Value\{Auto, Length, Percent};

/**
 * Box-model helpers. Widths and heights are border-box sizes. Margins are
 * applied by the parent when it positions a child, so $available here is the
 * space left after the parent removed the child's horizontal margins.
 */
final class BoxGeometry
{
    private function __construct()
    {
    }

    /** Border-box width: width resolved against $available, clamped to min/max. */
    public static function borderBoxWidth(ComputedStyle $s, float $available): float
    {
        $w = self::resolve($s->width, $available) ?? $available;
        $max = self::resolve($s->maxWidth, $available);
        if ($max !== null) {
            $w = min($w, $max);
        }
        $min = self::resolve($s->minWidth, $available);
        if ($min !== null) {
            $w = max($w, $min);
        }
        return max(0.0, $w);
    }

    public static function contentWidth(ComputedStyle $s, float $available): float
    {
        return max(0.0, self::borderBoxWidth($s, $available) - $s->padding->horizontal() - $s->borderWidth->horizontal());
    }

    /** Top padding + border (only on the first fragment). */
    public static function topEdge(ComputedStyle $s): float
    {
        return $s->padding->top->points + $s->borderWidth->top->points;
    }

    /** Bottom padding + border (only on the last fragment). */
    public static function bottomEdge(ComputedStyle $s): float
    {
        return $s->padding->bottom->points + $s->borderWidth->bottom->points;
    }

    public static function outerHeight(ComputedStyle $s, float $contentHeight, bool $first, bool $last): float
    {
        return ($first ? self::topEdge($s) : 0.0) + $contentHeight + ($last ? self::bottomEdge($s) : 0.0);
    }

    /** Adds background, borders and opacity for one slice of a box. */
    public static function decorate(Fragment $f, ComputedStyle $s, bool $first, bool $last): void
    {
        $w = $f->width;
        $h = $f->height;
        if ($s->background !== null) {
            $f->addOp(new Rect(0.0, 0.0, $w, $h, fill: $s->background, radius: $s->radius));
        }

        $b = $s->borderWidth;
        $top = $b->top->points;
        $right = $b->right->points;
        $bottom = $b->bottom->points;
        $left = $b->left->points;
        $uniform = $top > 0.0 && $top === $right && $top === $bottom && $top === $left;
        if ($uniform && $first && $last && $s->borderStyle === BorderStyle::Solid) {
            $f->addOp(new Rect(
                $top / 2,
                $top / 2,
                max(0.0, $w - $top),
                max(0.0, $h - $top),
                stroke: $s->borderColor,
                strokeWidth: $top,
                radius: max(0.0, $s->radius - $top / 2),
            ));
        } else {
            $dashed = $s->borderStyle === BorderStyle::Dashed;
            $color = $s->borderColor;
            if ($first && $top > 0.0) {
                $f->addOp(new Line(0.0, $top / 2, $w, $top / 2, $color, $top, $dashed));
            }
            if ($right > 0.0) {
                $f->addOp(new Line($w - $right / 2, 0.0, $w - $right / 2, $h, $color, $right, $dashed));
            }
            if ($last && $bottom > 0.0) {
                $f->addOp(new Line(0.0, $h - $bottom / 2, $w, $h - $bottom / 2, $color, $bottom, $dashed));
            }
            if ($left > 0.0) {
                $f->addOp(new Line($left / 2, 0.0, $left / 2, $h, $color, $left, $dashed));
            }
        }

        if ($s->opacity < 1.0) {
            $f->opacity = $s->opacity;
        }
    }

    /** Resolves a Length/Percent against $basis; Auto, null or a Percent without a basis → null. */
    public static function resolve(Length|Percent|Auto|null $v, ?float $basis): ?float
    {
        return match (true) {
            $v instanceof Length => $v->points,
            $v instanceof Percent => $basis === null ? null : $v->resolve($basis),
            default => null,
        };
    }
}
