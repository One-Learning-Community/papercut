<?php

declare(strict_types=1);

namespace Papercut\Decorator;

use Papercut\{Component, Node, Text, View};
use Papercut\Concerns\{HasStyle, HasVariants, NameRules};

/**
 * A small rounded label in a tone; block-level, sized to its text.
 */
final class Badge implements Decorator
{
    use HasStyle;
    use HasVariants;

    /** @internal */
    public const array TONES = ['neutral', 'note', 'tip', 'warning', 'danger'];

    private Node|Component $content;
    private string $tone = 'neutral';

    private function __construct()
    {
    }

    public static function make(string|Node|Component $content): self
    {
        $badge = new self();
        $badge->content = is_string($content) ? Text::make($content) : $content;
        return $badge;
    }

    /** Tones are free strings; `badge.<tone>` must exist in the theme. */
    public function tone(string $tone): self
    {
        $tone = NameRules::tone($tone);
        $clone = clone $this;
        $clone->tone = $tone;
        return $clone;
    }

    public function toneName(): string
    {
        return $this->tone;
    }

    /** @return list<string> */
    protected function toneNames(): array
    {
        return self::TONES;
    }

    /** @return list<string> */
    public function partNames(): array
    {
        return ['badge'];
    }

    public function render(): View
    {
        return View::make($this->content)
            ->apply(trim($this->part('badge', $this->tone) . ' ' . ($this->styleName() ?? '')))
            ->apply($this->style());
    }
}
