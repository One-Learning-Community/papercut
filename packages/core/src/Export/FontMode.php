<?php

declare(strict_types=1);

namespace Papercut\Export;

enum FontMode: string
{
    case None = 'none';
    case Embed = 'embed';
    case Link = 'link';
}
