{{-- App-side content: a feedback-form response record built from the decorator tags. --}}
<x-doc::document title="Feedback form response" author="Northwind Studio">
    <x-doc::page size="a4" margin="20mm">
        <x-doc::column gap="14pt">
            <x-doc::h2>Workshop feedback</x-doc::h2>

            <x-doc::key-value label-width="30%">
                <x-doc::kv label="Respondent">{{ $respondent }}</x-doc::kv>
                <x-doc::kv label="Submitted">{{ $submitted }}</x-doc::kv>
                <x-doc::kv label="Course">{{ $course }}</x-doc::kv>
            </x-doc::key-value>

            @php($n = 0)
            @foreach ($items as $item)
                @if ($item['type'] === 'instruction')
                    <x-doc::callout type="note" :title="$item['prompt']">{{ $item['text'] }}</x-doc::callout>
                    @continue
                @endif
                @php($n++)
                <x-doc::section :number="$n" :title="$item['prompt']" rule keep-together>
                    @switch($item['type'])
                        @case('text')
                            <x-doc::field placeholder="No response" lines="2">{{ $item['answer'] }}</x-doc::field>
                            @break
                        @case('choices')
                            <x-doc::choices :type="$item['multiple'] ? 'checkbox' : 'radio'" emphasize-selected>
                                @foreach ($item['options'] as $option)
                                    <x-doc::choice :selected="in_array($option, $item['selected'], true)">{{ $option }}</x-doc::choice>
                                @endforeach
                            </x-doc::choices>
                            @break
                        @case('ratings')
                            <x-doc::key-value label-width="60%">
                                @foreach ($item['rows'] as [$statement, $rating, $tone])
                                    <x-doc::kv :label="$statement"><x-doc::badge :type="$tone">{{ $rating }}</x-doc::badge></x-doc::kv>
                                @endforeach
                            </x-doc::key-value>
                            @break
                    @endswitch
                </x-doc::section>
            @endforeach
        </x-doc::column>
    </x-doc::page>
</x-doc::document>
