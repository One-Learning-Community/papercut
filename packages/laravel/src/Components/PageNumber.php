<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Exception\InvalidStructure;

final class PageNumber extends PdfComponent
{
    protected function ownAttributes(): array
    {
        return ['format', 'align', 'size'];
    }

    protected function build(array $data): object
    {
        if (trim($this->text($data)) !== '') {
            throw new InvalidStructure('<x-pdf::page-number> must not have content; use the format attribute');
        }
        $format = $this->attrs($data)['own']['format'] ?? '{page}';

        return $this->textStyled(\Papercut\PageNumber::make(is_string($format) ? $format : '{page}'), $data);
    }
}
