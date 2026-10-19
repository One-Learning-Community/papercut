<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class Column extends PdfComponent
{
    protected function build(array $data): object
    {
        return $this->styled(\Papercut\Column::make(...$this->children($data)), $data);
    }
}
