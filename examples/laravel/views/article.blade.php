{{-- App-side content: a knowledge base article. $html is the editor's HTML; <x-doc::markup> converts it. --}}
<x-doc::document title="Setting up two-factor sign-in" author="Plotline">
    <x-doc::page size="a4" margin="20mm">
        <x-slot:header>
            <x-doc::row padding="0 0 6pt 0" border-width="0 0 0.75pt 0" border-color="slate-200">
                <x-doc::text size="11pt" bold color="indigo-600">Plotline Help Center</x-doc::text>
                <x-doc::spacer />
                <x-doc::text size="9pt" color="slate-500">Account and security</x-doc::text>
            </x-doc::row>
        </x-slot:header>

        <x-doc::column gap="12pt">
            <x-doc::column gap="4pt">
                <x-doc::h1>Setting up two-factor sign-in</x-doc::h1>
                <x-doc::text size="9pt" color="slate-500">Updated 12 March 2026 · 4 min read</x-doc::text>
            </x-doc::column>
            <x-doc::markup :html="$html" />
        </x-doc::column>

        <x-slot:footer>
            <x-doc::page-number format="Page {page} of {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-doc::page>
</x-doc::document>
