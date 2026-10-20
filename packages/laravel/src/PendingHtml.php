<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Contracts\Support\Htmlable;
use Papercut\Export\Output;
use Symfony\Component\HttpFoundation\Response;

/** HTML output: embeds as a fragment in Blade, and exports, saves or responds as a full page. */
final class PendingHtml extends PendingOutput implements Htmlable, \Stringable
{
    private ?Output $fragmentOutput = null;

    /**
     * @param \Closure(): Output $fragment the embeddable `.papercut` fragment
     * @param \Closure(): Output $page the full HTML page
     */
    public function __construct(private readonly \Closure $fragment, \Closure $page)
    {
        parent::__construct($page);
    }

    /** The fragment, for `{!! $doc->html() !!}` inside another Blade view. */
    public function toHtml(): string
    {
        return ($this->fragmentOutput ??= ($this->fragment)())->bytes();
    }

    /** The fragment, unlike toString(), which is the full page. Lets `{!! $doc->html() !!}` print it. */
    public function __toString(): string
    {
        return $this->toHtml();
    }

    /** @param \Illuminate\Http\Request $request */
    public function toResponse($request): Response
    {
        $output = $this->output();

        return new Response($output->bytes(), 200, [
            'Content-Type' => $output->mime(),
            'Content-Length' => (string) strlen($output->bytes()),
        ]);
    }
}
