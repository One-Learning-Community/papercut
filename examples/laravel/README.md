# Papercut Blade examples

Blade versions of the plain-PHP examples in `examples/core`: `invoice`, `transcript`, `report`, and `responses`, a feedback-form record built from the decorator tags (`section`, `field`, `choices`, `key-value`, `badge`, `callout`).
`views/components/` holds two app components (`<x-stat-card>`, `<x-line-item-row>`) that wrap `<x-doc::*>` tags.
Sample data lives in `data.php`; the report's table is generated filler, not measurements.

```
cd packages/laravel && composer install
php examples/laravel/render.php   # writes examples/laravel/{invoice,transcript,report,responses}.{pdf,html} (the PDFs are git-ignored, the HTML is committed)
```

`render.php` boots a throwaway app with Orchestra Testbench (a dev dependency) and registers the provider by hand.
