<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\Exception\InvalidStructure;
use Papercut\Node;
use Papercut\Paint\Line;
use Papercut\Style\ComputedStyle;
use Papercut\Tree\{ColumnFlowPlacement, ColumnFlows};
use Papercut\Value\{Auto, ColumnSpec};
use Papercut\View;

/**
 * A ColumnFlow: its children fill column one, then the next, and carry on in column one of the
 * next page. The flow itself has no box; its children are laid out by BlockLayouter in each
 * column, so a break token is the content's own token and every page restarts at column one.
 */
final class ColumnFlowLayouter implements Layouter
{
    public function layout(Node $node, ComputedStyle $style, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult
    {
        $spec = self::spec($node);
        $ctx = $engine->context();
        $placement = ColumnFlows::placement($ctx->ancestors(), $ctx->region);
        if ($placement->isBlocked()) {
            if (!$spec->fromMarkup) {
                throw ColumnFlows::misplaced($placement, $ctx->path());
            }
            $ctx->approximated('columns ' . self::where($placement));
            return $engine->blockLayouter()->layout($node, $style, $c, $token, $engine);
        }
        if (self::hasBox($style)) {
            throw new InvalidStructure(sprintf(
                'A ColumnFlow can\'t have a border, background, padding or size (at "%s"). Put it inside a View with that style.',
                $ctx->path(),
            ));
        }

        return $this->page($node, $style, $spec, $c, $token, $engine);
    }

    public function intrinsic(Node $node, ComputedStyle $style, LayoutEngine $engine): array
    {
        $spec = self::spec($node);
        $one = $engine->blockLayouter()->intrinsic($node, $style, $engine);
        $ctx = $engine->context();
        if ($spec->fromMarkup && ColumnFlows::placement($ctx->ancestors(), $ctx->region)->isBlocked()) {
            return $one;
        }
        $gaps = array_sum($spec->gaps);

        return ['min' => $spec->count * $one['min'] + $gaps, 'max' => $spec->count * $one['max'] + $gaps];
    }

    /** One page of the flow: the columns in order, from $token. */
    private function page(Node $node, ComputedStyle $style, ColumnSpec $spec, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult
    {
        $boxes = $spec->columns($c->width);
        $last = count($boxes) - 1;
        $placed = [];
        $height = 0.0;
        $next = $token;

        foreach ($boxes as $i => $box) {
            $res = $engine->blockLayouter()->layout($node, $style, new Constraints($box['width'], $c->height, $c->fresh), $next, $engine);
            if ($res->status === Status::DoesNotFit || $res->fragment === null) {
                if ($i === 0) {
                    return LayoutResult::doesNotFit();
                }
                return LayoutResult::partial(self::assemble($spec, $c->width, $placed, $height), $next ?? new BreakToken());
            }
            $placed[] = ['x' => $box['x'], 'fragment' => $res->fragment];
            $height = max($height, $res->fragment->height);
            if ($res->status === Status::Complete || $res->token === null) {
                return LayoutResult::complete(self::assemble($spec, $c->width, $placed, $height));
            }
            $next = $res->token;
            if ($next->forced === ForcedBreak::Page || $i === $last) {
                // A column break in the last column is a page break.
                if ($next->forced === ForcedBreak::Column) {
                    $next = new BreakToken($next->index, $next->child, $next->parallel, $next->headerPending, ForcedBreak::Page);
                }
                return LayoutResult::partial(self::assemble($spec, $c->width, $placed, $height), $next);
            }
        }

        throw new \LogicException('A column flow has at least one column.');
    }

    /** @param list<array{x: float, fragment: Fragment}> $placed */
    private static function assemble(ColumnSpec $spec, float $width, array $placed, float $height): Fragment
    {
        $f = new Fragment($width, $height);
        foreach ($placed as ['x' => $x, 'fragment' => $column]) {
            $f->addChild($x, 0.0, $column);
        }
        if ($spec->rule !== null) {
            $boxes = $spec->columns($width);
            foreach ($spec->gaps as $i => $gap) {
                $centre = $boxes[$i]['x'] + $boxes[$i]['width'] + $gap / 2;
                $f->addOp(new Line($centre, 0.0, $centre, $height, $spec->rule->color, $spec->rule->width));
            }
        }

        return $f;
    }

    private static function spec(Node $node): ColumnSpec
    {
        return ($node instanceof View ? $node->columnSpec() : null)
            ?? throw new \LogicException('ColumnFlowLayouter lays out column flows only.');
    }

    private static function hasBox(ComputedStyle $s): bool
    {
        $b = $s->borderWidth;
        $p = $s->padding;
        return $b->top->points !== 0.0 || $b->right->points !== 0.0 || $b->bottom->points !== 0.0 || $b->left->points !== 0.0
            || $p->top->points !== 0.0 || $p->right->points !== 0.0 || $p->bottom->points !== 0.0 || $p->left->points !== 0.0
            || $s->background !== null
            || !$s->width instanceof Auto || !$s->height instanceof Auto
            || $s->minWidth !== null || $s->maxWidth !== null || $s->minHeight !== null || $s->maxHeight !== null;
    }

    private static function where(ColumnFlowPlacement $p): string
    {
        return match ($p) {
            ColumnFlowPlacement::InCell => 'in a table cell',
            ColumnFlowPlacement::InRow => 'in a row',
            ColumnFlowPlacement::InFlow => 'in another column flow',
            ColumnFlowPlacement::OutsideBody => 'in a header, footer, background or overlay',
            ColumnFlowPlacement::OnPage, ColumnFlowPlacement::InBox => 'here',
        };
    }
}
