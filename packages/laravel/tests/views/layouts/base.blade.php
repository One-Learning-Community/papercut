<x-pdf::document>
<x-pdf::page>
<x-pdf::text>@yield('title')</x-pdf::text>
@yield('content')
</x-pdf::page>
</x-pdf::document>
@stack('after')
