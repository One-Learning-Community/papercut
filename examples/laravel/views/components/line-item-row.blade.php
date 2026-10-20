@props(['name', 'desc', 'hours', 'rate'])
<x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
    <x-doc::td>
        <x-doc::column gap="1pt">
            <x-doc::text bold>{{ $name }}</x-doc::text>
            <x-doc::text size="8.5pt" color="slate-500">{{ $desc }}</x-doc::text>
        </x-doc::column>
    </x-doc::td>
    <x-doc::td align="right">{{ $hours }}</x-doc::td>
    <x-doc::td align="right">${{ number_format($rate, 2) }}</x-doc::td>
    <x-doc::td align="right">${{ number_format($hours * $rate, 2) }}</x-doc::td>
</x-doc::tr>
