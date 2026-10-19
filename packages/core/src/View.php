<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Concerns\HasStyle;
use Papercut\Style\{Align, Direction, Justify, Style};
use Papercut\Value\Length;

final class View implements Styled
{
    use HasStyle;

    /** @var list<Node|Component> */
    private array $children = [];
    private bool $explicitDirection = false;

    private function __construct()
    {
    }

    public static function make(Node|Component|string ...$children): self
    {
        $view = new self();
        $view->style = new Style(direction: Direction::Column);
        $view->children = self::normalize($children);
        return $view;
    }

    /**
     * Wraps string children in Text nodes.
     *
     * @internal
     * @param array<Node|Component|string> $children
     * @return list<Node|Component>
     */
    public static function normalize(array $children): array
    {
        $out = [];
        foreach ($children as $child) {
            $out[] = is_string($child) ? Text::make($child) : $child;
        }
        return $out;
    }

    /**
     * @return list<Node|Component>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function withChildren(Node|Component|string ...$children): self
    {
        $clone = clone $this;
        $clone->children = self::normalize($children);
        return $clone;
    }

    public function debugName(): string
    {
        if (!$this->explicitDirection) {
            return 'view';
        }
        return $this->style()->direction === Direction::Row ? 'row' : 'column';
    }

    public function direction(Direction $d): self
    {
        $clone = $this->withStyle(new Style(direction: $d));
        $clone->explicitDirection = true;
        return $clone;
    }

    public function gap(Length $g): self
    {
        return $this->withStyle(new Style(gap: $g));
    }

    public function justify(Justify $j): self
    {
        return $this->withStyle(new Style(justify: $j));
    }

    public function align(Align $a): self
    {
        return $this->withStyle(new Style(alignItems: $a));
    }

    public function wrap(bool $on = true): self
    {
        return $this->withStyle(new Style(wrap: $on));
    }
}
