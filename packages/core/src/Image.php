<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Concerns\HasStyle;
use Papercut\Style\ImageFit;
use Papercut\Support\EnumValue;

final class Image implements Styled
{
    use HasStyle;

    private ImageFit $fit = ImageFit::Contain;
    private ?string $altText = null;
    private bool $decorative = false;

    private function __construct(
        private readonly string $kind,
        private readonly string $value,
    ) {
    }

    public static function file(string $path): self
    {
        return new self('file', $path);
    }

    public static function bytes(string $bytes): self
    {
        return new self('bytes', $bytes);
    }

    /** @throws \Papercut\Exception\InvalidValue for a string that is not a fit mode */
    public function fit(ImageFit|string $fit): self
    {
        $fit = EnumValue::from(ImageFit::class, $fit);
        $clone = clone $this;
        $clone->fit = $fit;
        return $clone;
    }

    /**
     * Alternative text for screen readers: the description in tagged PDF, the `alt` in HTML and the description
     * in Word. Three states: never set (or null) is missing, which a tagged PDF warns about; a blank string
     * (`alt('')`) says the image is purely decorative, so it is kept out of a tagged PDF's structure and
     * written with an empty description; anything else is the alt text.
     */
    public function alt(?string $alt): self
    {
        $clone = clone $this;
        $clone->decorative = $alt !== null && trim($alt) === '';
        $clone->altText = $alt === null || trim($alt) === '' ? null : trim($alt);
        return $clone;
    }

    /** The alternative text, or null when it is missing or the image is decorative. */
    public function altText(): ?string
    {
        return $this->altText;
    }

    /** True for an explicit `alt('')`: the image is deliberately decorative, as opposed to missing its alt text. */
    public function isDecorative(): bool
    {
        return $this->decorative;
    }

    public function fitMode(): ImageFit
    {
        return $this->fit;
    }

    /**
     * @return array{kind: 'file'|'bytes', value: string}
     */
    public function source(): array
    {
        /** @var 'file'|'bytes' $kind */
        $kind = $this->kind;
        return ['kind' => $kind, 'value' => $this->value];
    }

    public function debugName(): string
    {
        return 'image';
    }
}
