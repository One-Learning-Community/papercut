{{-- App-side content: an API integration guide on US Letter, portrait then landscape then portrait. --}}
<x-doc::document title="Parcelpost API integration guide" author="Parcelpost">
    <x-doc::page size="letter" margin="0.75in">
        <x-slot:header>
            <x-doc::row padding="0 0 6pt 0" border-width="0 0 0.75pt 0" border-color="slate-200">
                <x-doc::text size="11pt" bold color="indigo-600">Parcelpost API</x-doc::text>
                <x-doc::spacer />
                <x-doc::text size="9pt" color="slate-500">Integration guide</x-doc::text>
            </x-doc::row>
        </x-slot:header>

        <x-doc::column gap="12pt">
            <x-doc::column gap="3pt">
                <x-doc::text size="9pt" bold color="indigo-600">INTEGRATION GUIDE</x-doc::text>
                <x-doc::h1>Create and track shipments</x-doc::h1>
                <x-doc::text size="9pt" color="slate-500">Version 2.1 | Updated March 2026</x-doc::text>
            </x-doc::column>

            <x-doc::text>The Parcelpost API lets your store buy shipping labels, book collections and follow parcels without leaving your own system. Every call is plain HTTPS with JSON bodies, and every call can be tried in the sandbox first.</x-doc::text>

            <x-doc::callout type="warning" title="Start with a test key">Test keys create sandbox shipments that never reach a carrier. Switch to a live key only after the checklist on the last page.</x-doc::callout>

            <x-doc::column gap="4pt">
                <x-doc::h3 keep-with-next>Set up</x-doc::h3>
                <x-doc::ol>
                    @foreach ($steps as $step)
                        <x-doc::li>{{ $step }}</x-doc::li>
                    @endforeach
                </x-doc::ol>
            </x-doc::column>

            <x-doc::column gap="8pt">
                <x-doc::h3 keep-with-next>Create a shipment</x-doc::h3>
                <x-doc::pre language="bash" label="Request" variant="caption">
                    curl https://api.example.com/v2/shipments \
                      -H "Authorization: Bearer pk_test_51Hx0" \
                      -H "Idempotency-Key: order-1042" \
                      -d service=standard \
                      -d 'parcel[weight_kg]=1.4' \
                      -d 'to[postcode]=94107'
                </x-doc::pre>
                <x-doc::pre language="json" label="Response: 201 Created" variant="strip">
                    {
                      "id": "shp_7Q2m9x",
                      "status": "label_created",
                      "tracking_code": "PP1042883301",
                      "price": { "amount": 8.45, "currency": "USD" },
                      "label_url": "https://api.example.com/v2/shipments/shp_7Q2m9x/label"
                    }
                </x-doc::pre>
            </x-doc::column>
        </x-doc::column>

        <x-slot:footer>
            <x-doc::page-number format="Page {page} of {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-doc::page>

    <x-doc::page size="letter" orientation="landscape" margin="0.75in">
        <x-slot:header>
            <x-doc::row padding="0 0 6pt 0" border-width="0 0 0.75pt 0" border-color="slate-200">
                <x-doc::text size="11pt" bold color="indigo-600">Parcelpost API</x-doc::text>
                <x-doc::spacer />
                <x-doc::text size="9pt" color="slate-500">Integration guide | Reference</x-doc::text>
            </x-doc::row>
        </x-slot:header>

        <x-doc::column gap="12pt">
            <x-doc::column gap="3pt">
                <x-doc::h3 keep-with-next>Endpoint reference</x-doc::h3>
                <x-doc::text color="slate-600">All paths are relative to https://api.example.com. Send the key as a bearer token; limits are per key.</x-doc::text>
            </x-doc::column>

            <x-doc::table columns="0.9in 2.8in 1fr 1in 1in">
                <x-doc::thead>
                    <x-doc::tr background="slate-100">
                        <x-doc::th>Method</x-doc::th>
                        <x-doc::th>Path</x-doc::th>
                        <x-doc::th>Description</x-doc::th>
                        <x-doc::th>Auth</x-doc::th>
                        <x-doc::th align="right">Rate limit</x-doc::th>
                    </x-doc::tr>
                </x-doc::thead>
                @foreach ($endpoints as [$method, $path, $description, $auth, $limit])
                    <x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
                        <x-doc::td><x-doc::badge :type="$methodTones[$method] ?? 'warning'">{{ $method }}</x-doc::badge></x-doc::td>
                        <x-doc::td><x-doc::text><x-doc::code>{{ $path }}</x-doc::code></x-doc::text></x-doc::td>
                        <x-doc::td>{{ $description }}</x-doc::td>
                        <x-doc::td><x-doc::text color="slate-600">{{ $auth }}</x-doc::text></x-doc::td>
                        <x-doc::td align="right"><x-doc::text color="slate-600">{{ $limit }}</x-doc::text></x-doc::td>
                    </x-doc::tr>
                @endforeach
            </x-doc::table>

            <x-doc::column gap="4pt">
                <x-doc::h3 keep-with-next>Errors</x-doc::h3>
                <x-doc::table columns="0.7in 1.6in 3.2in 1fr">
                    <x-doc::thead>
                        <x-doc::tr background="slate-100">
                            <x-doc::th>Status</x-doc::th>
                            <x-doc::th>Code</x-doc::th>
                            <x-doc::th>Meaning</x-doc::th>
                            <x-doc::th>What to do</x-doc::th>
                        </x-doc::tr>
                    </x-doc::thead>
                    @foreach ($errors as [$status, $code, $meaning, $action])
                        <x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
                            <x-doc::td><x-doc::text bold>{{ $status }}</x-doc::text></x-doc::td>
                            <x-doc::td><x-doc::text><x-doc::code>{{ $code }}</x-doc::code></x-doc::text></x-doc::td>
                            <x-doc::td>{{ $meaning }}</x-doc::td>
                            <x-doc::td><x-doc::text color="slate-600">{{ $action }}</x-doc::text></x-doc::td>
                        </x-doc::tr>
                    @endforeach
                </x-doc::table>
            </x-doc::column>
        </x-doc::column>

        <x-slot:footer>
            <x-doc::page-number format="Page {page} of {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-doc::page>

    <x-doc::page size="letter" margin="0.75in">
        <x-slot:header>
            <x-doc::row padding="0 0 6pt 0" border-width="0 0 0.75pt 0" border-color="slate-200">
                <x-doc::text size="11pt" bold color="indigo-600">Parcelpost API</x-doc::text>
                <x-doc::spacer />
                <x-doc::text size="9pt" color="slate-500">Integration guide | Webhooks</x-doc::text>
            </x-doc::row>
        </x-slot:header>

        <x-doc::column gap="12pt">
            <x-doc::column gap="4pt">
                <x-doc::h3 keep-with-next>Webhooks</x-doc::h3>
                <x-doc::text>Parcelpost calls your endpoint when a shipment changes state. Each delivery is signed, so check the signature before you trust the body.</x-doc::text>
            </x-doc::column>

            {{-- PHP source comes in as data: a literal <?php in a view would run. --}}
            <x-doc::pre language="php" label="verify.php" variant="strip">{{ $verify }}</x-doc::pre>

            <x-doc::row gap="16pt">
                <x-doc::card title="Request headers" grow="1">
                    <x-doc::key-value label-width="45%">
                        @foreach ($headers as [$name, $value])
                            <x-doc::kv :label="$name">{{ $value }}</x-doc::kv>
                        @endforeach
                    </x-doc::key-value>
                </x-doc::card>
                <x-doc::column gap="4pt" align="center">
                    <x-doc::qr-code value="https://docs.example.com/parcelpost/webhooks" width="1.1in" />
                    <x-doc::text size="8pt" color="slate-500">Webhook docs</x-doc::text>
                </x-doc::column>
            </x-doc::row>

            <x-doc::column gap="6pt">
                <x-doc::h3 keep-with-next>Go-live checklist</x-doc::h3>
                <x-doc::choices type="checkbox">
                    @foreach ($checklist as [$label, $done])
                        <x-doc::choice :selected="$done">{{ $label }}</x-doc::choice>
                    @endforeach
                </x-doc::choices>
            </x-doc::column>

            <x-doc::blockquote cite="Parcelpost support team">Retries are on our side. Answer quickly and keep your handler boring.</x-doc::blockquote>
        </x-doc::column>

        <x-slot:footer>
            <x-doc::page-number format="Page {page} of {total}" align="center" size="8pt" color="slate-500" />
        </x-slot:footer>
    </x-doc::page>
</x-doc::document>
