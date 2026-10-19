<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Node, Spacer};
use Papercut\Concerns\HasStyle;
use Papercut\Style\{ComputedStyle, Style};

use function Papercut\pt;

/**
 * Lays a node out at exactly a given border-box width (a flex item's
 * resolved main size, a table cell's column span). A node whose own
 * width/min/max would resolve to something else gets them pinned to that
 * width; any node built on HasStyle can be pinned. Spacers only hold space.
 */
final class ExactWidth
{
    private const float EPSILON = 1e-9;

    /** @var array<class-string, bool> */
    private static array $styleable = [];

    private function __construct()
    {
    }

    /** $style is the node's computed style when the caller has it; otherwise it is resolved here. */
    public static function layout(Node $node, float $width, ComputedStyle $parent, Constraints $c, ?BreakToken $token, LayoutEngine $engine, int $index, ?ComputedStyle $style = null): LayoutResult
    {
        if ($node instanceof Spacer) {
            return LayoutResult::complete(new Fragment($width, 0.0));
        }
        return $engine->layout(self::pin($node, $width, $style ?? $engine->styleOf($node, $parent, $index)), $parent, $c->withWidth($width), $token, $index);
    }

    /** $node with width, min-width and max-width set to $width, unless its style already resolves to it. */
    public static function pin(Node $node, float $width, ComputedStyle $style): Node
    {
        if (abs(BoxGeometry::borderBoxWidth($style, $width) - $width) <= self::EPSILON || !self::styleable($node) || !method_exists($node, 'apply')) {
            return $node;
        }
        $w = pt($width);
        $pinned = $node->apply(new Style(width: $w, minWidth: $w, maxWidth: $w));
        return $pinned instanceof Node ? $pinned : $node;
    }

    /** True for nodes whose class (or a parent class) uses the HasStyle trait. */
    public static function styleable(Node $node): bool
    {
        return self::$styleable[$node::class] ??= (static function (Node $node): bool {
            foreach ([$node::class, ...array_values(class_parents($node))] as $class) {
                if (in_array(HasStyle::class, class_uses($class), true)) {
                    return true;
                }
            }
            return false;
        })($node);
    }
}
