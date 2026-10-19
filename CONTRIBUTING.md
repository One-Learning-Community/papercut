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

The Laravel package's path repository pins core to `0.1.x-dev` (`options.versions` in its `composer.json`), so it
resolves on any branch or detached checkout. Bump that pin together with core's `branch-alias` when the minor
version changes.

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

Snapshot updates: visual-regression snapshots (`packages/core/tests/Visual/snapshots`, rasterised with `pdftoppm -r 72`)
are refreshed with `cd packages/core && UPDATE_SNAPSHOTS=1 php -d xdebug.mode=off vendor/bin/phpunit tests/Visual`.
Open every changed PNG and review it before committing. Failures write the actual and diff images to
`packages/core/tests/Visual/output/` (git-ignored).

Tests come first: write the failing test, watch it fail, then implement. `packages/core/README.md` is tested too:
`tests/Feature/ReadmeTest.php` executes every fenced `php` block in it (each block must leave a `$document`, and
optionally `$options`), so a README example that stops working fails the build.

## Benchmark

`packages/core/bench` renders a 10-page transcript. `run.php` does one warm-up render, then times 5 renders in the same
process and reports their median; run it with Xdebug off and OPcache on. This is a warm in-process figure: a cold
process (first render after start, including autoload and font loading) is higher.

```
cd packages/core
php -d xdebug.mode=off -d opcache.enable_cli=1 bench/run.php | php bench/check.php
```

`check.php` compares the median with `bench/baseline.json` for the machine class in `PAPERCUT_BENCH_MACHINE` (default
`local`) and fails above 1.2x the baseline. CI runs as `ci-ubuntu-latest`; until a maintainer commits that entry
(taken from the baseline the first CI run uploads as a build artifact), the gate checks only the page count.

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

Releases are tag-driven. Packagist reads one `composer.json` per repository, so each package is mirrored into its own
read-only split repository: `One-Learning-Community/papercut-core` and `One-Learning-Community/papercut-laravel`.
Never commit to the split repos directly; they are overwritten on every split.

| Workflow | Trigger | Does |
|---|---|---|
| `core.yml`, `laravel.yml` | push to `main`, pull requests | tests, PHPStan, benchmark gate |
| `split.yml` | push to `main` | mirrors `packages/*` to the split repos (their `dev-main`) |
| `release.yml` | tag `vX.Y.Z` | both suites, CHANGELOG check, split + tag both packages, GitHub release |

### One-time setup

1. Create a fine-grained personal access token (resource owner `One-Learning-Community`) with
   **Contents: read and write** on `papercut-core` and `papercut-laravel` only, and save it as the
   `SPLIT_TOKEN` Actions secret on `One-Learning-Community/papercut`. Without it `split.yml` skips with a notice
   and `release.yml` fails at the split step.
2. Once both split repos have content, submit them at https://packagist.org/packages/submit (`papercut-core`
   first, then `papercut-laravel`). Packagist's GitHub integration keeps them updated on new pushes and tags.

### Each release

1. Make sure `main` is green.
2. In `CHANGELOG.md` change `## [X.Y.Z] - unreleased` to the release date (`release.yml` refuses an unreleased
   or missing section) and add a fresh `## [Unreleased]` section.
3. If the minor version changed, bump core's `extra.branch-alias` and the Laravel path repo's `versions` pin.
4. Commit, then tag from `main`: `git tag -s vX.Y.Z -m "Papercut X.Y.Z" && git push origin main vX.Y.Z`.
5. Verify in a scratch project: `composer require papercut/core papercut/laravel`, then render one document with
   each.

The Laravel package's `repositories` and `minimum-stability` entries exist for monorepo development. Composer
ignores both in non-root packages, so they do not affect consumers of the split repo.
