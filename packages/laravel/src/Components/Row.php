<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class Row extends PdfComponent
{
    protected function build(array $data): object
    {
        return $this->styled(\Papercut\Row::make(...$this->children($data)), $data);
    }
}
