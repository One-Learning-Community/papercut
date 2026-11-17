# Preview routes

Preview routes give each document a fixed URL that renders it with sample data. Refresh the browser to see your latest change.

Register each view with a closure that returns its data, for example in a service provider:

```php
use Quoin\Laravel\Facades\Quoin;

Quoin::preview('pdf.invoice', fn () => ['invoice' => Invoice::factory()->make()]);
```

Then set `QUOIN_PREVIEW=true` in your `.env` file.

| Route | What it shows |
|---|---|
| `GET /_quoin` | a list of the registered views |
| `GET /_quoin/{view}` | one view, as a PDF in the browser |
| `GET /_quoin/{view}?format=html` | the same view as HTML |
| `GET /_quoin/{view}?format=docx` | the same view as a Word file. Needs `quoin/docx`; without it you get a 404 with the install command |

Any other `format` value is a 404.

The routes work only in the `local` environment, whatever the setting says. To change the URL prefix, set `preview.path`.
