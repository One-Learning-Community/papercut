{{-- App-side content: a certificate filled in on top of a blank PDF. papercut.image_path points at examples/assets. --}}
<x-doc::document title="Certificate of Completion" author="Hivewise Academy">
    <x-doc::page template="certificate-template.pdf">
        <x-slot:overlay>
            <x-doc::place x="5.5in" y="4.15in" anchor="bottom">
                <x-doc::text size="34pt" bold color="slate-900">{{ $name }}</x-doc::text>
            </x-doc::place>
            <x-doc::place x="5.5in" y="6.03in" anchor="bottom">
                <x-doc::text size="22pt" bold color="indigo-600">{{ $course }}</x-doc::text>
            </x-doc::place>
            <x-doc::place x="3.3in" y="6.91in" anchor="bottom">
                <x-doc::text size="13pt" color="slate-900">{{ $date }}</x-doc::text>
            </x-doc::place>
            <x-doc::place x="7.4in" y="7.02in" anchor="bottom">
                <x-doc::svg src="signature.svg" width="2.2in" />
            </x-doc::place>
        </x-slot:overlay>
    </x-doc::page>
</x-doc::document>
