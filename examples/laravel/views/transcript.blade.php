<x-doc::document title="Official Transcript" author="One Learning Community">
    <x-doc::page size="a4" margin="20mm">
        <x-slot:header>
            <x-doc::row>
                <x-doc::text size="16pt" bold color="indigo-600">Official Transcript</x-doc::text>
                <x-doc::spacer />
                <x-doc::text color="slate-500">One Learning Community</x-doc::text>
            </x-doc::row>
        </x-slot:header>

        <x-doc::column gap="10pt">
            <x-doc::column gap="3pt" padding="12pt" background="slate-50" radius="8pt" keep-together>
                <x-doc::text size="16pt" bold>{{ $student }}</x-doc::text>
                <x-doc::text color="slate-600">Student ID 2019-04417  |  B.Sc. Computer Science  |  Enrolled 2019</x-doc::text>
                <x-doc::row gap="8pt" margin="8pt 0 0 0">
                    @foreach ([['CREDITS', (string) $credits], ['GPA', number_format($gpa, 2)], ['STANDING', 'Good'], ['COURSES', (string) count($courses)]] as [$label, $value])
                        <x-doc::column gap="2pt" grow="1">
                            <x-doc::text size="8pt" bold color="slate-500">{{ $label }}</x-doc::text>
                            <x-doc::text size="13pt" bold>{{ $value }}</x-doc::text>
                        </x-doc::column>
                    @endforeach
                </x-doc::row>
            </x-doc::column>

            <x-doc::h2>Course history</x-doc::h2>

            <x-doc::table columns="22mm 1fr 24mm 18mm 18mm">
                <x-doc::thead>
                    <x-doc::tr background="slate-100">
                        <x-doc::th>Code</x-doc::th>
                        <x-doc::th>Course</x-doc::th>
                        <x-doc::th>Term</x-doc::th>
                        <x-doc::th align="right">Credits</x-doc::th>
                        <x-doc::th align="right">Grade</x-doc::th>
                    </x-doc::tr>
                </x-doc::thead>
                @foreach ($courses as [$code, $title, $term, $cr, $grade])
                    <x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
                        <x-doc::td><x-doc::text color="slate-500">{{ $code }}</x-doc::text></x-doc::td>
                        <x-doc::td>{{ $title }}</x-doc::td>
                        <x-doc::td><x-doc::text color="slate-600">{{ $term }}</x-doc::text></x-doc::td>
                        <x-doc::td align="right">{{ $cr }}</x-doc::td>
                        <x-doc::td align="right"><x-doc::text bold>{{ $grade }}</x-doc::text></x-doc::td>
                    </x-doc::tr>
                @endforeach
            </x-doc::table>

            <x-doc::column gap="4pt" padding="12pt" background="slate-50" radius="8pt" keep-together>
                <x-doc::h3 keep-with-next>Cumulative summary</x-doc::h3>
                <x-doc::row>
                    <x-doc::text grow="1">{{ sprintf('%d credits attempted, %.1f quality points.', $credits, $points) }}</x-doc::text>
                    <x-doc::text size="14pt" bold>GPA {{ number_format($gpa, 2) }}</x-doc::text>
                </x-doc::row>
            </x-doc::column>
        </x-doc::column>

        <x-slot:footer>
            <x-doc::page-number format="Page {page} of {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-doc::page>
</x-doc::document>
