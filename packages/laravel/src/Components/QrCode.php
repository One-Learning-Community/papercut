<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Barcode\ErrorCorrection;
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;

final class QrCode extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['value', 'level', 'quiet-zone'];
    }

    protected function build(array $data): object
    {
        if (trim($this->text($data)) !== '') {
            throw new InvalidStructure(Tag::of('qr-code') . ' must not have content; use the value attribute');
        }
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        // A misspelt value or level would otherwise reach the style check, which only suggests style names.
        foreach (array_keys($style) as $key) {
            foreach ($this->ownAttributes() as $candidate) {
                if (levenshtein((string) $key, $candidate) <= 2) {
                    throw UnknownAttribute::for((string) $key, $candidate)->within('on ' . Tag::of('qr-code'));
                }
            }
        }
        $value = $own['value'] ?? null;
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidStructure(Tag::of('qr-code') . ' requires a value');
        }
        $node = \Papercut\QrCode::make((string) $value, self::level($own['level'] ?? 'M'));
        if (isset($own['quiet-zone'])) {
            $zone = $own['quiet-zone'];
            if (!is_numeric($zone) || (string) (int) $zone !== trim((string) $zone) || (int) $zone < 0) {
                throw InvalidValue::for('quiet-zone', is_scalar($zone) ? (string) $zone : get_debug_type($zone))->because('expected a whole number of modules, 0 or more')->withContext('quiet-zone')->within('on ' . Tag::of('qr-code'));
            }
            $node = $node->quietZone((int) $zone);
        }

        return $this->styled($node, $data);
    }

    private static function level(mixed $level): ErrorCorrection
    {
        if ($level instanceof ErrorCorrection) {
            return $level;
        }
        $key = is_string($level) ? strtolower(trim($level)) : '';

        return match ($key) {
            'l', 'low' => ErrorCorrection::Low,
            'm', 'medium' => ErrorCorrection::Medium,
            'q', 'quartile' => ErrorCorrection::Quartile,
            'h', 'high' => ErrorCorrection::High,
            default => throw InvalidValue::for('level', is_scalar($level) ? (string) $level : get_debug_type($level))->because('expected L, M, Q or H')->withContext('level')->within('on ' . Tag::of('qr-code')),
        };
    }
}
