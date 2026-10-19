# Contributing to Papercut

Papercut is a monorepo with two Composer packages:

- `packages/core` (`papercut/core`): the layout engine and plain-PHP API.
- `packages/laravel` (`papercut/laravel`): Blade components on top of core. It requires core through a path
  repository (`../core`, symlinked), so a change in core is visible in the Laravel package immediately.

## Development setup

You need PHP 8.4+ with `mbstring`, `zlib` and `dom`; Composer 2; and two command-line tools the tests use to
validate and inspect PDFs: **qpdf** (structure checks) and **poppler** (`pdftotext`, `pdfinfo`, `pdftoppm`).
The `gd` extension is used by a few image tests (dev only).

```
# macOS
brew install qpdf poppler

# Debian / Ubuntu
sudo apt-get update && sudo apt-get install -y qpdf poppler-utils
```

Install both packages:

```
composer --working-dir=packages/core install
composer --working-dir=packages/laravel install
```

If Composer cannot resolve `papercut/core` for the Laravel package, the path repository has probably not picked
up core's `0.1.x-dev` branch alias (the alias is declared for the `main` branch). Run
`COMPOSER_ROOT_VERSION=0.1.x-dev composer --working-dir=packages/laravel update papercut/core`.

## Running the tests

Run each package's suite and static analysis from its own directory (PHPStan runs at level 8 and must be clean):

```
cd packages/core
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G

cd ../laravel
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
```

If Xdebug is loaded, prefix with `php -d xdebug.mode=off` for speed. CI runs the same commands on PHP 8.4 and 8.5
(`.github/workflows`).

Tests come first: write the failing test, watch it fail, then implement. `packages/core/README.md` is tested too:
`tests/Feature/ReadmeTest.php` executes every fenced `php` block in it (each block must leave a `$document`, and
optionally `$options`), so a README example that stops working fails the build.

## Examples

```
php examples/core/invoice.php        # also transcript.php, report.php, hello.php
php examples/laravel/render.php      # needs `composer install` in packages/laravel
```

They write PDFs next to the scripts (git-ignored). `tests/Feature/ExamplesTest.php` runs the core examples.

## Style

- `declare(strict_types=1)` everywhere; types on every signature; components are immutable (methods return copies).
- Only `Papercut\Backend\PhpPdf\*` may import `Dskripchenko\*` (enforced by `tests/ArchitectureTest.php`).
- No new runtime dependencies without discussion.
- Error messages say what is wrong, where, and what to do about it.

## Releasing

Publishing and tagging are maintainer actions; nothing here runs automatically. Placeholders to resolve first:
every `https://github.com/OWNER/papercut` URL (the `homepage` and `support` fields of both `composer.json` files)
must be replaced with the real repository URL.

1. Make sure `main` is green: both suites and PHPStan on PHP 8.4 and 8.5, and the core README test passes.
2. Replace the `OWNER` placeholder (`grep -rn OWNER --include=composer.json .`).
3. In `CHANGELOG.md` change `## [0.1.0] - unreleased` to the release date and add a fresh `Unreleased` section.
4. Commit, then tag from `main`: `git tag -s v0.1.0 -m "Papercut 0.1.0" && git push origin main v0.1.0`.
5. `papercut/laravel` requires `papercut/core: ^0.1` and `papercut/core` is a separate Composer package, but
   Packagist reads one `composer.json` per repository. Publish each package from its own read-only split
   repository (for example with `splitsh-lite` or a subtree-split workflow), tagging both with `v0.1.0`; the
   monorepo itself is not submitted to Packagist.
6. Submit the `papercut/core` split repository at https://packagist.org/packages/submit first, then
   `papercut/laravel`, and enable the GitHub webhook so new tags are picked up.
7. Verify in a scratch project: `composer require papercut/core` and `composer require papercut/laravel`, then
   render one document with each.
8. Before the Laravel package is installable from Packagist, remove its `repositories` path entry from the split
   copy's `composer.json` (the path repo only exists for monorepo development).
