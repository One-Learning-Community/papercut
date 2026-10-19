<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Rendering\Segments;
use Papercut\Value\{Edges, Length};

final class Page extends PdfComponent
{
    private const array SLOTS = ['header', 'footer', 'background'];

    protected function ownAttributes(): array
    {
        return ['size', 'orientation', 'margin', 'header-gap', 'footer-gap'];
    }

    protected function build(array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $location = 'on <x-pdf::page>';
        foreach (array_keys($style) as $key) {
            throw UnknownAttribute::for($key)->within($location);
        }
        if (isset($own['style'])) {
            throw UnknownAttribute::for('style')->within($location);
        }

        $slots = $this->namedSlots($data);
        foreach (array_keys($slots) as $name) {
            if (!in_array($name, self::SLOTS, true)) {
                throw new InvalidStructure("Unknown slot \"$name\" on <x-pdf::page>; allowed slots: " . implode(', ', self::SLOTS));
            }
        }

        $page = self::configured($own);
        $registry = app(NodeRegistry::class);
        foreach (self::SLOTS as $name) {
            if (isset($slots[$name])) {
                $nodes = Segments::nodes($registry->segments($slots[$name]), 'page');
                /** @var list<\Papercut\Node> $nodes */
                $page = $page->{$name}(...$nodes);
            }
        }

        /** @var list<\Papercut\Node> $body */
        $body = $this->children($data);

        return $page->body(...$body);
    }

    /**
     * @param list<\Papercut\Node> $body
     */
    public static function implicit(array $body): \Papercut\Page
    {
        return self::configured([])->body(...$body);
    }

    /** @param array<string, mixed> $own */
    private static function configured(array $own): \Papercut\Page
    {
        $location = 'on <x-pdf::page>';
        try {
            $size = $own['size'] ?? config('papercut.paper', 'a4');
            $page = \Papercut\Page::named(is_string($size) ? $size : 'a4');
        } catch (InvalidValue $e) {
            throw $e->withContext('size')->within($location);
        }

        $orientation = $own['orientation'] ?? 'portrait';
        if ($orientation === 'landscape') {
            $page = $page->landscape();
        } elseif ($orientation !== 'portrait') {
            throw InvalidValue::for('orientation', is_scalar($orientation) ? (string) $orientation : get_debug_type($orientation), 'portrait')
                ->withContext('orientation')->within($location);
        }

        $margin = $own['margin'] ?? config('papercut.margin', '20mm');
        try {
            $page = $page->margin($margin instanceof Edges ? $margin : Edges::parse((string) (is_scalar($margin) ? $margin : '20mm')));
        } catch (InvalidValue $e) {
            throw $e->withContext('margin')->within($location);
        }

        foreach (['header-gap' => 'headerGap', 'footer-gap' => 'footerGap'] as $attr => $method) {
            if (isset($own[$attr])) {
                $gap = $own[$attr];
                try {
                    $page = $page->$method($gap instanceof Length ? $gap : Length::parse((string) (is_scalar($gap) ? $gap : '')));
                } catch (InvalidValue $e) {
                    throw $e->withContext($attr)->within($location);
                }
            }
        }

        return $page;
    }
}
