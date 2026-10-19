<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Closure;
use Illuminate\View\Component;
use Illuminate\View\ComponentSlot;
use Papercut\Exception\InvalidValue;
use Papercut\Exception\UnknownAttribute;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Rendering\Segments;

abstract class PdfComponent extends Component
{
    final public function render(): string
    {
        return '';
    }

    final public function resolveView(): Closure
    {
        /** @param array<string, mixed> $data */
        return fn (array $data) => app(NodeRegistry::class)->register($this->build($data));
    }

    /** @param array<string, mixed> $data */
    abstract protected function build(array $data): object;

    protected function tag(): string
    {
        $short = substr((string) strrchr('\\' . static::class, '\\'), 1);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }

    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{own: array<string, mixed>, style: array<string, mixed>}
     */
    protected function attrs(array $data): array
    {
        $bag = $data['attributes'] ?? null;
        $all = $bag instanceof \Illuminate\View\ComponentAttributeBag ? $bag->getAttributes() : [];
        $own = [];
        $style = [];
        foreach ($all as $key => $value) {
            $key = (string) $key;
            if ($key === 'style' || in_array($key, $this->ownAttributes(), true)) {
                $own[$key] = $value;
            } else {
                $style[$key === 'align' ? 'align-items' : $key] = $value;
            }
        }

        return ['own' => $own, 'style' => $style];
    }

    /**
     * @template T of \Papercut\View|\Papercut\Link|\Papercut\Spacer
     * @param T $node
     * @param array<string, mixed> $data
     * @return T
     */
    protected function styled(object $node, array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $location = 'on <x-pdf::' . $this->tag() . '>';
        try {
            if ($style !== []) {
                $node = $node->attributes($style);
            }
            if (isset($own['style']) && is_string($own['style'])) {
                $node = $node->apply($own['style']);
            }
        } catch (UnknownAttribute | InvalidValue $e) {
            throw $e->within($location);
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<\Papercut\Node>
     */
    protected function children(array $data): array
    {
        /** @var list<\Papercut\Node> $nodes */
        $nodes = Segments::nodes(app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')), $this->tag());

        return $nodes;
    }

    /** @param array<string, mixed> $data */
    protected function text(array $data): string
    {
        return Segments::text(app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')), $this->tag());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected function namedSlots(array $data): array
    {
        $out = [];
        foreach ($data as $name => $value) {
            if ($name !== 'slot' && $value instanceof ComponentSlot) {
                $out[(string) $name] = (string) $value;
            }
        }

        return $out;
    }
}
