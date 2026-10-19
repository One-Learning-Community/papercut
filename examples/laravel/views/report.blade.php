<x-pdf::document title="Designing Papercut" author="Papercut">
    <x-pdf::page size="a4" margin="22mm">
        <x-slot:header>
            <x-pdf::row>
                <x-pdf::text size="8pt" color="slate-500">Papercut Design Report</x-pdf::text>
                <x-pdf::spacer />
                <x-pdf::text size="8pt" color="slate-500">Confidential</x-pdf::text>
            </x-pdf::row>
        </x-slot:header>

        <x-pdf::column>
            <x-pdf::text size="32pt" bold color="slate-900">Designing Papercut</x-pdf::text>
            <x-pdf::text size="13pt" color="slate-500">A short report on layout, pagination, and deterministic output</x-pdf::text>

            <x-pdf::row gap="8pt" margin="10pt 0 10pt 0">
                <x-stat-card label="SECTIONS" value="4" note="plus an appendix" color="indigo-600" />
                <x-stat-card label="TABLE ROWS" value="{{ count($scenarios) }}" note="split across pages, header repeated" color="emerald-600" />
            </x-pdf::row>

            <x-pdf::h1>1. Overview</x-pdf::h1>
            @foreach ($prose as $i => $paragraph)
                @if ($i === 3)
                    <x-pdf::h2 margin="10pt 0 0 0">2. Text and pagination</x-pdf::h2>
                @elseif ($i === 6)
                    <x-pdf::h2 margin="10pt 0 0 0">3. Backend and failure modes</x-pdf::h2>
                @endif
                <x-pdf::p align="justify">{{ $paragraph }}</x-pdf::p>
            @endforeach

            <x-pdf::text margin="4pt 0 8pt 0">Rule of thumb: <b>measure first</b>, then <i>place</i>, and only then paint.</x-pdf::text>

            <x-pdf::h2 margin="10pt 0 0 0">4. Sample table</x-pdf::h2>
            <x-pdf::text size="9pt" color="slate-500" margin="0 0 6pt 0">Illustrative data generated for this example; not measurements.</x-pdf::text>
            <x-pdf::table columns="16mm 1fr 28mm 28mm">
                <x-pdf::thead>
                    <x-pdf::tr background="slate-100">
                        <x-pdf::th>#</x-pdf::th>
                        <x-pdf::th>Scenario</x-pdf::th>
                        <x-pdf::th align="right">Pages</x-pdf::th>
                        <x-pdf::th align="right">Rows</x-pdf::th>
                    </x-pdf::tr>
                </x-pdf::thead>
                @foreach ($scenarios as [$n, $name, $pages, $rows])
                    <x-pdf::tr border-width="0 0 0.5pt 0" border-color="slate-200">
                        <x-pdf::td><x-pdf::text color="slate-500">{{ $n }}</x-pdf::text></x-pdf::td>
                        <x-pdf::td>{{ $name }}</x-pdf::td>
                        <x-pdf::td align="right">{{ $pages }}</x-pdf::td>
                        <x-pdf::td align="right">{{ $rows }}</x-pdf::td>
                    </x-pdf::tr>
                @endforeach
            </x-pdf::table>

            <x-pdf::page-break />

            <x-pdf::h1>Appendix A: Glossary</x-pdf::h1>
            @foreach ($glossary as [$term, $definition])
                <x-pdf::text margin="0 0 6pt 0"><b>{{ $term }}</b>: {{ $definition }}</x-pdf::text>
            @endforeach
        </x-pdf::column>

        <x-slot:footer>
            <x-pdf::page-number format="{page} / {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-pdf::page>
</x-pdf::document>
