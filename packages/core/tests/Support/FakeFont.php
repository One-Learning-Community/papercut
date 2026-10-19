<?php

declare(strict_types=1);

namespace Papercut\Tests\Support;

use Papercut\Backend\FontHandle;

final class FakeFont implements FontHandle
{
    public int $advanceCalls = 0;

    public function id(): string
    {
        return 'fake';
    }

    public function advance(string $text, float $size, float $letterSpacing = 0.0): float
    {
        $this->advanceCalls++;
        $n = mb_strlen($text, 'UTF-8');
        return $n * 0.5 * $size + $n * $letterSpacing;
    }

    public function ascent(float $size): float
    {
        return 0.8 * $size;
    }

    public function descent(float $size): float
    {
        return 0.2 * $size;
    }

    public function covers(string $text): bool
    {
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            if (mb_ord($ch, 'UTF-8') >= 0x3000) {
                return false;
            }
        }
        return true;
    }
}
