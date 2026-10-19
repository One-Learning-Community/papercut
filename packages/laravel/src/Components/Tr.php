<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\TableCell;
use Papercut\TableRow;

final class Tr extends PdfComponent
{
    protected function build(array $data): object
    {
        $cells = [];
        foreach ($this->rawChildren($data, [TableCell::class]) as $child) {
            if (!$child instanceof TableCell) {
                throw new InvalidStructure('<x-pdf::tr> can only contain <x-pdf::td> or <x-pdf::th>');
            }
            $cells[] = $child;
        }

        return $this->styled(TableRow::make(...$cells), $data);
    }
}
