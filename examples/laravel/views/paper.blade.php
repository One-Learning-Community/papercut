{{-- App-side content: an academic paper. The title block and abstract run full width, the body flows through two columns, and a closing note runs full width again. --}}
<x-doc::document title="Wider Hive Spacing Improves Winter Survival of Honey Bee Colonies" author="Marta Lindqvist, Tomas Okafor, Reiko Havel">
    <x-doc::page size="a4" margin="18mm">
        <x-slot:header>
            <x-doc::row padding="0 0 4pt 0" border-width="0 0 0.5pt 0" border-color="slate-200">
                <x-doc::text size="8pt" color="slate-500">Hive Spacing and Winter Survival</x-doc::text>
                <x-doc::spacer />
                <x-doc::text size="8pt" color="slate-500">Preprint</x-doc::text>
            </x-doc::row>
        </x-slot:header>

        <x-doc::column gap="12pt" margin="0 0 14pt 0">
            <x-doc::h1 align="center">Wider Hive Spacing Improves Winter Survival of Honey Bee Colonies</x-doc::h1>
            <x-doc::row gap="14pt">
                @foreach ($authors as [$name, $affiliation])
                    <x-doc::column gap="1pt" grow="1" basis="0pt">
                        <x-doc::text bold>{{ $name }}</x-doc::text>
                        <x-doc::text size="8.5pt" color="slate-600">{{ $affiliation }}</x-doc::text>
                    </x-doc::column>
                @endforeach
            </x-doc::row>
            <x-doc::column gap="3pt" padding="8pt" background="slate-50">
                <x-doc::text bold>Abstract</x-doc::text>
                <x-doc::p align="justify">{{ $abstract }}</x-doc::p>
            </x-doc::column>
        </x-doc::column>

        <x-doc::column-flow columns="2" column-gap="6mm" rule>
            @foreach ($sections as $i => [$title, $paragraphs])
                <x-doc::h3 margin="{{ $i === 0 ? '0' : '8pt 0 0 0' }}">{{ $title }}</x-doc::h3>
                @foreach ($paragraphs as $paragraph)
                    @if ($paragraph === '@logit')
                        <x-doc::p align="justify">We compared survival with a logistic model that included site and year, and the weight lost with a linear model. For a colony at spacing <x-doc::math math="d" />, the model gives the log odds of survival as</x-doc::p>
                        <x-doc::equation :math="$logit" text-align="center" margin="2pt 0 6pt 0" />
                    @elseif ($paragraph === '@weight-loss')
                        <x-doc::p align="justify">The weight lost over winter fell with spacing too: the fitted slope was <x-doc::math :math="$slope" /> kg per cm, or about 0.4 kg for each further 10 cm. Colonies at wide spacing also began spring with more frames of bees. Figure 1 plots survival against spacing for both years together.</x-doc::p>
                    @else
                        <x-doc::p align="justify">{{ $paragraph }}</x-doc::p>
                    @endif
                @endforeach
                @if ($title === '3. Results')
                    <x-doc::column gap="4pt" margin="4pt 0 8pt 0" keep-together>
                        <x-doc::text size="8.5pt" color="slate-600" bold>Table 1. Survival to spring, by spacing between hives.</x-doc::text>
                        <x-doc::table columns="1fr 1fr 1fr">
                            <x-doc::thead>
                                <x-doc::tr background="slate-100">
                                    <x-doc::th>Spacing</x-doc::th>
                                    <x-doc::th>Colonies</x-doc::th>
                                    <x-doc::th>Survived</x-doc::th>
                                </x-doc::tr>
                            </x-doc::thead>
                            @foreach ($survival as $row)
                                <x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
                                    @foreach ($row as $cell)
                                        <x-doc::td>{{ $cell }}</x-doc::td>
                                    @endforeach
                                </x-doc::tr>
                            @endforeach
                        </x-doc::table>
                    </x-doc::column>
                    <x-doc::column gap="4pt" margin="4pt 0 8pt 0" keep-together>
                        <x-doc::svg src="survival-chart.svg" width="80mm" />
                        <x-doc::text size="8.5pt" color="slate-600" bold>Figure 1. Colonies alive in spring at 10, 20, 30 and 40 cm (left to right); the gridlines mark 25, 50, 75 and 100 percent.</x-doc::text>
                    </x-doc::column>
                @endif
            @endforeach
        </x-doc::column-flow>

        <x-doc::column gap="2pt" margin="14pt 0 0 0" padding="6pt 0 0 0" border-width="0.75pt 0 0 0" border-color="slate-300">
            <x-doc::text bold>Data availability</x-doc::text>
            <x-doc::p size="9pt" color="slate-600">The colony records behind Table 1 and Figure 1 are invented for this example and are not measurements.</x-doc::p>
        </x-doc::column>

        <x-slot:footer>
            <x-doc::page-number format="Page {page} of {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-doc::page>
</x-doc::document>
