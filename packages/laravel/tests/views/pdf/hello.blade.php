<x-pdf::document title="Hello">
    <x-pdf::page>
        <x-slot:header><x-pdf::text>Header</x-pdf::text></x-slot:header>
        <x-slot:footer><x-pdf::page-number /></x-slot:footer>
        <x-pdf::text>Hello {{ $name }}</x-pdf::text>
    </x-pdf::page>
</x-pdf::document>
