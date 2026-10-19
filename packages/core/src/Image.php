<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Backend\ImageHandle;
use Papercut\Concerns\HasStyle;
use Papercut\Style\ImageFit;

final class Image implements Styled
{
    use HasStyle;

    private ImageFit $fit = ImageFit::Contain;

    private function __construct(
        private readonly string $kind,
        private readonly string|ImageHandle $value,
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

    public static function handle(ImageHandle $h): self
    {
        return new self('handle', $h);
    }

    public function fit(ImageFit $fit): self
    {
        $clone = clone $this;
        $clone->fit = $fit;
        return $clone;
    }

    public function fitMode(): ImageFit
    {
        return $this->fit;
    }

    /**
     * @return array{kind: 'file'|'bytes'|'handle', value: string|ImageHandle}
     */
    public function source(): array
    {
        /** @var 'file'|'bytes'|'handle' $kind */
        $kind = $this->kind;
        return ['kind' => $kind, 'value' => $this->value];
    }

    public function debugName(): string
    {
        return 'image';
    }
}
