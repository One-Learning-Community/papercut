@props(['name', 'desc', 'hours', 'rate'])
<x-pdf::tr border-width="0 0 0.5pt 0" border-color="slate-200">
    <x-pdf::td>
        <x-pdf::column gap="1pt">
            <x-pdf::text bold>{{ $name }}</x-pdf::text>
            <x-pdf::text size="8.5pt" color="slate-500">{{ $desc }}</x-pdf::text>
        </x-pdf::column>
    </x-pdf::td>
    <x-pdf::td align="right">{{ $hours }}</x-pdf::td>
    <x-pdf::td align="right">${{ number_format($rate, 2) }}</x-pdf::td>
    <x-pdf::td align="right">${{ number_format($hours * $rate, 2) }}</x-pdf::td>
</x-pdf::tr>
