<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\Internal\Tokens;

final readonly class Edges
{
    private function __construct(
        public Length $top,
        public Length $right,
        public Length $bottom,
        public Length $left,
    ) {
    }

    public static function all(Length $l): self
    {
        return new self($l, $l, $l, $l);
    }

    public static function zero(): self
    {
        return self::all(Length::zero());
    }

    public static function of(Length $top, ?Length $right = null, ?Length $bottom = null, ?Length $left = null): self
    {
        $right ??= $top;
        $bottom ??= $top;
        $left ??= $right;
        return new self($top, $right, $bottom, $left);
    }

    public static function parse(string $input): self
    {
        $tokens = Tokens::split($input, 'edges');
        if (count($tokens) > 4) {
            throw InvalidValue::for('edges', $input);
        }
        $lengths = [];
        foreach ($tokens as $i => $tok) {
            try {
                $lengths[] = Length::parse($tok);
            } catch (InvalidValue $e) {
                if ($e->suggestion === null) {
                    throw InvalidValue::for('edges', $input, null, $e);
                }
                $fixed = $tokens;
                $fixed[$i] = $e->suggestion;
                throw InvalidValue::for('edges', $input, implode(' ', $fixed), $e);
            }
        }
        return self::of(...$lengths);
    }

    public function horizontal(): float
    {
        return $this->left->points + $this->right->points;
    }

    public function vertical(): float
    {
        return $this->top->points + $this->bottom->points;
    }
}
