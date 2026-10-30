# Papercut Blade examples

Blade versions of the plain-PHP examples in `examples/core`: `invoice`, `transcript` and `report`. It also has `responses`, a feedback-form record built from the decorator tags (`card`, `key-value`, `callout`, `section`, `field`, `choices`, `badge`, `blockquote`); `examples/core/documents/responses.php` builds the same document in PHP. `guide` is a three-page integration guide on US Letter (portrait, landscape, portrait) with highlighted code blocks; `examples/core/documents/guide.php` builds it in PHP. `certificate` fills in the blank PDF in `examples/assets/` (render.php sets `papercut.image_path` to that directory, so the view uses relative paths) and has no HTML version, because HTML drops page templates. `article` converts the editor HTML from `data.php` with `<x-doc::markup>`.
`views/components/` holds two app components (`<x-stat-card>`, `<x-line-item-row>`) that wrap `<x-doc::*>` tags.
Sample data lives in `data.php`; the report's table is generated filler, not measurements.

```
cd packages/laravel && composer install
php examples/laravel/render.php   # writes examples/laravel/{invoice,transcript,report,responses,guide,article}.{pdf,html} and certificate.pdf (the PDFs are git-ignored, the HTML is committed)
```

`render.php` boots a throwaway app with Orchestra Testbench (a dev dependency) and registers the provider by hand.
