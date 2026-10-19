<?php

declare(strict_types=1);

namespace Papercut\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Papercut\Laravel\PdfManager;

/**
 * @method static \Papercut\Laravel\PendingPdf view(string $view, array<string, mixed> $data = [])
 * @method static \Papercut\Laravel\PendingPdf make(\Papercut\Document $doc)
 * @method static \Papercut\RenderOptions options()
 * @method static void preview(string $view, \Closure|array<string, mixed> $data = [])
 *
 * @see PdfManager
 */
final class Pdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PdfManager::class;
    }
}
