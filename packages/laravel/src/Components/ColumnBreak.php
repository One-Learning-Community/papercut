<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Exception\UnknownAttribute;

final class ColumnBreak extends PapercutComponent
{
    protected function build(array $data): object
    {
        $attrs = $this->attrs($data);
        foreach (array_keys([...$attrs['own'], ...$attrs['style']]) as $key) {
            throw UnknownAttribute::for((string) $key)->within('on ' . Tag::of('column-break'));
        }

        return \Papercut\ColumnBreak::make();
    }
}
