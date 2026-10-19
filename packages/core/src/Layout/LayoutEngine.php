<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Component, Image, Link, Node, PageBreak, PageNumber, Spacer, Table, TableCell, Text, View};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
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

    private readonly BlockLayouter $block;
    private readonly FlexLayouter $flex;

    public function __construct(private readonly LayoutContext $ctx)
    {
        $this->block = new BlockLayouter();
        $this->flex = new FlexLayouter();
        $leaf = new LeafLayouter();
        $this->layouters[Text::class] = new TextLayouter();
        $this->layouters[Link::class] = new LinkLayouter($this->block);
        $this->layouters[Image::class] = new ImageLayouter();
        $table = new TableLayouter($this->block);
        $this->layouters[Table::class] = $table;
        $this->layouters[TableCell::class] = $table;
        $this->layouters[Spacer::class] = $leaf;
        $this->layouters[PageBreak::class] = $leaf;
        $this->layouters[PageNumber::class] = $leaf;
    }

    public function context(): LayoutContext
    {
        return $this->ctx;
    }

    /** $index is the node's position among its siblings; it is shown in the path, e.g. "column[2]". */
    public function layout(Node|Component $node, ComputedStyle $parent, Constraints $c, ?BreakToken $token = null, ?int $index = null): LayoutResult
    {
        $node = self::expand($node);
        $this->ctx->pushPath(self::pathName($node, null, $index));
        try {
            $style = $this->enter($node, $parent, $index);
            $result = $this->layouterFor($node, $style)->layout($node, $style, $c, $token, $this);
            if ($result->status === Status::DoesNotFit && $c->fresh) {
                $this->recordFailure();
            }
            return $result;
        } finally {
            $this->ctx->popPath();
        }
    }

    /** Keeps an already recorded descendant failure: the deepest node is the useful one. */
    private function recordFailure(): void
    {
        $path = $this->ctx->path();
        $last = $this->ctx->lastFailurePath;
        if ($last !== null && str_starts_with($last, $path . ' > ')) {
            return;
        }
        $this->ctx->setLastFailurePath($path);
    }

    /** @return array{min: float, max: float} */
    public function intrinsic(Node|Component $node, ComputedStyle $parent, ?int $index = null): array
    {
        $node = self::expand($node);
        $this->ctx->pushPath(self::pathName($node, null, $index));
        try {
            $style = $this->enter($node, $parent, $index);
            return $this->layouterFor($node, $style)->intrinsic($node, $style, $this);
        } finally {
            $this->ctx->popPath();
        }
    }

    /** Computed style of a child node, with style errors reported at its path. */
    public function styleOf(Node $node, ComputedStyle $parent, ?int $index = null): ComputedStyle
    {
        $this->ctx->pushPath(self::pathName($node, null, $index));
        try {
            return $this->enter($node, $parent, $index);
        } finally {
            $this->ctx->popPath();
        }
    }

    /**
     * Resolves the node's style while a provisional path entry is pushed, so
     * style errors name the node, then renames the entry by computed direction.
     */
    private function enter(Node $node, ComputedStyle $parent, ?int $index): ComputedStyle
    {
        try {
            $style = $this->ctx->resolveStyle($node, $parent);
        } catch (UnknownAttribute | InvalidValue $e) {
            throw $e->within('at ' . $this->ctx->path());
        }
        $this->ctx->popPath();
        $this->ctx->pushPath(self::pathName($node, $style, $index));
        return $style;
    }

    /** Renders Components down to a Node. */
    public static function expand(Node|Component $node): Node
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
    private static function pathName(Node $node, ?ComputedStyle $style, ?int $index): string
    {
        $name = $node instanceof View && $style !== null
            ? ($style->direction === Direction::Row ? 'row' : 'column')
            : $node->debugName();
        return $index === null ? $name : sprintf('%s[%d]', $name, $index);
    }

    private function layouterFor(Node $node, ComputedStyle $style): Layouter
    {
        if ($node instanceof View) {
            return $style->direction === Direction::Row ? $this->flex : $this->block;
        }
        return $this->layouters[$node::class] ?? throw new \LogicException(sprintf(
            'No layouter registered for %s at "%s".',
            $node::class,
            $this->ctx->path(),
        ));
    }
}
