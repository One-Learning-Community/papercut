@props(['label', 'value', 'note' => null, 'color' => 'slate-900'])
<x-doc::column gap="3pt" padding="14pt" background="slate-50" radius="8pt" grow="1">
    <x-doc::text size="8pt" bold color="slate-500">{{ $label }}</x-doc::text>
    <x-doc::text size="22pt" bold :color="$color">{{ $value }}</x-doc::text>
    @if ($note)
        <x-doc::text size="9pt" color="slate-600">{{ $note }}</x-doc::text>
    @endif
</x-doc::column>
