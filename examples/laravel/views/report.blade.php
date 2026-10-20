<x-doc::document title="Designing Papercut" author="Papercut">
    <x-doc::page size="a4" margin="22mm">
        <x-slot:header>
            <x-doc::row>
                <x-doc::text size="8pt" color="slate-500">Papercut Design Report</x-doc::text>
                <x-doc::spacer />
                <x-doc::text size="8pt" color="slate-500">Confidential</x-doc::text>
            </x-doc::row>
        </x-slot:header>

        <x-doc::column>
            <x-doc::text size="32pt" bold color="slate-900">Designing Papercut</x-doc::text>
            <x-doc::text size="13pt" color="slate-500">A short report on layout, pagination, and deterministic output</x-doc::text>

            <x-doc::row gap="8pt" margin="10pt 0 10pt 0">
                <x-stat-card label="SECTIONS" value="4" note="plus an appendix" color="indigo-600" />
                <x-stat-card label="TABLE ROWS" value="{{ count($scenarios) }}" note="split across pages, header repeated" color="emerald-600" />
            </x-doc::row>

            <x-doc::h1>1. Overview</x-doc::h1>
            @foreach ($prose as $i => $paragraph)
                @if ($i === 3)
                    <x-doc::h2 margin="10pt 0 0 0">2. Text and pagination</x-doc::h2>
                @elseif ($i === 6)
                    <x-doc::h2 margin="10pt 0 0 0">3. Backend and failure modes</x-doc::h2>
                @endif
                <x-doc::p align="justify">{{ $paragraph }}</x-doc::p>
            @endforeach

            <x-doc::text margin="4pt 0 8pt 0">Rule of thumb: <b>measure first</b>, then <i>place</i>, and only then paint.</x-doc::text>

            <x-doc::h2 margin="10pt 0 0 0">4. Sample table</x-doc::h2>
            <x-doc::text size="9pt" color="slate-500" margin="0 0 6pt 0">Illustrative data generated for this example; not measurements.</x-doc::text>
            <x-doc::table columns="16mm 1fr 28mm 28mm">
                <x-doc::thead>
                    <x-doc::tr background="slate-100">
                        <x-doc::th>#</x-doc::th>
                        <x-doc::th>Scenario</x-doc::th>
                        <x-doc::th align="right">Pages</x-doc::th>
                        <x-doc::th align="right">Rows</x-doc::th>
                    </x-doc::tr>
                </x-doc::thead>
                @foreach ($scenarios as [$n, $name, $pages, $rows])
                    <x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
                        <x-doc::td><x-doc::text color="slate-500">{{ $n }}</x-doc::text></x-doc::td>
                        <x-doc::td>{{ $name }}</x-doc::td>
                        <x-doc::td align="right">{{ $pages }}</x-doc::td>
                        <x-doc::td align="right">{{ $rows }}</x-doc::td>
                    </x-doc::tr>
                @endforeach
            </x-doc::table>

            <x-doc::page-break />

            <x-doc::h1>Appendix A: Glossary</x-doc::h1>
            @foreach ($glossary as [$term, $definition])
                <x-doc::text margin="0 0 6pt 0"><b>{{ $term }}</b>: {{ $definition }}</x-doc::text>
            @endforeach
        </x-doc::column>

        <x-slot:footer>
            <x-doc::page-number format="{page} / {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-doc::page>
</x-doc::document>
