@props(['label', 'value', 'note' => null, 'color' => 'slate-900'])
<x-pdf::column gap="3pt" padding="14pt" background="slate-50" radius="8pt" grow="1">
    <x-pdf::text size="8pt" bold color="slate-500">{{ $label }}</x-pdf::text>
    <x-pdf::text size="22pt" bold :color="$color">{{ $value }}</x-pdf::text>
    @if ($note)
        <x-pdf::text size="9pt" color="slate-600">{{ $note }}</x-pdf::text>
    @endif
</x-pdf::column>
