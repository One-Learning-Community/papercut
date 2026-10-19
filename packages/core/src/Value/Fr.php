<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;

final readonly class Fr implements \Stringable
{
    private function __construct(public float $value)
    {
    }

    public static function of(float $value): self
    {
        if ($value <= 0) {
            throw InvalidValue::for('fraction', $value . 'fr');
        }
        return new self($value);
    }

    public static function parse(string $input): self
    {
        $s = trim($input);
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)fr$/', $s, $m) && (float) $m[1] > 0) {
            return new self((float) $m[1]);
        }
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)\s*fr$/i', $s, $m) && (float) $m[1] > 0) {
            throw InvalidValue::for('fraction', $input, $m[1] . 'fr');
        }
        throw InvalidValue::for('fraction', $input);
    }

    public function __toString(): string
    {
        return rtrim(rtrim(number_format($this->value, 4, '.', ''), '0'), '.') . 'fr';
    }
}
