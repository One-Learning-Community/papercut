<x-pdf::document title="Official Transcript" author="One Learning Community">
    <x-pdf::page size="a4" margin="20mm">
        <x-slot:header>
            <x-pdf::row>
                <x-pdf::text size="16pt" bold color="indigo-600">Official Transcript</x-pdf::text>
                <x-pdf::spacer />
                <x-pdf::text color="slate-500">One Learning Community</x-pdf::text>
            </x-pdf::row>
        </x-slot:header>

        <x-pdf::column gap="10pt">
            <x-pdf::column gap="3pt" padding="12pt" background="slate-50" radius="8pt" keep-together>
                <x-pdf::text size="16pt" bold>{{ $student }}</x-pdf::text>
                <x-pdf::text color="slate-600">Student ID 2019-04417  |  B.Sc. Computer Science  |  Enrolled 2019</x-pdf::text>
                <x-pdf::row gap="8pt" margin="8pt 0 0 0">
                    @foreach ([['CREDITS', (string) $credits], ['GPA', number_format($gpa, 2)], ['STANDING', 'Good'], ['COURSES', (string) count($courses)]] as [$label, $value])
                        <x-pdf::column gap="2pt" grow="1">
                            <x-pdf::text size="8pt" bold color="slate-500">{{ $label }}</x-pdf::text>
                            <x-pdf::text size="13pt" bold>{{ $value }}</x-pdf::text>
                        </x-pdf::column>
                    @endforeach
                </x-pdf::row>
            </x-pdf::column>

            <x-pdf::h2>Course history</x-pdf::h2>

            <x-pdf::table columns="22mm 1fr 24mm 18mm 18mm">
                <x-pdf::thead>
                    <x-pdf::tr background="slate-100">
                        <x-pdf::th>Code</x-pdf::th>
                        <x-pdf::th>Course</x-pdf::th>
                        <x-pdf::th>Term</x-pdf::th>
                        <x-pdf::th align="right">Credits</x-pdf::th>
                        <x-pdf::th align="right">Grade</x-pdf::th>
                    </x-pdf::tr>
                </x-pdf::thead>
                @foreach ($courses as [$code, $title, $term, $cr, $grade])
                    <x-pdf::tr border-width="0 0 0.5pt 0" border-color="slate-200">
                        <x-pdf::td><x-pdf::text color="slate-500">{{ $code }}</x-pdf::text></x-pdf::td>
                        <x-pdf::td>{{ $title }}</x-pdf::td>
                        <x-pdf::td><x-pdf::text color="slate-600">{{ $term }}</x-pdf::text></x-pdf::td>
                        <x-pdf::td align="right">{{ $cr }}</x-pdf::td>
                        <x-pdf::td align="right"><x-pdf::text bold>{{ $grade }}</x-pdf::text></x-pdf::td>
                    </x-pdf::tr>
                @endforeach
            </x-pdf::table>

            <x-pdf::column gap="4pt" padding="12pt" background="slate-50" radius="8pt" keep-together>
                <x-pdf::h3 keep-with-next>Cumulative summary</x-pdf::h3>
                <x-pdf::row>
                    <x-pdf::text grow="1">{{ sprintf('%d credits attempted, %.1f quality points.', $credits, $points) }}</x-pdf::text>
                    <x-pdf::text size="14pt" bold>GPA {{ number_format($gpa, 2) }}</x-pdf::text>
                </x-pdf::row>
            </x-pdf::column>
        </x-pdf::column>

        <x-slot:footer>
            <x-pdf::page-number format="Page {page} of {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-pdf::page>
</x-pdf::document>
