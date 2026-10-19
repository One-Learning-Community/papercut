<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Component, Node, Text, View};
use Papercut\Style\{ComputedStyle, Direction};

/**
 * Dispatches nodes to their layouters. Expands Components, resolves the
 * node's computed style and maintains the context's node path.
 */
final class LayoutEngine
{
    private const int MAX_COMPONENT_DEPTH = 100;

    /** @var array<class-string<Node>, Layouter> */
    private array $layouters = [];

    public function __construct(private readonly LayoutContext $ctx)
    {
        $this->layouters[Text::class] = new TextLayouter();
    }

    public function context(): LayoutContext
    {
        return $this->ctx;
    }

    public function layout(Node|Component $node, ComputedStyle $parent, Constraints $c, ?BreakToken $token = null): LayoutResult
    {
        $node = self::expand($node);
        $style = $this->ctx->resolveStyle($node, $parent);
        $this->ctx->pushPath(self::pathName($node, $style));
        try {
            return $this->layouterFor($node)->layout($node, $style, $c, $token, $this);
        } finally {
            $this->ctx->popPath();
        }
    }

    /** @return array{min: float, max: float} */
    public function intrinsic(Node|Component $node, ComputedStyle $parent): array
    {
        $node = self::expand($node);
        $style = $this->ctx->resolveStyle($node, $parent);
        $this->ctx->pushPath(self::pathName($node, $style));
        try {
            return $this->layouterFor($node)->intrinsic($node, $style, $this);
        } finally {
            $this->ctx->popPath();
        }
    }

    private static function expand(Node|Component $node): Node
    {
        $depth = 0;
        while (!$node instanceof Node) {
            if (++$depth > self::MAX_COMPONENT_DEPTH) {
                throw new \LogicException(sprintf(
                    'Component %s did not render to a node within %d levels; check for a component that renders itself.',
                    $node::class,
                    self::MAX_COMPONENT_DEPTH,
                ));
            }
            $node = $node->render();
        }
        return $node;
    }

    /** Views are named by their computed direction; debugName() can be stale after attributes(). */
    private static function pathName(Node $node, ComputedStyle $style): string
    {
        if ($node instanceof View) {
            return $style->direction === Direction::Row ? 'row' : 'column';
        }
        return $node->debugName();
    }

    private function layouterFor(Node $node): Layouter
    {
        return $this->layouters[$node::class] ?? throw new \LogicException(sprintf(
            'No layouter registered for %s at "%s".',
            $node::class,
            $this->ctx->path(),
        ));
    }
}
