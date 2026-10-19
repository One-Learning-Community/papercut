<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class Text extends PdfComponent
{
    protected function ownAttributes(): array
    {
        return ['align', 'size'];
    }

    protected function build(array $data): object
    {
        return $this->textStyled(\Papercut\Text::markup(trim($this->text($data))), $data);
    }
}
