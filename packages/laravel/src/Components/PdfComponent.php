<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Closure;
use Illuminate\View\Component;
use Illuminate\View\ComponentSlot;
use Papercut\Exception\InvalidValue;
use Papercut\Style\Style;
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
                $style[$key] = $value;
            }
        }

        return ['own' => $own, 'style' => $style];
    }

    /** @return array<string, string> user attribute key => style key (container components only) */
    protected function aliases(): array
    {
        return [];
    }

    /**
     * Applies style attributes and a named style (string) or Style object, tagging errors with the tag.
     *
     * @template T of \Papercut\View|\Papercut\Link|\Papercut\Spacer|\Papercut\Text|\Papercut\PageNumber|\Papercut\Image
     * @param T $node
     * @param array<string, mixed> $style
     * @return T
     */
    protected function applyStyle(object $node, array $style, mixed $styleName = null): object
    {
        $location = 'on <x-pdf::' . $this->tag() . '>';
        $aliases = $this->aliases();
        try {
            $plain = [];
            foreach ($style as $key => $value) {
                if (isset($aliases[$key])) {
                    try {
                        $node = $node->attributes([$aliases[$key] => $value]);
                    } catch (InvalidValue $e) {
                        $re = InvalidValue::for($e->kind, $e->input, $e->suggestion)->withContext($key);
                        throw $e->reason !== null ? $re->because($e->reason) : $re;
                    }
                } else {
                    $plain[$key] = $value;
                }
            }
            if ($plain !== []) {
                $node = $node->attributes($plain);
            }
            if ($styleName instanceof Style || is_string($styleName)) {
                $node = $node->apply($styleName);
            } elseif ($styleName !== null) {
                throw InvalidValue::for('style', is_scalar($styleName) ? (string) $styleName : get_debug_type($styleName))->withContext('style');
            }
        } catch (UnknownAttribute | InvalidValue $e) {
            throw $e->within($location);
        }

        return $node;
    }

    /**
     * @template T of \Papercut\View|\Papercut\Link|\Papercut\Spacer|\Papercut\Text|\Papercut\PageNumber|\Papercut\Image
     * @param T $node
     * @param array<string, mixed> $data
     * @return T
     */
    protected function styled(object $node, array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);

        return $this->applyStyle($node, $style, $own['style'] ?? null);
    }

    /**
     * Like styled(), but maps the own `align` to text-align and `size` to font-size.
     *
     * @template T of \Papercut\Text|\Papercut\PageNumber
     * @param T $node
     * @param array<string, mixed> $data
     * @return T
     */
    protected function textStyled(object $node, array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $mapped = [];
        if (isset($own['align'])) {
            $mapped['text-align'] = $own['align'];
        }
        if (isset($own['size'])) {
            $mapped['font-size'] = $own['size'];
        }

        return $this->applyStyle($node, [...$mapped, ...$style], $own['style'] ?? null);
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
