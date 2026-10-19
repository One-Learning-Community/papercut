<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Value\Length;

final class Spacer extends PdfComponent
{
    protected function ownAttributes(): array
    {
        return ['size'];
    }

    protected function build(array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        foreach (array_keys($style) as $key) {
            if ($key !== 'grow') {
                throw UnknownAttribute::for($key)->within('on <x-pdf::spacer>');
            }
        }

        if (isset($own['size'])) {
            $size = $own['size'];
            try {
                $length = $size instanceof Length ? $size : Length::parse((string) $size);
            } catch (InvalidValue $e) {
                throw $e->withContext('size')->within('on <x-pdf::spacer>');
            }
            $node = \Papercut\Spacer::size($length);
        } else {
            $grow = $style['grow'] ?? 1;
            $node = \Papercut\Spacer::flex(is_numeric($grow) ? (float) $grow : 1.0);
            unset($style['grow']);
        }

        return $this->styled($node, [...$data, 'attributes' => new \Illuminate\View\ComponentAttributeBag([...$style, ...(isset($own['style']) ? ['style' => $own['style']] : [])])]);
    }
}
