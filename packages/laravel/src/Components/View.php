<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class View extends PdfComponent
{
    protected function build(array $data): object
    {
        return $this->styled(\Papercut\View::make(...$this->children($data)), $data);
    }
}
