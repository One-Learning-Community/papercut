<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Str;
use Papercut\Document;
use Papercut\RenderOptions;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class PendingPdf implements Responsable
{
    private ?string $rendered = null;

    public function __construct(private readonly Document $document, private readonly RenderOptions $options)
    {
    }

    public function toString(): string
    {
        return $this->rendered ??= $this->document->render($this->options);
    }

    public function save(string $path): self
    {
        file_put_contents($path, $this->toString());

        return $this;
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);
    }

    public function inline(string $filename = 'document.pdf'): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_INLINE, $filename);
    }

    /** @param \Illuminate\Http\Request $request */
    public function toResponse($request): Response
    {
        return $this->inline();
    }

    private function respond(string $disposition, string $filename): Response
    {
        $body = $this->toString();
        $filename = str_replace(['/', '\\'], '-', $filename);

        return new Response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, self::asciiFallback($filename)),
            'Content-Length' => (string) strlen($body),
        ]);
    }

    private static function asciiFallback(string $filename): string
    {
        $fallback = str_replace('%', '_', (string) preg_replace('/[^\x20-\x7e]/', '_', Str::ascii($filename)));

        return $fallback === '' ? 'document.pdf' : $fallback;
    }
}
