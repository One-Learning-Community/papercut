@props(['label', 'value', 'note' => null, 'color' => 'slate-900'])
{{-- An app component wrapping the stat decorator: it fixes the grow and colours the value. --}}
<x-doc::stat :label="$label" :note="$note" grow="1"><span color="{{ $color }}">{{ $value }}</span></x-doc::stat>
