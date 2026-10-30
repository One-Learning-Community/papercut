# Papercut core examples

Plain-PHP documents built with `Papercut\Document`. Run from the repository root:

```
php examples/core/invoice.php      # one-page invoice with letterhead, line items and totals
php examples/core/transcript.php   # multi-page transcript with a repeating table header
php examples/core/report.php       # justified prose, stat cards, an 80-row table, appendix
php examples/core/responses.php    # a feedback-form record built from eight of the decorators
php examples/core/guide.php        # integration guide: portrait, landscape, portrait; highlighted code blocks
```

Each script requires its Document from `documents/<name>.php`, which just returns it, so the same documents can be rendered by other exporters. `php examples/html/render.php` writes `examples/html/{invoice,transcript,report,responses,guide}.html` (full pages with fonts linked by relative path, so open them from a checkout).

Each script writes `examples/core/<name>.pdf` (git-ignored) and prints its name. `hello.php` is a low-level backend demo.
