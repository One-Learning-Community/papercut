<?php

declare(strict_types=1);

namespace Papercut\Paint;

use Papercut\Exception\InvalidValue;

/** @internal Part of the display list between layout and the PDF writer. It may change in any release. */
final readonly class Link implements Op
{
    /** The validated URI, leading whitespace removed. */
    public string $uri;
    /** What the link says, whitespace-normalized, or null: the description of its annotation in a tagged PDF. */
    public ?string $description;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        string $uri,
        ?string $description = null,
    ) {
        if (!is_finite($x) || !is_finite($y) || !is_finite($width) || !is_finite($height)) {
            throw new \InvalidArgumentException('Link received a non-finite number.');
        }
        if (!self::allowed($uri)) {
            throw InvalidValue::for('link', $uri)->because('only http, https, mailto, tel and #fragment links are allowed');
        }
        $this->uri = ltrim($uri);
        $this->description = self::normalize($description);
    }

    /** Whitespace collapsed and trimmed; null for nothing left. */
    public static function normalize(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        return $text === '' ? null : $text;
    }

    /** http, https, mailto, tel or a #fragment, case-insensitive, ignoring leading whitespace. */
    public static function allowed(string $uri): bool
    {
        return preg_match('~^(?:(?:https?|mailto|tel):|#)~i', ltrim($uri)) === 1;
    }
}
