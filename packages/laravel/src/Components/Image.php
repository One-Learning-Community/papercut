<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\{ImageUnreadable, InvalidValue};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Style\ImageFit;

final class Image extends PdfComponent
{
    protected function ownAttributes(): array
    {
        return ['src', 'data', 'fit'];
    }

    protected function build(array $data): object
    {
        $own = $this->attrs($data)['own'];
        $src = $own['src'] ?? null;
        $bytes = $own['data'] ?? null;
        if (($src === null) === ($bytes === null)) {
            throw new InvalidStructure('<x-pdf::image> requires exactly one of src or data');
        }

        if ($src !== null) {
            $path = (string) $src;
            if (!str_starts_with($path, '/')) {
                $base = config('papercut.image_path');
                $base = is_string($base) && $base !== '' ? $base : public_path();
                $path = rtrim($base, '/') . '/' . $path;
            }
            if (!is_file($path)) {
                throw new ImageUnreadable("Image not found: $path");
            }
            $node = \Papercut\Image::file($path);
        } else {
            $node = \Papercut\Image::bytes((string) $bytes);
        }

        if (isset($own['fit'])) {
            $fit = ImageFit::tryFrom((string) $own['fit'])
                ?? throw InvalidValue::for('fit', (string) $own['fit'])->because('expected contain, cover or fill')->withContext('fit')->within('on <x-pdf::image>');
            $node = $node->fit($fit);
        }

        return $this->styled($node, $data);
    }
}
