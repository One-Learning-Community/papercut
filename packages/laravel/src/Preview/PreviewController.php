<?php

declare(strict_types=1);

namespace Papercut\Laravel\Preview;

use Illuminate\Http\Response as HttpResponse;
use Papercut\Laravel\PdfManager;
use Symfony\Component\HttpFoundation\Response;

final class PreviewController
{
    public function __construct(private readonly PreviewRegistry $registry, private readonly PdfManager $pdf)
    {
    }

    public function index(): Response
    {
        $prefix = '/' . trim((string) config('papercut.preview.path', '_papercut'), '/');
        $items = '';
        foreach ($this->registry->names() as $name) {
            $e = e($name);
            $items .= '<li><a href="' . e($prefix . '/' . $name) . '">' . $e . '</a></li>';
        }

        return new HttpResponse(
            '<!doctype html><html><head><meta charset="utf-8"><title>Papercut previews</title></head><body>'
            . '<h1>Papercut previews</h1><ul>' . $items . '</ul></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function show(string $view): Response
    {
        abort_unless($this->registry->has($view), 404);

        return $this->pdf->view($view, $this->registry->data($view))->inline();
    }
}
