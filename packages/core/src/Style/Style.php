<?php

declare(strict_types=1);

namespace Papercut\Style;

use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Value\{Auto, Color, Edges, Length, Percent};

/**
 * A sparse bag of style properties; null means "not set".
 */
final readonly class Style
{
    /**
     * Attribute key => [property, parser]. Parsers: edges, length, lengthPercent,
     * lengthPercentAuto, color, float>=0, float01, int>=1, bool, string, enum:<class>.
     */
    private const array ATTRIBUTES = [
        'width' => ['width', 'lengthPercentAuto'],
        'height' => ['height', 'lengthPercentAuto'],
        'min-width' => ['minWidth', 'lengthPercent'],
        'max-width' => ['maxWidth', 'lengthPercent'],
        'min-height' => ['minHeight', 'lengthPercent'],
        'max-height' => ['maxHeight', 'lengthPercent'],
        'padding' => ['padding', 'edges'],
        'margin' => ['margin', 'edges'],
        'border-width' => ['borderWidth', 'edges'],
        'border-color' => ['borderColor', 'color'],
        'border-style' => ['borderStyle', 'enum:' . BorderStyle::class],
        'radius' => ['radius', 'length'],
        'background' => ['background', 'color'],
        'opacity' => ['opacity', 'float01'],
        'direction' => ['direction', 'enum:' . Direction::class],
        'gap' => ['gap', 'length'],
        'justify' => ['justify', 'enum:' . Justify::class],
        'align-items' => ['alignItems', 'enum:' . Align::class],
        'align-self' => ['alignSelf', 'enum:' . Align::class],
        'grow' => ['grow', 'float>=0'],
        'shrink' => ['shrink', 'float>=0'],
        'basis' => ['basis', 'lengthPercentAuto'],
        'wrap' => ['wrap', 'bool'],
        'font' => ['fontFamily', 'string'],
        'font-size' => ['fontSize', 'length'],
        'weight' => ['fontWeight', 'int>=1'],
        'italic' => ['italic', 'bool'],
        'bold' => ['fontWeight', 'bold'],
        'color' => ['color', 'color'],
        'line-height' => ['lineHeight', 'float>=0'],
        'letter-spacing' => ['letterSpacing', 'length'],
        'text-align' => ['textAlign', 'enum:' . TextAlign::class],
        'underline' => ['underline', 'bool'],
        'strike' => ['strike', 'bool'],
        'keep-together' => ['keepTogether', 'bool'],
        'keep-with-next' => ['keepWithNext', 'bool'],
        'break-before' => ['breakBefore', 'bool'],
        'break-after' => ['breakAfter', 'bool'],
        'min-lines' => ['minLines', 'int>=1'],
    ];

    public function __construct(
        public Length|Percent|Auto|null $width = null,
        public Length|Percent|Auto|null $height = null,
        public Length|Percent|null $minWidth = null,
        public Length|Percent|null $maxWidth = null,
        public Length|Percent|null $minHeight = null,
        public Length|Percent|null $maxHeight = null,
        public ?Edges $padding = null,
        public ?Edges $margin = null,
        public ?Edges $borderWidth = null,
        public ?Color $borderColor = null,
        public ?BorderStyle $borderStyle = null,
        public ?Length $radius = null,
        public ?Color $background = null,
        public ?float $opacity = null,
        public ?Direction $direction = null,
        public ?Length $gap = null,
        public ?Justify $justify = null,
        public ?Align $alignItems = null,
        public ?Align $alignSelf = null,
        public ?float $grow = null,
        public ?float $shrink = null,
        public Length|Percent|Auto|null $basis = null,
        public ?bool $wrap = null,
        public ?string $fontFamily = null,
        public ?Length $fontSize = null,
        public ?int $fontWeight = null,
        public ?bool $italic = null,
        public ?Color $color = null,
        public ?float $lineHeight = null,
        public ?Length $letterSpacing = null,
        public ?TextAlign $textAlign = null,
        public ?bool $underline = null,
        public ?bool $strike = null,
        public ?bool $keepTogether = null,
        public ?bool $keepWithNext = null,
        public ?bool $breakBefore = null,
        public ?bool $breakAfter = null,
        public ?int $minLines = null,
    ) {
    }

    /**
     * Named-argument factory; accepts exactly the constructor's parameters.
     */
    public static function make(mixed ...$properties): self
    {
        return new self(...$properties);
    }

    public function merge(Style $over): self
    {
        return new self(...array_merge(
            get_object_vars($this),
            array_filter(get_object_vars($over), static fn (mixed $v): bool => $v !== null),
        ));
    }

    public function isEmpty(): bool
    {
        return array_filter(get_object_vars($this), static fn (mixed $v): bool => $v !== null) === [];
    }

    /**
     * @param array<string, string|int|float|bool|object> $attrs kebab-case Blade attribute names
     */
    public static function fromAttributes(array $attrs): self
    {
        $props = [];
        foreach ($attrs as $key => $value) {
            $key = (string) $key;
            if (!isset(self::ATTRIBUTES[$key])) {
                throw UnknownAttribute::for($key, self::suggest($key, array_keys(self::ATTRIBUTES)));
            }
            [$property, $parser] = self::ATTRIBUTES[$key];
            try {
                $props[$property] = self::parse($parser, $value);
            } catch (InvalidValue $e) {
                throw $e->withContext($key);
            }
        }
        return new self(...$props);
    }

    private static function parse(string $parser, string|int|float|bool|object $v): mixed
    {
        if (str_starts_with($parser, 'enum:')) {
            /** @var class-string<\BackedEnum> $enum */
            $enum = substr($parser, 5);
            return self::parseEnum($enum, $v);
        }

        return match ($parser) {
            'edges' => $v instanceof Edges ? $v : Edges::parse(self::str($v, 'edges')),
            'length' => $v instanceof Length ? $v : Length::parse(self::str($v, 'length')),
            'lengthPercent' => self::parseLengthPercent($v, false),
            'lengthPercentAuto' => self::parseLengthPercent($v, true),
            'color' => $v instanceof Color ? $v : Color::parse(self::str($v, 'color')),
            'float>=0' => self::parseNumber($v, 'number', false, 0.0),
            'float01' => self::parseOpacity($v),
            'int>=1' => (int) self::parseNumber($v, 'integer', true, 1.0),
            'bool' => self::parseBool($v),
            'bold' => self::parseBool($v) ? 700 : 400,
            'string' => self::str($v, 'string'),
            default => throw new \LogicException(sprintf('Unknown attribute parser "%s".', $parser)),
        };
    }

    private static function str(string|int|float|bool|object $v, string $kind): string
    {
        if (is_string($v)) {
            return $v;
        }
        throw InvalidValue::for($kind, get_debug_type($v));
    }

    private static function parseLengthPercent(string|int|float|bool|object $v, bool $allowAuto): Length|Percent|Auto
    {
        if ($v instanceof Length || $v instanceof Percent || ($allowAuto && $v instanceof Auto)) {
            return $v;
        }
        $s = self::str($v, $allowAuto ? 'length, percentage or auto' : 'length or percentage');
        if ($allowAuto && trim($s) === 'auto') {
            return Auto::instance();
        }
        return str_ends_with(trim($s), '%') ? Percent::parse($s) : Length::parse($s);
    }

    private static function parseNumber(string|int|float|bool|object $v, string $kind, bool $integer, float $min): float
    {
        if (is_bool($v) || is_object($v) || (is_string($v) && !is_numeric($v))) {
            throw InvalidValue::for($kind, is_string($v) ? $v : get_debug_type($v));
        }
        $n = (float) $v;
        if (!is_finite($n) || $n < $min || ($integer && $n !== floor($n))) {
            throw InvalidValue::for($kind, (string) $v);
        }
        return $n;
    }

    private static function parseOpacity(string|int|float|bool|object $v): float
    {
        $n = self::parseNumber($v, 'opacity', false, 0.0);
        if ($n > 1.0) {
            throw InvalidValue::for('opacity', (string) $n);
        }
        return $n;
    }

    private static function parseBool(string|int|float|bool|object $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_object($v)) {
            throw InvalidValue::for('boolean', get_debug_type($v), 'true');
        }
        return match (strtolower(trim((string) $v))) {
            'true', '', '1' => true,
            'false', '0' => false,
            default => throw InvalidValue::for('boolean', (string) $v, 'true'),
        };
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    private static function parseEnum(string $enum, string|int|float|bool|object $v): \BackedEnum
    {
        if ($v instanceof $enum) {
            return $v;
        }
        $s = self::str($v, 'value');
        $case = $enum::tryFrom($s);
        if ($case !== null) {
            return $case;
        }
        $values = array_map(static fn (\BackedEnum $c): string => (string) $c->value, $enum::cases());
        throw InvalidValue::for('value', $s, self::suggest($s, $values));
    }

    /**
     * @param list<string> $candidates
     */
    private static function suggest(string $input, array $candidates): ?string
    {
        $best = null;
        $bestDistance = 3;
        foreach ($candidates as $candidate) {
            $d = levenshtein($input, $candidate);
            if ($d < $bestDistance) {
                $best = $candidate;
                $bestDistance = $d;
            }
        }
        return $best;
    }
}
