<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;

final readonly class Length implements \Stringable
{
    private const string NUMBER = '-?(?:\d+(?:\.\d+)?|\.\d+)';

    private function __construct(public float $points)
    {
    }

    public static function of(float $value, Unit $unit): self
    {
        $points = $value * $unit->points();
        if (!is_finite($value) || !is_finite($points)) {
            throw InvalidValue::for('length', InvalidValue::number($value));
        }
        return new self($points);
    }

    public static function points(float $pt): self
    {
        if (!is_finite($pt)) {
            throw InvalidValue::for('length', InvalidValue::number($pt));
        }
        return new self($pt);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function parse(string $input): self
    {
        $s = trim($input);
        if (preg_match('/^(' . self::NUMBER . ')(pt|px|mm|cm|in)$/', $s, $m)) {
            $number = (float) $m[1];
            if (!is_finite($number) || !is_finite($number * Unit::from($m[2])->points())) {
                throw InvalidValue::for('length', $input);
            }
            return self::of($number, Unit::from($m[2]));
        }
        if (preg_match('/^' . self::NUMBER . '$/', $s)) {
            if ((float) $s === 0.0) {
                return self::zero();
            }
            throw InvalidValue::for('length', $input, $s . 'pt');
        }
        if (preg_match('/^(' . self::NUMBER . ')\s+(pt|px|mm|cm|in)$/i', $s, $m)) {
            throw InvalidValue::for('length', $input, $m[1] . strtolower($m[2]));
        }
        if (preg_match('/^(' . self::NUMBER . ')(pt|px|mm|cm|in)$/i', $s, $m)) {
            throw InvalidValue::for('length', $input, $m[1] . strtolower($m[2]));
        }
        throw InvalidValue::for('length', $input);
    }

    public function plus(Length $other): self
    {
        return self::points($this->points + $other->points);
    }

    public function minus(Length $other): self
    {
        return self::points($this->points - $other->points);
    }

    public function scale(float $factor): self
    {
        return self::points($this->points * $factor);
    }

    public function equals(Length $other, float $epsilon = 1e-6): bool
    {
        return abs($this->points - $other->points) <= $epsilon;
    }

    public function __toString(): string
    {
        $n = rtrim(rtrim(number_format($this->points, 4, '.', ''), '0'), '.');
        return ($n === '-0' ? '0' : $n) . 'pt';
    }
}
