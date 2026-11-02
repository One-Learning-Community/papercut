// Example inputs for the Tailwind translator page.
export const samples = [
  {
    name: 'Profile card',
    source: `<div class="flex flex-col gap-3 p-4 bg-slate-50 rounded-lg break-inside-avoid">
  <h2 class="text-xl font-bold text-slate-900">Ada Lovelace</h2>
  <p class="text-sm text-slate-600">Analyst, <em>Analytical Engine</em> project</p>
  <div class="flex gap-2 mt-2">
    <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 text-xs font-medium">Mathematics</span>
    <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 text-xs font-medium">Notes</span>
  </div>
</div>`,
  },
  {
    name: 'Invoice header',
    source: `<header class="flex justify-between items-start pb-6 mb-6 border-b border-slate-200">
  <div>
    <h1 class="text-3xl font-semibold tracking-tight">Invoice</h1>
    <p class="text-slate-500">#{{ $invoice->number }}</p>
  </div>
  <div class="text-right text-sm leading-relaxed">
    <p class="font-bold">Example Studio Ltd</p>
    <p>12 High Street<br>Springfield</p>
  </div>
</header>`,
  },
  {
    name: 'Table with a loop',
    source: `<table class="w-full text-sm">
  <thead class="bg-gray-100">
    <tr>
      <th class="px-3 py-2 text-left">Item</th>
      <th class="px-3 py-2 text-right">Amount</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($lines as $line)
    <tr class="border-b">
      <td class="px-3 py-2">{{ $line->name }}</td>
      <td class="px-3 py-2 text-right">{{ $line->amount }}</td>
    </tr>
    @endforeach
  </tbody>
</table>`,
  },
  {
    name: 'Columns',
    source: `<div class="columns-2 gap-x-8 divide-x">
  <h3 class="text-lg font-bold">Notes from the field</h3>
  <p>The first survey went out in March and the answers came back within a week.</p>
  <p>Readers asked for shorter sections and clearer headings.</p>
</div>`,
  },
  {
    name: 'Web-only classes',
    source: `<nav class="hidden md:flex print:hidden gap-4">Menu</nav>
<section class="grid grid-cols-2 gap-6 p-6 md:p-10 shadow-lg hover:shadow-xl">
  <div class="rounded-full w-1/2 mx-auto">
    <img src="logo.png" class="w-24 h-24 object-cover">
  </div>
  <ul class="list-disc pl-5 space-y-1">
    <li>First <strong class="text-red-600 bg-red-50">point</strong></li>
    <li>Second point</li>
  </ul>
</section>`,
  },
  {
    name: 'Classes on Papercut tags',
    source: `<x-doc::column class="gap-3 p-4 bg-slate-50 rounded-lg keep-together">
    <x-doc::text class="text-xl font-bold text-slate-900">Ada Lovelace</x-doc::text>
    <x-doc::row class="gap-2 mt-2" justify="end">
        <x-doc::badge type="tip">Notes</x-doc::badge>
    </x-doc::row>
</x-doc::column>`,
  },
]
