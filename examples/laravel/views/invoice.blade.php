<x-pdf::document title="Invoice INV-2026-0142" author="Northwind Studio">
    <x-pdf::page size="a4" margin="20mm">
        <x-pdf::column gap="20pt">
            {{-- Letterhead --}}
            <x-pdf::row padding="0 0 14pt 0" border-width="0 0 1.5pt 0" border-color="indigo-600">
                <x-pdf::column gap="2pt">
                    <x-pdf::text size="16pt" bold color="indigo-600">Northwind Studio</x-pdf::text>
                    <x-pdf::text color="slate-500">48 Harbor Street, Suite 210</x-pdf::text>
                    <x-pdf::text color="slate-500">Portland, OR 97204</x-pdf::text>
                    <x-pdf::text color="slate-500">billing@northwind.example</x-pdf::text>
                </x-pdf::column>
                <x-pdf::spacer />
                <x-pdf::column gap="2pt">
                    <x-pdf::text size="26pt" bold color="slate-900" align="right">INVOICE</x-pdf::text>
                    <x-pdf::text color="slate-600" align="right">No. INV-2026-0142</x-pdf::text>
                    <x-pdf::text color="slate-600" align="right">Issued 2026-03-02 / Due 2026-04-01</x-pdf::text>
                </x-pdf::column>
            </x-pdf::row>

            {{-- Bill to / project --}}
            <x-pdf::row gap="16pt" padding="12pt" background="slate-50" radius="6pt">
                <x-pdf::column gap="2pt" grow="1">
                    <x-pdf::text size="8pt" bold color="slate-500" letter-spacing="0.6pt">BILL TO</x-pdf::text>
                    <x-pdf::text bold>Marlowe &amp; Finch Publishing</x-pdf::text>
                    <x-pdf::text>Attn: Accounts Payable</x-pdf::text>
                    <x-pdf::text>900 Meridian Avenue</x-pdf::text>
                    <x-pdf::text>Seattle, WA 98101</x-pdf::text>
                </x-pdf::column>
                <x-pdf::column gap="2pt" grow="1">
                    <x-pdf::text size="8pt" bold color="slate-500" letter-spacing="0.6pt">PROJECT</x-pdf::text>
                    <x-pdf::text>Report Platform Rebuild</x-pdf::text>
                    <x-pdf::text size="8pt" bold color="slate-500" letter-spacing="0.6pt" margin="6pt 0 0 0">PO NUMBER</x-pdf::text>
                    <x-pdf::text>PO-77310</x-pdf::text>
                </x-pdf::column>
            </x-pdf::row>

            {{-- Line items --}}
            <x-pdf::table columns="1fr 20mm 25mm 25mm">
                <x-pdf::thead>
                    <x-pdf::tr background="slate-100">
                        <x-pdf::th font-size="9pt">Description</x-pdf::th>
                        <x-pdf::th align="right" font-size="9pt">Hours</x-pdf::th>
                        <x-pdf::th align="right" font-size="9pt">Rate</x-pdf::th>
                        <x-pdf::th align="right" font-size="9pt">Amount</x-pdf::th>
                    </x-pdf::tr>
                </x-pdf::thead>
                @foreach ($items as [$name, $desc, $hours, $rate])
                    <x-line-item-row :name="$name" :desc="$desc" :hours="$hours" :rate="$rate" />
                @endforeach
            </x-pdf::table>

            {{-- Totals --}}
            <x-pdf::row>
                <x-pdf::spacer />
                <x-pdf::column gap="4pt" width="70mm">
                    <x-pdf::row>
                        <x-pdf::text color="slate-600">Subtotal</x-pdf::text>
                        <x-pdf::spacer />
                        <x-pdf::text align="right">${{ number_format($subtotal, 2) }}</x-pdf::text>
                    </x-pdf::row>
                    <x-pdf::row>
                        <x-pdf::text color="slate-600">Tax (8.25%)</x-pdf::text>
                        <x-pdf::spacer />
                        <x-pdf::text align="right">${{ number_format($tax, 2) }}</x-pdf::text>
                    </x-pdf::row>
                    <x-pdf::row margin="4pt 0 0 0" padding="6pt 0 0 0" border-width="1pt 0 0 0" border-color="slate-900">
                        <x-pdf::text size="12pt" bold color="slate-900">Total due</x-pdf::text>
                        <x-pdf::spacer />
                        <x-pdf::text size="12pt" bold align="right">${{ number_format($total, 2) }}</x-pdf::text>
                    </x-pdf::row>
                </x-pdf::column>
            </x-pdf::row>
        </x-pdf::column>

        <x-slot:footer>
            <x-pdf::column padding="8pt 0 0 0" border-width="0.5pt 0 0 0" border-color="slate-200">
                <x-pdf::row gap="12pt" justify="space-between">
                    <x-pdf::text size="8pt" color="slate-500" grow="1">Payment terms: net 30. Wire or ACH to Northwind Studio, account ending 4417.</x-pdf::text>
                    <x-pdf::page-number format="Page {page} of {total}" size="8pt" color="slate-500" align="right" />
                </x-pdf::row>
            </x-pdf::column>
        </x-slot:footer>
    </x-pdf::page>
</x-pdf::document>
