<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class H4 extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['align', 'size'];
    }

    protected function build(array $data): object
    {
        return $this->textStyled(\Papercut\Text::markup(trim($this->text($data)))->withPreset('h4'), $data);
    }
}
