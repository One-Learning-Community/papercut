<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Decorator\ChoiceList;
use Papercut\Decorator\ChoiceOption;
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
final class Choices extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['type', 'emphasize-selected', 'selected-text', 'unselected-text', 'variant'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $where = 'on ' . Tag::of('choices');
        $type = isset($own['type']) ? $this->scalarOwn($own['type'], 'type') : 'checkbox';
        $list = match ($type) {
            'radio' => ChoiceList::radio(),
            'checkbox' => ChoiceList::checkbox(),
            default => throw InvalidValue::for('type', $type)->because('expected one of radio, checkbox')->withContext('type')->within($where),
        };
        $options = [];
        foreach ($this->rawChildren($data, [ChoiceOption::class]) as $child) {
            if (!$child instanceof ChoiceOption) {
                throw new InvalidStructure(Tag::of('choices') . ' can only contain ' . Tag::of('choice') . NodeRegistry::staleHint($child));
            }
            $options[] = $child;
        }
        $list = $list->add(...$options);
        if (array_key_exists('emphasize-selected', $own)) {
            $list = $list->emphasizeSelected($this->boolOwn($own['emphasize-selected'], 'emphasize-selected'));
        }
        $list = $list->stateText(
            isset($own['selected-text']) ? $this->scalarOwn($own['selected-text'], 'selected-text') : self::translated('selected'),
            isset($own['unselected-text']) ? $this->scalarOwn($own['unselected-text'], 'unselected-text') : self::translated('unselected'),
        );

        return $this->styled($list, $data);
    }

    private static function translated(string $key): string
    {
        $text = __('papercut::choices.' . $key);

        return is_string($text) ? $text : $key;
    }
}
