# Preview routes

Getting a layout right means rendering it over and over. Preview routes give each document a fixed URL that renders it with sample data, so you can just refresh the browser to see your latest change.

Register each view along with a closure that returns its data. A service provider is a good place for this:

```php
use Papercut\Laravel\Facades\Papercut;

Papercut::preview('pdf.invoice', fn () => ['invoice' => Invoice::factory()->make()]);
```

Then set `PAPERCUT_PREVIEW=true` in your `.env` file.

| Route | What it shows |
|---|---|
| `GET /_papercut` | a list of the registered views |
| `GET /_papercut/{view}` | one view, as a PDF in the browser |
| `GET /_papercut/{view}?format=html` | the same view as HTML |
| `GET /_papercut/{view}?format=docx` | the same view as a Word file. Needs `papercut/docx`; without it you get a 404 with the install command |

Any other `format` value is a 404.

For safety, the routes only work in the `local` environment, whatever the setting says. To change the URL prefix, set `preview.path`.
