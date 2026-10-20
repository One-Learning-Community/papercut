<x-doc::document title="Invoice INV-2026-0142" author="Northwind Studio">
    <x-doc::page size="a4" margin="20mm">
        <x-doc::column gap="20pt">
            {{-- Letterhead --}}
            <x-doc::row padding="0 0 14pt 0" border-width="0 0 1.5pt 0" border-color="indigo-600">
                <x-doc::column gap="2pt">
                    <x-doc::text size="16pt" bold color="indigo-600">Northwind Studio</x-doc::text>
                    <x-doc::text color="slate-500">48 Harbor Street, Suite 210</x-doc::text>
                    <x-doc::text color="slate-500">Portland, OR 97204</x-doc::text>
                    <x-doc::text color="slate-500">billing@northwind.example</x-doc::text>
                </x-doc::column>
                <x-doc::spacer />
                <x-doc::column gap="2pt">
                    <x-doc::text size="26pt" bold color="slate-900" align="right">INVOICE</x-doc::text>
                    <x-doc::text color="slate-600" align="right">No. INV-2026-0142</x-doc::text>
                    <x-doc::text color="slate-600" align="right">Issued 2026-03-02 / Due 2026-04-01</x-doc::text>
                </x-doc::column>
            </x-doc::row>

            {{-- Bill to / project --}}
            <x-doc::row gap="16pt" padding="12pt" background="slate-50" radius="6pt">
                <x-doc::column gap="2pt" grow="1">
                    <x-doc::text size="8pt" bold color="slate-500" letter-spacing="0.6pt">BILL TO</x-doc::text>
                    <x-doc::text bold>Marlowe &amp; Finch Publishing</x-doc::text>
                    <x-doc::text>Attn: Accounts Payable</x-doc::text>
                    <x-doc::text>900 Meridian Avenue</x-doc::text>
                    <x-doc::text>Seattle, WA 98101</x-doc::text>
                </x-doc::column>
                <x-doc::column gap="2pt" grow="1">
                    <x-doc::text size="8pt" bold color="slate-500" letter-spacing="0.6pt">PROJECT</x-doc::text>
                    <x-doc::text>Report Platform Rebuild</x-doc::text>
                    <x-doc::text size="8pt" bold color="slate-500" letter-spacing="0.6pt" margin="6pt 0 0 0">PO NUMBER</x-doc::text>
                    <x-doc::text>PO-77310</x-doc::text>
                </x-doc::column>
            </x-doc::row>

            {{-- Line items --}}
            <x-doc::table columns="1fr 20mm 25mm 25mm">
                <x-doc::thead>
                    <x-doc::tr background="slate-100">
                        <x-doc::th font-size="9pt">Description</x-doc::th>
                        <x-doc::th align="right" font-size="9pt">Hours</x-doc::th>
                        <x-doc::th align="right" font-size="9pt">Rate</x-doc::th>
                        <x-doc::th align="right" font-size="9pt">Amount</x-doc::th>
                    </x-doc::tr>
                </x-doc::thead>
                @foreach ($items as [$name, $desc, $hours, $rate])
                    <x-line-item-row :name="$name" :desc="$desc" :hours="$hours" :rate="$rate" />
                @endforeach
            </x-doc::table>

            {{-- Totals --}}
            <x-doc::row>
                <x-doc::spacer />
                <x-doc::column gap="4pt" width="70mm">
                    <x-doc::row>
                        <x-doc::text color="slate-600">Subtotal</x-doc::text>
                        <x-doc::spacer />
                        <x-doc::text align="right">${{ number_format($subtotal, 2) }}</x-doc::text>
                    </x-doc::row>
                    <x-doc::row>
                        <x-doc::text color="slate-600">Tax (8.25%)</x-doc::text>
                        <x-doc::spacer />
                        <x-doc::text align="right">${{ number_format($tax, 2) }}</x-doc::text>
                    </x-doc::row>
                    <x-doc::row margin="4pt 0 0 0" padding="6pt 0 0 0" border-width="1pt 0 0 0" border-color="slate-900">
                        <x-doc::text size="12pt" bold color="slate-900">Total due</x-doc::text>
                        <x-doc::spacer />
                        <x-doc::text size="12pt" bold align="right">${{ number_format($total, 2) }}</x-doc::text>
                    </x-doc::row>
                </x-doc::column>
            </x-doc::row>
        </x-doc::column>

        <x-slot:footer>
            <x-doc::column padding="8pt 0 0 0" border-width="0.5pt 0 0 0" border-color="slate-200">
                <x-doc::row gap="12pt" justify="space-between">
                    <x-doc::text size="8pt" color="slate-500" grow="1">Payment terms: net 30. Wire or ACH to Northwind Studio, account ending 4417.</x-doc::text>
                    <x-doc::page-number format="Page {page} of {total}" size="8pt" color="slate-500" align="right" />
                </x-doc::row>
            </x-doc::column>
        </x-slot:footer>
    </x-doc::page>
</x-doc::document>
