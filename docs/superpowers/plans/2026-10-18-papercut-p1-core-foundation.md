# Papercut Plan 1: Core Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the bottom of Papercut's pipeline: typed values with a strict string grammar, backend-neutral paint ops, a font registry, and a `PhpPdfBackend` that turns page display lists into valid PDFs through `dskripchenko/php-pdf`.

**Architecture:** `papercut/core` lives in `packages/core` of a monorepo. Layout code (Plan 2) will produce `PageDisplayList`s of immutable `Op` objects in top-left-origin points. A `Backend` interface renders them. `PhpPdfBackend` is the only code that imports `Dskripchenko\*`. Fonts are measured through the `FontHandle` interface, and measurement must agree exactly with what the backend draws.

**Tech Stack:** PHP 8.4, Composer, PHPUnit ^13.4, PHPStan ^2.3, `dskripchenko/php-pdf` ^1.24, qpdf + poppler-utils (test-time only).

**Spec:** `docs/superpowers/specs/2026-10-18-papercut-design.md`

## Global Constraints

- PHP `^8.4`. Every PHP file starts with `declare(strict_types=1);`.
- Package `papercut/core`, root namespace `Papercut\`, source `packages/core/src`, tests `packages/core/tests` (namespace `Papercut\Tests\`).
- Runtime requirements are exactly: `php ^8.4`, `ext-mbstring`, `ext-zlib`, `ext-dom`, `dskripchenko/php-pdf ^1.24`.
- Only files under `packages/core/src/Backend/PhpPdf/` may reference `Dskripchenko\`.
- All library exceptions extend `Papercut\PapercutException`. Specific types live in `Papercut\Exception\`.
- Internal geometry is `float` points (1pt = 1/72in), origin top-left, y grows downward.
- Value grammar (spec §6.5): lengths `12pt`, `16px` (0.75pt), `20mm`, `2cm`, `1in`; percent `50%`; fractions `3fr`; `auto`. Bare numbers are rejected except `0`. Edges take 1–4 values in CSS order. Colors: `#rgb`, `#rrggbb`, `#rrggbbaa`, `rgb(…)`/`rgba(…)`, Tailwind-style named palette (`slate-500`), `black`, `white`, `transparent`.
- Error message shape (spec §8): `Invalid length "20 mm". Did you mean "20mm"?`. With context: `Invalid length "20 mm" for margin on <x-pdf::page>. Did you mean "20mm"?`.
- Output must be byte-deterministic when `DocumentMeta::$createdAt` is fixed.
- Classes are `final` (and `readonly` for value objects) unless an interface or abstract base is required.
- Run tests from the repo root: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist`.

## Review Focus

1. `margin="0"` (bare zero) must parse as a zero length. CSS users type this constantly. Test: Task 2.
2. Text containing glyphs the font lacks (CJK or emoji in Inter) must render without throwing, keep the PDF valid, and `covers()` must report `false`. Test: Task 7 + Task 9.
3. Empty or whitespace-only text runs must measure as `0.0` (whitespace: its real advance) and must not crash the backend. Test: Task 7 + Task 9.
4. A color with alpha (`#33415580`) must draw translucent, not opaque. Test: Task 9 (ExtGState `/ca` present).
5. Non-ASCII metadata (`Café ✓ Report`) must survive into the PDF Info dictionary. Test: Task 9 (via `pdfinfo`).

---

## File Structure

```
.gitignore
packages/core/
  composer.json
  phpunit.xml.dist
  phpstan.neon
  resources/
    fonts/Inter/{Inter-Regular,Inter-Italic,Inter-Bold,Inter-BoldItalic}.ttf, LICENSE.txt
    palette.php                         generated Tailwind 3.4.17 palette
  tools/generate-palette.php
  src/
    PapercutException.php
    functions.php                       pt() px() mm() cm() in() percent() fr()
    Exception/{InvalidValue,FontNotFound,ImageUnreadable}.php
    Value/
      Unit.php  Length.php  Percent.php  Fr.php  Auto.php
      TrackList.php  Edges.php  Color.php
      Internal/Tokens.php               whitespace tokenizer w/ split-unit suggestions
    Paint/
      Op.php  TextRun.php  Rect.php  Line.php  DrawImage.php
      Clip.php  Opacity.php  Link.php  PageDisplayList.php  DocumentMeta.php
    Backend/
      Backend.php  FontHandle.php  ImageHandle.php
      PhpPdf/{PhpPdfBackend,PhpPdfFont,PhpPdfImage}.php
    Text/
      FontStyle.php  FontRegistry.php  CachedFont.php
  tests/
    Support/PdfInspector.php            wraps qpdf / pdftotext / pdfinfo
    Support/FakeFont.php
    Value/... Paint/... Backend/... Text/...
examples/core/hello.php
.github/workflows/core.yml
```

---

### Task 1: Scaffold `papercut/core`

**Files:**
- Create: `.gitignore`, `packages/core/composer.json`, `packages/core/phpunit.xml.dist`, `packages/core/phpstan.neon`
- Create: `packages/core/src/PapercutException.php`, `packages/core/src/functions.php`
- Create: `packages/core/src/Exception/InvalidValue.php`, `packages/core/src/Exception/FontNotFound.php`, `packages/core/src/Exception/ImageUnreadable.php`
- Create: `packages/core/tests/Support/PdfInspector.php`
- Test: `packages/core/tests/Exception/InvalidValueTest.php`

**Interfaces:**
- Produces: `abstract class Papercut\PapercutException extends \RuntimeException`
- Produces: `final class Papercut\Exception\InvalidValue extends PapercutException` with
  `static for(string $kind, string $input, ?string $suggestion = null): self`,
  `withContext(string $context): self` (returns a new instance),
  readonly props `string $kind, string $input, ?string $suggestion, ?string $context`.
- Produces: `final class FontNotFound extends PapercutException`, `final class ImageUnreadable extends PapercutException` (plain message constructors).
- Produces: `Papercut\Tests\Support\PdfInspector` with static methods
  `check(string $pdfBytes): void` (asserts `qpdf --check` exit 0), `text(string $pdfBytes): string` (pdftotext),
  `words(string $pdfBytes, int $page = 1): list<array{text:string,xMin:float,yMin:float,xMax:float,yMax:float}>` (pdftotext -bbox),
  `info(string $pdfBytes): array<string,string>` (pdfinfo key/values),
  `qdf(string $pdfBytes): string` (uncompressed QDF body, for grepping).

- [ ] **Step 1: Install system tools (macOS dev / CI Linux)**

Run (macOS): `brew install qpdf poppler`. CI (Ubuntu): `sudo apt-get install -y qpdf poppler-utils`.
Expected: `qpdf --version`, `pdftotext -v`, `pdfinfo -v` all succeed.

- [ ] **Step 2: Create root `.gitignore`**

```gitignore
/packages/*/vendor/
/packages/*/composer.lock
/packages/*/.phpunit.cache/
/.superpowers/
*.pdf
!packages/*/tests/fixtures/**/*.pdf
```

- [ ] **Step 3: Create `packages/core/composer.json`**

```json
{
    "name": "papercut/core",
    "description": "Component-based, page-aware PDF layout engine for modern PHP.",
    "type": "library",
    "license": "MIT",
    "keywords": ["pdf", "layout", "flexbox", "document", "generator"],
    "require": {
        "php": "^8.4",
        "ext-dom": "*",
        "ext-mbstring": "*",
        "ext-zlib": "*",
        "dskripchenko/php-pdf": "^1.24"
    },
    "require-dev": {
        "phpstan/phpstan": "^2.3",
        "phpunit/phpunit": "^13.4"
    },
    "autoload": {
        "psr-4": { "Papercut\\": "src/" },
        "files": ["src/functions.php"]
    },
    "autoload-dev": {
        "psr-4": { "Papercut\\Tests\\": "tests/" }
    },
    "scripts": {
        "test": "phpunit",
        "analyse": "phpstan analyse --memory-limit=1G"
    },
    "config": { "sort-packages": true }
}
```

- [ ] **Step 4: Create `packages/core/phpunit.xml.dist` and `packages/core/phpstan.neon`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache"
         colors="true"
         failOnWarning="true"
         failOnRisky="true"
         beStrictAboutOutputDuringTests="true">
    <testsuites>
        <testsuite name="core">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

```neon
parameters:
    level: 8
    paths:
        - src
        - tests
```

- [ ] **Step 5: Create the exception base, `functions.php` stub, and the three exceptions**

`packages/core/src/PapercutException.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut;

abstract class PapercutException extends \RuntimeException
{
}
```

`packages/core/src/functions.php` (functions are added in Task 2):
```php
<?php

declare(strict_types=1);

namespace Papercut;
```

`packages/core/src/Exception/FontNotFound.php` and `ImageUnreadable.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Exception;

use Papercut\PapercutException;

final class FontNotFound extends PapercutException
{
}
```
(`ImageUnreadable` is identical apart from the class name.)

- [ ] **Step 6: Write the failing test for `InvalidValue`**

`packages/core/tests/Exception/InvalidValueTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Exception;

use Papercut\Exception\InvalidValue;
use Papercut\PapercutException;
use PHPUnit\Framework\TestCase;

final class InvalidValueTest extends TestCase
{
    public function test_message_without_suggestion(): void
    {
        $e = InvalidValue::for('length', '20px!');
        self::assertInstanceOf(PapercutException::class, $e);
        self::assertSame('Invalid length "20px!".', $e->getMessage());
    }

    public function test_message_with_suggestion(): void
    {
        $e = InvalidValue::for('length', '20 mm', '20mm');
        self::assertSame('Invalid length "20 mm". Did you mean "20mm"?', $e->getMessage());
        self::assertSame('20mm', $e->suggestion);
    }

    public function test_with_context_returns_new_exception_with_context_in_message(): void
    {
        $base = InvalidValue::for('length', '20 mm', '20mm');
        $e = $base->withContext('margin on <x-pdf::page>');
        self::assertNotSame($base, $e);
        self::assertSame(
            'Invalid length "20 mm" for margin on <x-pdf::page>. Did you mean "20mm"?',
            $e->getMessage(),
        );
        self::assertSame($base, $e->getPrevious());
    }
}
```

- [ ] **Step 7: Install dependencies and run the test to see it fail**

Run: `composer --working-dir=packages/core install` then `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist --filter InvalidValueTest`
Expected: FAIL / error, `Class "Papercut\Exception\InvalidValue" not found`.

- [ ] **Step 8: Implement `InvalidValue`**

```php
<?php

declare(strict_types=1);

namespace Papercut\Exception;

use Papercut\PapercutException;

final class InvalidValue extends PapercutException
{
    private function __construct(
        public readonly string $kind,
        public readonly string $input,
        public readonly ?string $suggestion,
        public readonly ?string $context,
        ?\Throwable $previous = null,
    ) {
        $msg = sprintf('Invalid %s "%s"', $kind, $input);
        if ($context !== null) {
            $msg .= ' for ' . $context;
        }
        $msg .= '.';
        if ($suggestion !== null) {
            $msg .= sprintf(' Did you mean "%s"?', $suggestion);
        }
        parent::__construct($msg, 0, $previous);
    }

    public static function for(string $kind, string $input, ?string $suggestion = null): self
    {
        return new self($kind, $input, $suggestion, null);
    }

    public function withContext(string $context): self
    {
        return new self($this->kind, $this->input, $this->suggestion, $context, $this);
    }
}
```

- [ ] **Step 9: Create `PdfInspector` test helper**

`packages/core/tests/Support/PdfInspector.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Support;

use PHPUnit\Framework\Assert;

final class PdfInspector
{
    public static function check(string $pdf): void
    {
        [$code, $out] = self::run(['qpdf', '--check'], $pdf);
        Assert::assertSame(0, $code, "qpdf --check failed:\n" . $out);
    }

    public static function text(string $pdf): string
    {
        return self::run(['pdftotext', '-enc', 'UTF-8'], $pdf, '-')[1];
    }

    /** @return list<array{text:string,xMin:float,yMin:float,xMax:float,yMax:float}> */
    public static function words(string $pdf, int $page = 1): array
    {
        $html = self::run(['pdftotext', '-bbox', '-enc', 'UTF-8', '-f', (string) $page, '-l', (string) $page], $pdf, '-')[1];
        preg_match_all('/<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="([\d.]+)">(.*?)<\/word>/u', $html, $m, PREG_SET_ORDER);
        return array_map(static fn (array $w): array => [
            'text' => html_entity_decode($w[5], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'xMin' => (float) $w[1], 'yMin' => (float) $w[2],
            'xMax' => (float) $w[3], 'yMax' => (float) $w[4],
        ], $m);
    }

    /** @return array<string,string> */
    public static function info(string $pdf): array
    {
        $out = self::run(['pdfinfo', '-enc', 'UTF-8'], $pdf)[1];
        $info = [];
        foreach (explode("\n", $out) as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/u', $line, $m)) {
                $info[trim($m[1])] = trim($m[2]);
            }
        }
        return $info;
    }

    public static function qdf(string $pdf): string
    {
        $in = self::tmp($pdf);
        $out = $in . '.qdf';
        exec(sprintf('qpdf --qdf --object-streams=disable %s %s 2>&1', escapeshellarg($in), escapeshellarg($out)), $o, $code);
        Assert::assertContains($code, [0, 3], 'qpdf --qdf failed: ' . implode("\n", $o));
        $body = (string) file_get_contents($out);
        @unlink($in);
        @unlink($out);
        return $body;
    }

    /**
     * @param list<string> $cmd
     * @return array{0:int,1:string}
     */
    private static function run(array $cmd, string $pdf, ?string $trailingArg = null): array
    {
        $file = self::tmp($pdf);
        $parts = array_map('escapeshellarg', $cmd);
        $parts[] = escapeshellarg($file);
        if ($trailingArg !== null) {
            $parts[] = escapeshellarg($trailingArg);
        }
        exec(implode(' ', $parts) . ' 2>&1', $lines, $code);
        @unlink($file);
        return [$code, implode("\n", $lines)];
    }

    private static function tmp(string $pdf): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pc') . '.pdf';
        file_put_contents($file, $pdf);
        return $file;
    }
}
```

- [ ] **Step 10: Run tests and static analysis**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist` → PASS (3 tests).
Run: `packages/core/vendor/bin/phpstan analyse -c packages/core/phpstan.neon --memory-limit=1G` (from `packages/core`: `cd packages/core && vendor/bin/phpstan analyse`) → no errors.

- [ ] **Step 11: Commit**

```bash
git add .gitignore packages/core
git commit -m "feat(core): scaffold papercut/core package with exception base"
```

---

### Task 2: `Unit`, `Length` and helper functions

**Files:**
- Create: `packages/core/src/Value/Unit.php`, `packages/core/src/Value/Length.php`
- Modify: `packages/core/src/functions.php`
- Test: `packages/core/tests/Value/LengthTest.php`

**Interfaces:**
- Consumes: `InvalidValue::for()`.
- Produces: `enum Papercut\Value\Unit: string { Pt='pt'; Px='px'; Mm='mm'; Cm='cm'; In='in'; public function points(): float }` (points per unit: Pt 1, Px 0.75, Mm 72/25.4, Cm 720/25.4, In 72).
- Produces: `final readonly class Papercut\Value\Length implements \Stringable` with `public float $points`; `static of(float $value, Unit $unit): self`; `static points(float $pt): self`; `static zero(): self`; `static parse(string $input): self`; `plus(Length): self`; `minus(Length): self`; `scale(float): self`; `equals(Length, float $epsilon = 1e-6): bool`; `__toString()` → canonical `"<n>pt"` (up to 4 decimals, trailing zeros trimmed, e.g. `"12pt"`, `"56.6929pt"`, `"0pt"`).
- Produces: functions in namespace `Papercut`: `pt(float): Length`, `px(float): Length`, `mm(float): Length`, `cm(float): Length`, `in(float): Length`.

- [ ] **Step 1: Write the failing tests**

`packages/core/tests/Value/LengthTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\Length;
use Papercut\Value\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Papercut\{cm, in, mm, pt, px};

final class LengthTest extends TestCase
{
    /** @return iterable<string, array{string, float}> */
    public static function validInputs(): iterable
    {
        yield 'points' => ['12pt', 12.0];
        yield 'pixels' => ['16px', 12.0];
        yield 'millimetres' => ['25.4mm', 72.0];
        yield 'centimetres' => ['2.54cm', 72.0];
        yield 'inches' => ['1in', 72.0];
        yield 'leading dot' => ['.5in', 36.0];
        yield 'negative' => ['-10pt', -10.0];
        yield 'bare zero' => ['0', 0.0];
        yield 'zero with decimals' => ['0.0', 0.0];
        yield 'surrounding whitespace' => ['  12pt ', 12.0];
    }

    #[DataProvider('validInputs')]
    public function test_parse_valid(string $input, float $points): void
    {
        self::assertEqualsWithDelta($points, Length::parse($input)->points, 1e-9);
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function invalidInputs(): iterable
    {
        yield 'space before unit' => ['20 mm', '20mm'];
        yield 'uppercase unit' => ['20MM', '20mm'];
        yield 'bare non-zero number' => ['12', '12pt'];
        yield 'unknown unit' => ['12em', null];
        yield 'empty' => ['', null];
        yield 'garbage' => ['wide', null];
        yield 'exponent' => ['1e3pt', null];
        yield 'percent is not a length' => ['50%', null];
    }

    #[DataProvider('invalidInputs')]
    public function test_parse_invalid(string $input, ?string $suggestion): void
    {
        try {
            Length::parse($input);
            self::fail('Expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertSame('length', $e->kind);
            self::assertSame($input, $e->input);
            self::assertSame($suggestion, $e->suggestion);
        }
    }

    public function test_helpers_and_units(): void
    {
        self::assertSame(12.0, pt(12)->points);
        self::assertSame(12.0, px(16)->points);
        self::assertEqualsWithDelta(72.0, mm(25.4)->points, 1e-9);
        self::assertEqualsWithDelta(72.0, cm(2.54)->points, 1e-9);
        self::assertSame(72.0, in(1)->points);
        self::assertSame(72.0, Length::of(1, Unit::In)->points);
    }

    public function test_arithmetic_and_equality(): void
    {
        self::assertSame(15.0, pt(10)->plus(pt(5))->points);
        self::assertSame(5.0, pt(10)->minus(pt(5))->points);
        self::assertSame(20.0, pt(10)->scale(2)->points);
        self::assertTrue(mm(25.4)->equals(in(1)));
        self::assertFalse(pt(1)->equals(pt(2)));
        self::assertSame(0.0, Length::zero()->points);
    }

    public function test_canonical_string(): void
    {
        self::assertSame('12pt', (string) pt(12));
        self::assertSame('56.6929pt', (string) mm(20));
        self::assertSame('0pt', (string) Length::zero());
        self::assertSame('-3.5pt', (string) pt(-3.5));
        self::assertTrue(Length::parse((string) mm(20))->equals(mm(20), 1e-3));
    }
}
```

- [ ] **Step 2: Run to see it fail**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist --filter LengthTest`
Expected: errors, `Class "Papercut\Value\Length" not found`.

- [ ] **Step 3: Implement `Unit`, `Length` and functions**

`packages/core/src/Value/Unit.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

enum Unit: string
{
    case Pt = 'pt';
    case Px = 'px';
    case Mm = 'mm';
    case Cm = 'cm';
    case In = 'in';

    /** Points per one of this unit. */
    public function points(): float
    {
        return match ($this) {
            self::Pt => 1.0,
            self::Px => 0.75,
            self::Mm => 72 / 25.4,
            self::Cm => 720 / 25.4,
            self::In => 72.0,
        };
    }
}
```

`packages/core/src/Value/Length.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;

final readonly class Length implements \Stringable
{
    private const string NUMBER = '-?(?:\d+(?:\.\d+)?|\.\d+)';

    private function __construct(public float $points)
    {
    }

    public static function of(float $value, Unit $unit): self
    {
        return new self($value * $unit->points());
    }

    public static function points(float $pt): self
    {
        return new self($pt);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function parse(string $input): self
    {
        $s = trim($input);
        if (preg_match('/^(' . self::NUMBER . ')(pt|px|mm|cm|in)$/', $s, $m)) {
            return self::of((float) $m[1], Unit::from($m[2]));
        }
        if (preg_match('/^' . self::NUMBER . '$/', $s)) {
            if ((float) $s === 0.0) {
                return self::zero();
            }
            throw InvalidValue::for('length', $input, $s . 'pt');
        }
        if (preg_match('/^(' . self::NUMBER . ')\s+(pt|px|mm|cm|in)$/i', $s, $m)) {
            throw InvalidValue::for('length', $input, $m[1] . strtolower($m[2]));
        }
        if (preg_match('/^(' . self::NUMBER . ')(pt|px|mm|cm|in)$/i', $s, $m)) {
            throw InvalidValue::for('length', $input, $m[1] . strtolower($m[2]));
        }
        throw InvalidValue::for('length', $input);
    }

    public function plus(Length $other): self
    {
        return new self($this->points + $other->points);
    }

    public function minus(Length $other): self
    {
        return new self($this->points - $other->points);
    }

    public function scale(float $factor): self
    {
        return new self($this->points * $factor);
    }

    public function equals(Length $other, float $epsilon = 1e-6): bool
    {
        return abs($this->points - $other->points) <= $epsilon;
    }

    public function __toString(): string
    {
        $n = rtrim(rtrim(number_format($this->points, 4, '.', ''), '0'), '.');
        return ($n === '-0' ? '0' : $n) . 'pt';
    }
}
```

Append to `packages/core/src/functions.php`:
```php
use Papercut\Value\Length;
use Papercut\Value\Unit;

function pt(float $value): Length { return Length::of($value, Unit::Pt); }
function px(float $value): Length { return Length::of($value, Unit::Px); }
function mm(float $value): Length { return Length::of($value, Unit::Mm); }
function cm(float $value): Length { return Length::of($value, Unit::Cm); }
function in(float $value): Length { return Length::of($value, Unit::In); }
```

- [ ] **Step 4: Run tests**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist --filter LengthTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Value packages/core/src/functions.php packages/core/tests/Value
git commit -m "feat(core): add Length value with strict string grammar"
```

---

### Task 3: `Percent`, `Fr`, `Auto`, `Tokens`, `TrackList`, `Edges`

**Files:**
- Create: `packages/core/src/Value/Percent.php`, `Fr.php`, `Auto.php`, `TrackList.php`, `Edges.php`, `Internal/Tokens.php`
- Modify: `packages/core/src/functions.php` (add `percent()`, `fr()`)
- Test: `packages/core/tests/Value/PercentFrAutoTest.php`, `TrackListTest.php`, `EdgesTest.php`

**Interfaces:**
- Consumes: `Length::parse()`, `InvalidValue`.
- Produces: `final readonly class Percent implements \Stringable { public float $value; static of(float): self; static parse(string): self; resolve(float $basisPoints): float; __toString "50%" }` — kind in errors: `percentage`. Suggestion for `"50 %"` → `"50%"`.
- Produces: `final readonly class Fr implements \Stringable { public float $value; static of(float): self; static parse(string): self; __toString "3fr" }` — value must be > 0, kind `fraction`. Suggestion for `"3 fr"` → `"3fr"`.
- Produces: `final readonly class Auto implements \Stringable { static instance(): self; __toString "auto" }` (single shared instance via a private static).
- Produces: `final class Papercut\Value\Internal\Tokens { /** @return list<string> */ static split(string $input, string $kind): array }` — splits on whitespace; throws `InvalidValue::for($kind, $input, <joined>)` if a numeric token is immediately followed by a token that is exactly a unit (`pt|px|mm|cm|in|fr|%`), with the suggestion being the whole input with that space removed; throws `InvalidValue::for($kind, $input)` on empty input.
- Produces: `final readonly class TrackList { /** @var list<Length|Percent|Fr|Auto> */ public array $tracks; static of(Length|Percent|Fr|Auto ...$tracks): self; static parse(string $input): self; count(): int }` — kind `column list`. Per-token parse order: `auto` → `Auto`, `/fr$/` → `Fr`, `/%$/` → `Percent`, else `Length`. A failing token rethrows as `InvalidValue::for('column list', $input, <input with that token replaced by the token's suggestion>)` when the token error had a suggestion, else without.
- Produces: `final readonly class Edges { public Length $top, $right, $bottom, $left; static all(Length): self; static of(Length $top, ?Length $right = null, ?Length $bottom = null, ?Length $left = null): self (CSS shorthand rules); static zero(): self; static parse(string): self; horizontal(): float; vertical(): float }` — kind `edges`; 1–4 tokens, more → InvalidValue.
- Produces: functions `Papercut\percent(float): Percent`, `Papercut\fr(float): Fr`.

- [ ] **Step 1: Write the failing tests**

`packages/core/tests/Value/PercentFrAutoTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\{Auto, Fr, Percent};
use PHPUnit\Framework\TestCase;

use function Papercut\{fr, percent};

final class PercentFrAutoTest extends TestCase
{
    public function test_percent(): void
    {
        self::assertSame(50.0, Percent::parse('50%')->value);
        self::assertSame(100.0, Percent::parse('50%')->resolve(200));
        self::assertSame('12.5%', (string) percent(12.5));
        $this->assertInvalid(fn () => Percent::parse('50 %'), 'percentage', '50%');
        $this->assertInvalid(fn () => Percent::parse('half'), 'percentage', null);
    }

    public function test_fr(): void
    {
        self::assertSame(3.0, Fr::parse('3fr')->value);
        self::assertSame(0.5, Fr::parse('.5fr')->value);
        self::assertSame('2fr', (string) fr(2));
        $this->assertInvalid(fn () => Fr::parse('3 fr'), 'fraction', '3fr');
        $this->assertInvalid(fn () => Fr::parse('0fr'), 'fraction', null);
        $this->assertInvalid(fn () => Fr::parse('-1fr'), 'fraction', null);
        $this->expectException(InvalidValue::class);
        Fr::of(0);
    }

    public function test_auto_is_singleton(): void
    {
        self::assertSame(Auto::instance(), Auto::instance());
        self::assertSame('auto', (string) Auto::instance());
    }

    private function assertInvalid(callable $fn, string $kind, ?string $suggestion): void
    {
        try {
            $fn();
            self::fail('Expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertSame($kind, $e->kind);
            self::assertSame($suggestion, $e->suggestion);
        }
    }
}
```

`packages/core/tests/Value/TrackListTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\{Auto, Fr, Length, Percent, TrackList};
use PHPUnit\Framework\TestCase;

use function Papercut\{fr, mm};

final class TrackListTest extends TestCase
{
    public function test_parse_mixed_tracks(): void
    {
        $t = TrackList::parse('3fr 25mm auto 10%');
        self::assertSame(4, $t->count());
        self::assertInstanceOf(Fr::class, $t->tracks[0]);
        self::assertInstanceOf(Length::class, $t->tracks[1]);
        self::assertInstanceOf(Auto::class, $t->tracks[2]);
        self::assertInstanceOf(Percent::class, $t->tracks[3]);
    }

    public function test_tolerates_extra_whitespace(): void
    {
        self::assertSame(2, TrackList::parse("  1fr \n\t 2fr ")->count());
    }

    public function test_split_unit_suggests_joined_value(): void
    {
        try {
            TrackList::parse('3 fr 25mm');
            self::fail('Expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertSame('column list', $e->kind);
            self::assertSame('3fr 25mm', $e->suggestion);
        }
    }

    public function test_bad_token_suggestion_is_spliced_into_whole_input(): void
    {
        try {
            TrackList::parse('1fr 25MM');
            self::fail('Expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertSame('1fr 25mm', $e->suggestion);
        }
    }

    public function test_empty_is_invalid(): void
    {
        $this->expectException(InvalidValue::class);
        TrackList::parse('   ');
    }

    public function test_of(): void
    {
        self::assertSame(2, TrackList::of(fr(1), mm(20))->count());
    }
}
```

`packages/core/tests/Value/EdgesTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\Edges;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class EdgesTest extends TestCase
{
    public function test_css_shorthand(): void
    {
        $this->assertEdges(Edges::parse('10pt'), 10, 10, 10, 10);
        $this->assertEdges(Edges::parse('10pt 20pt'), 10, 20, 10, 20);
        $this->assertEdges(Edges::parse('10pt 20pt 30pt'), 10, 20, 30, 20);
        $this->assertEdges(Edges::parse('10pt 20pt 30pt 40pt'), 10, 20, 30, 40);
        $this->assertEdges(Edges::parse('0'), 0, 0, 0, 0);
        $this->assertEdges(Edges::of(pt(1), pt(2)), 1, 2, 1, 2);
        $this->assertEdges(Edges::all(pt(5)), 5, 5, 5, 5);
        $this->assertEdges(Edges::zero(), 0, 0, 0, 0);
    }

    public function test_sums(): void
    {
        $e = Edges::parse('1pt 2pt 3pt 4pt');
        self::assertSame(6.0, $e->horizontal());
        self::assertSame(4.0, $e->vertical());
    }

    public function test_too_many_values(): void
    {
        $this->expectException(InvalidValue::class);
        Edges::parse('1pt 2pt 3pt 4pt 5pt');
    }

    public function test_split_unit_suggestion(): void
    {
        try {
            Edges::parse('20 mm 10mm');
            self::fail('Expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertSame('edges', $e->kind);
            self::assertSame('20mm 10mm', $e->suggestion);
        }
    }

    private function assertEdges(Edges $e, float $t, float $r, float $b, float $l): void
    {
        self::assertEqualsWithDelta([$t, $r, $b, $l], [$e->top->points, $e->right->points, $e->bottom->points, $e->left->points], 1e-9);
    }
}
```

- [ ] **Step 2: Run to see failures**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist --filter 'PercentFrAutoTest|TrackListTest|EdgesTest'`
Expected: errors (classes not found).

- [ ] **Step 3: Implement**

`packages/core/src/Value/Internal/Tokens.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value\Internal;

use Papercut\Exception\InvalidValue;

/** @internal */
final class Tokens
{
    /** @return list<string> */
    public static function split(string $input, string $kind): array
    {
        $tokens = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            throw InvalidValue::for($kind, $input);
        }
        foreach ($tokens as $i => $tok) {
            $next = $tokens[$i + 1] ?? null;
            if ($next !== null
                && preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $tok)
                && preg_match('/^(pt|px|mm|cm|in|fr|%)$/i', $next)) {
                $joined = $tokens;
                $joined[$i] = $tok . strtolower($next);
                unset($joined[$i + 1]);
                throw InvalidValue::for($kind, $input, implode(' ', $joined));
            }
        }
        return $tokens;
    }
}
```

`packages/core/src/Value/Percent.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;

final readonly class Percent implements \Stringable
{
    private function __construct(public float $value)
    {
    }

    public static function of(float $value): self
    {
        return new self($value);
    }

    public static function parse(string $input): self
    {
        $s = trim($input);
        if (preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))%$/', $s, $m)) {
            return new self((float) $m[1]);
        }
        if (preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))\s+%$/', $s, $m)) {
            throw InvalidValue::for('percentage', $input, $m[1] . '%');
        }
        throw InvalidValue::for('percentage', $input);
    }

    public function resolve(float $basisPoints): float
    {
        return $basisPoints * $this->value / 100;
    }

    public function __toString(): string
    {
        return rtrim(rtrim(number_format($this->value, 4, '.', ''), '0'), '.') . '%';
    }
}
```

`packages/core/src/Value/Fr.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;

final readonly class Fr implements \Stringable
{
    private function __construct(public float $value)
    {
    }

    public static function of(float $value): self
    {
        if ($value <= 0) {
            throw InvalidValue::for('fraction', $value . 'fr');
        }
        return new self($value);
    }

    public static function parse(string $input): self
    {
        $s = trim($input);
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)fr$/', $s, $m) && (float) $m[1] > 0) {
            return new self((float) $m[1]);
        }
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)\s*fr$/i', $s, $m) && (float) $m[1] > 0) {
            throw InvalidValue::for('fraction', $input, $m[1] . 'fr');
        }
        throw InvalidValue::for('fraction', $input);
    }

    public function __toString(): string
    {
        return rtrim(rtrim(number_format($this->value, 4, '.', ''), '0'), '.') . 'fr';
    }
}
```

`packages/core/src/Value/Auto.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

final class Auto implements \Stringable
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function __toString(): string
    {
        return 'auto';
    }
}
```
(`Auto` is not `readonly` because readonly classes cannot declare static properties; it has no instance state.)

`packages/core/src/Value/TrackList.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\Internal\Tokens;

final readonly class TrackList
{
    /** @param list<Length|Percent|Fr|Auto> $tracks */
    private function __construct(public array $tracks)
    {
    }

    public static function of(Length|Percent|Fr|Auto ...$tracks): self
    {
        if ($tracks === []) {
            throw InvalidValue::for('column list', '');
        }
        return new self(array_values($tracks));
    }

    public static function parse(string $input): self
    {
        $tokens = Tokens::split($input, 'column list');
        $tracks = [];
        foreach ($tokens as $i => $tok) {
            try {
                $tracks[] = match (true) {
                    $tok === 'auto' => Auto::instance(),
                    str_ends_with(strtolower($tok), 'fr') => Fr::parse($tok),
                    str_ends_with($tok, '%') => Percent::parse($tok),
                    default => Length::parse($tok),
                };
            } catch (InvalidValue $e) {
                if ($e->suggestion === null) {
                    throw InvalidValue::for('column list', $input);
                }
                $fixed = $tokens;
                $fixed[$i] = $e->suggestion;
                throw InvalidValue::for('column list', $input, implode(' ', $fixed));
            }
        }
        return new self($tracks);
    }

    public function count(): int
    {
        return count($this->tracks);
    }
}
```
(`Fr::parse`'s second branch uses `\s*` with `/i`, so it suggests a fix for both `3 fr` and `3FR`.)

`packages/core/src/Value/Edges.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\Internal\Tokens;

final readonly class Edges
{
    private function __construct(
        public Length $top,
        public Length $right,
        public Length $bottom,
        public Length $left,
    ) {
    }

    public static function all(Length $l): self
    {
        return new self($l, $l, $l, $l);
    }

    public static function zero(): self
    {
        return self::all(Length::zero());
    }

    public static function of(Length $top, ?Length $right = null, ?Length $bottom = null, ?Length $left = null): self
    {
        $right ??= $top;
        $bottom ??= $top;
        $left ??= $right;
        return new self($top, $right, $bottom, $left);
    }

    public static function parse(string $input): self
    {
        $tokens = Tokens::split($input, 'edges');
        if (count($tokens) > 4) {
            throw InvalidValue::for('edges', $input);
        }
        $lengths = [];
        foreach ($tokens as $i => $tok) {
            try {
                $lengths[] = Length::parse($tok);
            } catch (InvalidValue $e) {
                if ($e->suggestion === null) {
                    throw InvalidValue::for('edges', $input);
                }
                $fixed = $tokens;
                $fixed[$i] = $e->suggestion;
                throw InvalidValue::for('edges', $input, implode(' ', $fixed));
            }
        }
        return self::of(...$lengths);
    }

    public function horizontal(): float
    {
        return $this->left->points + $this->right->points;
    }

    public function vertical(): float
    {
        return $this->top->points + $this->bottom->points;
    }
}
```
Note: CSS 3-value shorthand is `top right bottom` with left = right; `of()` above implements that because `$left ??= $right` runs after `$bottom ??= $top`.

Append to `functions.php`:
```php
use Papercut\Value\Fr;
use Papercut\Value\Percent;

function percent(float $value): Percent { return Percent::of($value); }
function fr(float $value): Fr { return Fr::of($value); }
```
(Move all `use` statements to the top of the file under the namespace line.)

- [ ] **Step 4: Run tests**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist`
Expected: PASS (all value tests).

- [ ] **Step 5: Commit**

```bash
git add packages/core/src packages/core/tests
git commit -m "feat(core): add Percent, Fr, Auto, TrackList and Edges values"
```

---

### Task 4: `Color` and the generated palette

**Files:**
- Create: `packages/core/tools/generate-palette.php`, `packages/core/resources/palette.php` (generated), `packages/core/src/Value/Color.php`
- Test: `packages/core/tests/Value/ColorTest.php`

**Interfaces:**
- Produces: `final readonly class Color implements \Stringable { public int $r, $g, $b; public float $alpha; static rgb(int $r, int $g, int $b, float $alpha = 1.0): self; static hex(string): self; static named(string): self; static parse(string): self; static black(): self; static white(): self; static transparent(): self; withAlpha(float): self; isOpaque(): bool; isTransparent(): bool; /** @return array{float,float,float} */ toUnitRgb(): array; __toString → "#rrggbb" or "#rrggbbaa" when alpha < 1 }`.
- Error kind: `color`. Unknown names get a suggestion when `levenshtein` ≤ 2 to a palette name (closest, ties → alphabetical first). `"slate500"` → suggestion `"slate-500"` (insert hyphen before trailing digits if that name exists).
- `rgb()` / `rgba()` functional syntax: `rgb(51, 65, 85)`, `rgb(51 65 85)`, `rgba(51, 65, 85, 0.5)`, `rgb(51 65 85 / 0.5)`. Channels 0–255 integers, alpha 0–1. Out of range → InvalidValue.
- Alpha from `#rrggbbaa` = `aa/255`, rounded to 4 decimals.

- [ ] **Step 1: Create the palette generator and generate**

`packages/core/tools/generate-palette.php`:
```php
<?php

declare(strict_types=1);

// Regenerates resources/palette.php from Tailwind CSS v3.4.17's color palette (MIT).
// Usage: php packages/core/tools/generate-palette.php

$source = 'https://unpkg.com/tailwindcss@3.4.17/lib/public/colors.js';
$js = file_get_contents($source);
if ($js === false) {
    fwrite(STDERR, "Could not fetch $source\n");
    exit(1);
}
preg_match_all('/^    (\w+): \{\n((?:        \d+: "#[0-9a-f]{3,6}",?\n)+)    \}/m', $js, $families, PREG_SET_ORDER);
$palette = ['black' => '#000000', 'white' => '#ffffff'];
foreach ($families as [, $name, $body]) {
    preg_match_all('/(\d+): "(#[0-9a-f]{3,6})"/', $body, $shades, PREG_SET_ORDER);
    foreach ($shades as [, $shade, $hex]) {
        $palette["$name-$shade"] = strlen($hex) === 4
            ? '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3]
            : $hex;
    }
}
if (count($palette) !== 244) {
    fwrite(STDERR, 'Unexpected palette size ' . count($palette) . "\n");
    exit(1);
}
$out = "<?php\n\n// Generated by tools/generate-palette.php from Tailwind CSS v3.4.17 (MIT). Do not edit.\n\nreturn " . var_export($palette, true) . ";\n";
file_put_contents(__DIR__ . '/../resources/palette.php', $out);
echo 'Wrote ' . count($palette) . " colors\n";
```

Run: `php packages/core/tools/generate-palette.php`
Expected: `Wrote 244 colors`; `packages/core/resources/palette.php` exists and contains `'slate-700' => '#334155'`.

- [ ] **Step 2: Write the failing tests**

`packages/core/tests/Value/ColorTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Value;

use Papercut\Exception\InvalidValue;
use Papercut\Value\Color;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    /** @return iterable<string, array{string, int, int, int, float}> */
    public static function valid(): iterable
    {
        yield 'short hex' => ['#fa0', 255, 170, 0, 1.0];
        yield 'long hex' => ['#334155', 51, 65, 85, 1.0];
        yield 'uppercase hex' => ['#33AAFF', 51, 170, 255, 1.0];
        yield 'hex with alpha' => ['#33415580', 51, 65, 85, 0.502];
        yield 'rgb commas' => ['rgb(51, 65, 85)', 51, 65, 85, 1.0];
        yield 'rgb spaces' => ['rgb(51 65 85)', 51, 65, 85, 1.0];
        yield 'rgba' => ['rgba(51, 65, 85, 0.5)', 51, 65, 85, 0.5];
        yield 'rgb slash alpha' => ['rgb(51 65 85 / 0.25)', 51, 65, 85, 0.25];
        yield 'named' => ['slate-700', 51, 65, 85, 1.0];
        yield 'black' => ['black', 0, 0, 0, 1.0];
        yield 'white' => ['white', 255, 255, 255, 1.0];
        yield 'transparent' => ['transparent', 0, 0, 0, 0.0];
        yield 'whitespace' => ['  #000 ', 0, 0, 0, 1.0];
    }

    #[DataProvider('valid')]
    public function test_parse_valid(string $in, int $r, int $g, int $b, float $a): void
    {
        $c = Color::parse($in);
        self::assertSame([$r, $g, $b], [$c->r, $c->g, $c->b]);
        self::assertEqualsWithDelta($a, $c->alpha, 0.001);
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function invalid(): iterable
    {
        yield 'typo in name' => ['slaet-700', 'slate-700'];
        yield 'missing hyphen' => ['slate500', 'slate-500'];
        yield 'unknown shade' => ['slate-550', null];
        yield 'bad hex length' => ['#12345', null];
        yield 'channel out of range' => ['rgb(300, 0, 0)', null];
        yield 'alpha out of range' => ['rgba(0, 0, 0, 2)', null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('invalid')]
    public function test_parse_invalid(string $in, ?string $suggestion): void
    {
        try {
            Color::parse($in);
            self::fail('Expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertSame('color', $e->kind);
            self::assertSame($suggestion, $e->suggestion);
        }
    }

    public function test_helpers(): void
    {
        self::assertSame('#334155', (string) Color::rgb(51, 65, 85));
        self::assertSame('#33415580', (string) Color::rgb(51, 65, 85, 0.5));
        self::assertTrue(Color::black()->isOpaque());
        self::assertTrue(Color::transparent()->isTransparent());
        self::assertSame(0.5, Color::white()->withAlpha(0.5)->alpha);
        self::assertEqualsWithDelta([0.2, 0.2549, 0.3333], Color::hex('#334155')->toUnitRgb(), 0.0001);
        $this->expectException(InvalidValue::class);
        Color::rgb(256, 0, 0);
    }
}
```

- [ ] **Step 3: Run to see it fail**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist --filter ColorTest`
Expected: errors, class not found.

- [ ] **Step 4: Implement `Color`**

```php
<?php

declare(strict_types=1);

namespace Papercut\Value;

use Papercut\Exception\InvalidValue;

final readonly class Color implements \Stringable
{
    private function __construct(
        public int $r,
        public int $g,
        public int $b,
        public float $alpha,
    ) {
    }

    public static function rgb(int $r, int $g, int $b, float $alpha = 1.0): self
    {
        foreach ([$r, $g, $b] as $ch) {
            if ($ch < 0 || $ch > 255) {
                throw InvalidValue::for('color', sprintf('rgb(%d, %d, %d)', $r, $g, $b));
            }
        }
        if ($alpha < 0 || $alpha > 1) {
            throw InvalidValue::for('color', sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha));
        }
        return new self($r, $g, $b, round($alpha, 4));
    }

    public static function black(): self { return new self(0, 0, 0, 1.0); }
    public static function white(): self { return new self(255, 255, 255, 1.0); }
    public static function transparent(): self { return new self(0, 0, 0, 0.0); }

    public static function hex(string $input): self
    {
        $h = ltrim(trim($input), '#');
        if (!preg_match('/^[0-9a-fA-F]+$/', $h) || !in_array(strlen($h), [3, 6, 8], true)) {
            throw InvalidValue::for('color', $input);
        }
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        $alpha = strlen($h) === 8 ? hexdec(substr($h, 6, 2)) / 255 : 1.0;
        return self::rgb((int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2)), $alpha);
    }

    public static function named(string $name): self
    {
        $key = strtolower(trim($name));
        if ($key === 'transparent') {
            return self::transparent();
        }
        $palette = self::palette();
        if (isset($palette[$key])) {
            return self::hex($palette[$key]);
        }
        throw InvalidValue::for('color', $name, self::suggestName($key, $palette));
    }

    public static function parse(string $input): self
    {
        $s = trim($input);
        if ($s === '') {
            throw InvalidValue::for('color', $input);
        }
        if ($s[0] === '#') {
            return self::hex($s);
        }
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,?\s*(\d{1,3})\s*,?\s*(\d{1,3})\s*(?:[,\/]\s*(\d*\.?\d+)\s*)?\)$/i', $s, $m)) {
            try {
                return self::rgb((int) $m[1], (int) $m[2], (int) $m[3], isset($m[4]) ? (float) $m[4] : 1.0);
            } catch (InvalidValue) {
                throw InvalidValue::for('color', $input);
            }
        }
        try {
            return self::named($s);
        } catch (InvalidValue $e) {
            throw InvalidValue::for('color', $input, $e->suggestion);
        }
    }

    public function withAlpha(float $alpha): self
    {
        return self::rgb($this->r, $this->g, $this->b, $alpha);
    }

    public function isOpaque(): bool { return $this->alpha >= 1.0; }
    public function isTransparent(): bool { return $this->alpha <= 0.0; }

    /** @return array{float,float,float} */
    public function toUnitRgb(): array
    {
        return [$this->r / 255, $this->g / 255, $this->b / 255];
    }

    public function __toString(): string
    {
        $hex = sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
        return $this->isOpaque() ? $hex : $hex . sprintf('%02x', (int) round($this->alpha * 255));
    }

    /** @return array<string,string> */
    private static function palette(): array
    {
        static $palette = null;
        return $palette ??= require __DIR__ . '/../../resources/palette.php';
    }

    /** @param array<string,string> $palette */
    private static function suggestName(string $key, array $palette): ?string
    {
        if (preg_match('/^([a-z]+)(\d+)$/', $key, $m) && isset($palette[$m[1] . '-' . $m[2]])) {
            return $m[1] . '-' . $m[2];
        }
        // A known family with an unknown shade ("slate-550") is not a typo: no suggestion.
        if (preg_match('/^([a-z]+)-\d+$/', $key, $m) && isset($palette[$m[1] . '-500'])) {
            return null;
        }
        $best = null;
        $bestDistance = 3;
        foreach (array_keys($palette) as $name) {
            $d = levenshtein($key, $name);
            if ($d < $bestDistance || ($d === $bestDistance && $best !== null && strcmp($name, $best) < 0)) {
                $best = $name;
                $bestDistance = $d;
            }
        }
        return $bestDistance <= 2 ? $best : null;
    }
}
```
(The `$bestDistance = 3` start value means only names within distance 2 are ever suggested.)

- [ ] **Step 5: Run tests**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist --filter ColorTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/core/tools packages/core/resources/palette.php packages/core/src/Value/Color.php packages/core/tests/Value/ColorTest.php
git commit -m "feat(core): add Color value with hex/rgb/named palette parsing"
```

---

### Task 5: Backend interfaces and paint ops

**Files:**
- Create: `packages/core/src/Backend/{Backend,FontHandle,ImageHandle}.php`
- Create: `packages/core/src/Paint/{Op,TextRun,Rect,Line,DrawImage,Clip,Opacity,Link,PageDisplayList,DocumentMeta}.php`
- Create: `packages/core/tests/Support/FakeFont.php`
- Test: `packages/core/tests/Paint/OpsTest.php`

**Interfaces:**
- Produces:
```php
namespace Papercut\Backend;
interface FontHandle {
    /** Stable identifier, e.g. sha1 of the font file path. */
    public function id(): string;
    /** Advance width in points of $text at $size, matching exactly what the backend draws. */
    public function advance(string $text, float $size, float $letterSpacing = 0.0): float;
    /** Distance above the baseline in points at $size (positive). */
    public function ascent(float $size): float;
    /** Distance below the baseline in points at $size (positive). */
    public function descent(float $size): float;
    /** True when every non-whitespace character has a glyph. */
    public function covers(string $text): bool;
}
interface ImageHandle {
    public function id(): string;
    public function widthPx(): int;
    public function heightPx(): int;
}
interface Backend {
    /** @throws \Papercut\Exception\FontNotFound */
    public function loadFont(string $path): FontHandle;
    /** @throws \Papercut\Exception\ImageUnreadable */
    public function loadImageFile(string $path): ImageHandle;
    /** @throws \Papercut\Exception\ImageUnreadable */
    public function loadImageBytes(string $bytes): ImageHandle;
    /** @param list<\Papercut\Paint\PageDisplayList> $pages */
    public function render(\Papercut\Paint\DocumentMeta $meta, array $pages): string;
}
```
- Produces (namespace `Papercut\Paint`, all `final readonly`, all implement `Op` except `PageDisplayList`/`DocumentMeta`; coordinates top-left origin points):
  - `interface Op {}`
  - `TextRun(float $x, float $baseline, string $text, FontHandle $font, float $size, Color $color, float $letterSpacing = 0.0)`; `$size` must be > 0 (else `\InvalidArgumentException`).
  - `Rect(float $x, float $y, float $width, float $height, ?Color $fill = null, ?Color $stroke = null, float $strokeWidth = 0.0, float $radius = 0.0)`; negative width/height/strokeWidth/radius → `\InvalidArgumentException`.
  - `Line(float $x1, float $y1, float $x2, float $y2, Color $color, float $width = 1.0, bool $dashed = false)`.
  - `DrawImage(float $x, float $y, float $width, float $height, ImageHandle $image)`.
  - `Clip(float $x, float $y, float $width, float $height, array $ops)` with `/** @var list<Op> */ public array $ops`.
  - `Opacity(float $alpha, array $ops)`; alpha outside [0,1] → `\InvalidArgumentException`.
  - `Link(float $x, float $y, float $width, float $height, string $uri)`.
  - `PageDisplayList(float $width, float $height, array $ops)`; width/height must be > 0.
  - `DocumentMeta(?string $title = null, ?string $author = null, ?string $subject = null, ?string $keywords = null, ?string $lang = null, ?\DateTimeImmutable $createdAt = null)`.
- Produces test helper `Papercut\Tests\Support\FakeFont implements FontHandle`: every character advances `0.5 * size` (+ letterSpacing), ascent `0.8*size`, descent `0.2*size`, covers everything except characters ≥ U+3000; it counts `advance()` calls in a public `int $advanceCalls`.

- [ ] **Step 1: Write the failing tests**

`packages/core/tests/Paint/OpsTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Paint;

use Papercut\Paint\{Clip, DocumentMeta, Line, Opacity, PageDisplayList, Rect, TextRun};
use Papercut\Tests\Support\FakeFont;
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

final class OpsTest extends TestCase
{
    public function test_ops_hold_their_values(): void
    {
        $run = new TextRun(10, 20, 'Hi', new FakeFont(), 12, Color::black());
        self::assertSame('Hi', $run->text);
        self::assertSame(0.0, $run->letterSpacing);

        $rect = new Rect(0, 0, 100, 50, fill: Color::white(), radius: 4);
        self::assertNull($rect->stroke);
        self::assertSame(4.0, $rect->radius);

        $line = new Line(0, 0, 10, 0, Color::black(), dashed: true);
        self::assertTrue($line->dashed);

        $clip = new Clip(0, 0, 10, 10, [$rect, new Opacity(0.5, [$line])]);
        self::assertCount(2, $clip->ops);

        $page = new PageDisplayList(595, 842, [$run, $clip]);
        self::assertCount(2, $page->ops);

        $meta = new DocumentMeta(title: 'T');
        self::assertSame('T', $meta->title);
        self::assertNull($meta->createdAt);
    }

    public function test_rect_rejects_negative_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Rect(0, 0, -1, 10);
    }

    public function test_opacity_rejects_out_of_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Opacity(1.5, []);
    }

    public function test_page_rejects_zero_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PageDisplayList(0, 842, []);
    }

    public function test_text_run_rejects_non_positive_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextRun(0, 0, 'x', new FakeFont(), 0, Color::black());
    }

    public function test_fake_font_metrics(): void
    {
        $f = new FakeFont();
        self::assertSame(12.0, $f->advance('ab', 12));
        self::assertSame(14.0, $f->advance('ab', 12, 1.0));
        self::assertSame(2, $f->advanceCalls);
        self::assertFalse($f->covers('漢'));
    }
}
```

- [ ] **Step 2: Run to see failure**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist --filter OpsTest` → errors (classes missing).

- [ ] **Step 3: Implement interfaces, ops and `FakeFont`**

Write the three backend interfaces exactly as in **Interfaces** above. Example op implementations (the rest follow the same pattern: constructor property promotion, `final readonly`, validation in the constructor body):

```php
<?php

declare(strict_types=1);

namespace Papercut\Paint;

use Papercut\Value\Color;

final readonly class Rect implements Op
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?Color $fill = null,
        public ?Color $stroke = null,
        public float $strokeWidth = 0.0,
        public float $radius = 0.0,
    ) {
        if ($width < 0 || $height < 0 || $strokeWidth < 0 || $radius < 0) {
            throw new \InvalidArgumentException('Rect dimensions, stroke width and radius must be non-negative.');
        }
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Papercut\Paint;

final readonly class Clip implements Op
{
    /** @param list<Op> $ops */
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public array $ops,
    ) {
    }
}
```

`TextRun` throws `\InvalidArgumentException('Text size must be positive.')` when `$size <= 0`. `Opacity` throws when alpha not in [0,1]. `PageDisplayList` throws when width or height ≤ 0. `Op` is `interface Op {}`. `DocumentMeta` has no validation.

`packages/core/tests/Support/FakeFont.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Support;

use Papercut\Backend\FontHandle;

final class FakeFont implements FontHandle
{
    public int $advanceCalls = 0;

    public function id(): string { return 'fake'; }

    public function advance(string $text, float $size, float $letterSpacing = 0.0): float
    {
        $this->advanceCalls++;
        $n = mb_strlen($text, 'UTF-8');
        return $n * 0.5 * $size + $n * $letterSpacing;
    }

    public function ascent(float $size): float { return 0.8 * $size; }
    public function descent(float $size): float { return 0.2 * $size; }

    public function covers(string $text): bool
    {
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            if (mb_ord($ch, 'UTF-8') >= 0x3000) {
                return false;
            }
        }
        return true;
    }
}
```

- [ ] **Step 4: Run tests** → PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Backend packages/core/src/Paint packages/core/tests
git commit -m "feat(core): add backend interfaces and paint ops"
```

---

### Task 6: Bundle Inter fonts

**Files:**
- Create: `packages/core/resources/fonts/Inter/Inter-Regular.ttf`, `Inter-Italic.ttf`, `Inter-Bold.ttf`, `Inter-BoldItalic.ttf`, `LICENSE.txt`
- Test: `packages/core/tests/Text/BundledFontsTest.php`

**Interfaces:**
- Produces: constant path convention `packages/core/resources/fonts/Inter/Inter-{Regular,Italic,Bold,BoldItalic}.ttf` used by Task 8 and tests.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use PHPUnit\Framework\TestCase;

final class BundledFontsTest extends TestCase
{
    public function test_inter_faces_and_license_are_bundled(): void
    {
        $dir = __DIR__ . '/../../resources/fonts/Inter';
        foreach (['Regular', 'Italic', 'Bold', 'BoldItalic'] as $face) {
            $path = "$dir/Inter-$face.ttf";
            self::assertFileExists($path);
            // TrueType outlines (glyf), not CFF: sfnt version 0x00010000
            self::assertSame("\x00\x01\x00\x00", (string) file_get_contents($path, length: 4));
        }
        self::assertStringContainsString('SIL OPEN FONT LICENSE', strtoupper((string) file_get_contents("$dir/LICENSE.txt")));
    }
}
```

- [ ] **Step 2: Run** → FAIL (files missing).

- [ ] **Step 3: Download Inter 4.1 and copy the four static TTFs + license**

```bash
tmp=$(mktemp -d)
curl -sL -o "$tmp/inter.zip" https://github.com/rsms/inter/releases/download/v4.1/Inter-4.1.zip
unzip -q -o "$tmp/inter.zip" 'extras/ttf/Inter-Regular.ttf' 'extras/ttf/Inter-Italic.ttf' 'extras/ttf/Inter-Bold.ttf' 'extras/ttf/Inter-BoldItalic.ttf' 'LICENSE.txt' -d "$tmp"
mkdir -p packages/core/resources/fonts/Inter
cp "$tmp"/extras/ttf/Inter-{Regular,Italic,Bold,BoldItalic}.ttf "$tmp/LICENSE.txt" packages/core/resources/fonts/Inter/
rm -rf "$tmp"
```

- [ ] **Step 4: Run** → PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/core/resources/fonts packages/core/tests/Text/BundledFontsTest.php
git commit -m "feat(core): bundle Inter 4.1 (OFL) as the default font family"
```

---

### Task 7: `PhpPdfFont` and `PhpPdfImage` (backend handles)

**Files:**
- Create: `packages/core/src/Backend/PhpPdf/PhpPdfFont.php`, `packages/core/src/Backend/PhpPdf/PhpPdfImage.php`, `packages/core/src/Backend/PhpPdf/PhpPdfBackend.php` (load methods only; `render()` throws `\LogicException('Not implemented')` until Task 9)
- Test: `packages/core/tests/Backend/PhpPdfFontTest.php`, `packages/core/tests/Backend/PhpPdfImageTest.php`

**Interfaces:**
- Consumes: `FontHandle`, `ImageHandle`, `Backend`, `FontNotFound`, `ImageUnreadable`.
- Produces: `final class PhpPdfFont implements FontHandle` with `public function pdfFont(): \Dskripchenko\PhpPdf\Pdf\PdfFont` (used by Task 9). Constructed only via `PhpPdfBackend::loadFont()`.
- Produces: `final class PhpPdfImage implements ImageHandle` with `public function pdfImage(): \Dskripchenko\PhpPdf\Image\PdfImage`.
- Produces: `final class PhpPdfBackend implements Backend` (constructor has no required args). `loadFont($path)` caches handles by `realpath`, so the same path returns the same instance.

**Measurement rule (must match `Page::showEmbeddedText`):** glyphs = `PdfFont::shapedGlyphs($text)`; widths in 1/1000 em = Σ `widthOfGlyphPdfUnits(gid)`. If `$letterSpacing == 0.0`, subtract Σ `kerningPdfUnits(prevGid, gid)` over consecutive pairs (the backend's TJ array applies them as positive-means-left offsets). If `$letterSpacing != 0.0`, kerning is **not** applied (the backend drops TJ kerning when Tc is set) and the result adds `count(glyphs) * $letterSpacing`. advance = units / 1000 × size (+ spacing term). Empty string → 0.0.
- `ascent($size)` = `ttf()->ascent() / ttf()->unitsPerEm() * $size`; `descent($size)` = `abs(ttf()->descent()) / ttf()->unitsPerEm() * $size`.
- `covers($text)` = `PdfFont::supportsText($text)`.
- `id()` = `sha1(realpath)`.
- Image `id()` = `sha1` of the image bytes. `widthPx()`/`heightPx()` from `PdfImage::$widthPx/$heightPx`. Any `\Throwable` from `PdfImage::fromPath/fromBytes` becomes `ImageUnreadable` with the original as previous; message `Cannot read image "<path>": <reason>` (for bytes: `Cannot read image bytes: <reason>`).
- Missing/unreadable font path → `FontNotFound('Font file not found: <path>')`. A file that fails TTF parsing → `FontNotFound('Font file is not a valid TrueType/OpenType font: <path>')` with previous.

- [ ] **Step 1: Write the failing tests**

`packages/core/tests/Backend/PhpPdfFontTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Backend;

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Backend\PhpPdf\PhpPdfFont;
use Papercut\Exception\FontNotFound;
use PHPUnit\Framework\TestCase;

final class PhpPdfFontTest extends TestCase
{
    private const string INTER = __DIR__ . '/../../resources/fonts/Inter/Inter-Regular.ttf';

    private function font(): PhpPdfFont
    {
        $f = (new PhpPdfBackend())->loadFont(self::INTER);
        self::assertInstanceOf(PhpPdfFont::class, $f);
        return $f;
    }

    public function test_empty_string_has_zero_advance(): void
    {
        self::assertSame(0.0, $this->font()->advance('', 12));
    }

    public function test_whitespace_has_positive_advance(): void
    {
        self::assertGreaterThan(0.0, $this->font()->advance(' ', 12));
    }

    public function test_advance_scales_linearly_with_size(): void
    {
        $f = $this->font();
        self::assertEqualsWithDelta($f->advance('Hello', 10) * 2, $f->advance('Hello', 20), 1e-9);
    }

    public function test_kerning_tightens_av_pair(): void
    {
        $f = $this->font();
        self::assertLessThan($f->advance('A', 100) + $f->advance('V', 100), $f->advance('AV', 100));
    }

    public function test_letter_spacing_disables_kerning_and_adds_per_glyph(): void
    {
        $f = $this->font();
        $unkerned = $f->advance('A', 100) + $f->advance('V', 100);
        self::assertEqualsWithDelta($unkerned + 2 * 3.0, $f->advance('AV', 100, 3.0), 1e-9);
    }

    public function test_metrics(): void
    {
        $f = $this->font();
        self::assertGreaterThan(0.0, $f->ascent(12));
        self::assertGreaterThan(0.0, $f->descent(12));
        self::assertLessThan(12 * 1.5, $f->ascent(12) + $f->descent(12));
    }

    public function test_covers(): void
    {
        $f = $this->font();
        self::assertTrue($f->covers('Café naïve'));
        self::assertFalse($f->covers('漢字'));
        self::assertTrue($f->covers(''));
    }

    public function test_same_path_returns_same_handle(): void
    {
        $b = new PhpPdfBackend();
        self::assertSame($b->loadFont(self::INTER), $b->loadFont(self::INTER));
        self::assertSame($b->loadFont(self::INTER)->id(), (new PhpPdfBackend())->loadFont(self::INTER)->id());
    }

    public function test_missing_font(): void
    {
        $this->expectException(FontNotFound::class);
        (new PhpPdfBackend())->loadFont('/nope/missing.ttf');
    }

    public function test_invalid_font_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pc') . '.ttf';
        file_put_contents($tmp, 'not a font');
        try {
            $this->expectException(FontNotFound::class);
            (new PhpPdfBackend())->loadFont($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
```

`packages/core/tests/Backend/PhpPdfImageTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Backend;

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Exception\ImageUnreadable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('gd')]
final class PhpPdfImageTest extends TestCase
{
    public static function pngBytes(int $w = 40, int $h = 20, bool $alpha = false): string
    {
        $im = imagecreatetruecolor($w, $h);
        if ($alpha) {
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 0, 0, 64));
        } else {
            imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 200));
        }
        ob_start();
        imagepng($im);
        return (string) ob_get_clean();
    }

    public static function jpegBytes(int $w = 30, int $h = 60): string
    {
        $im = imagecreatetruecolor($w, $h);
        ob_start();
        imagejpeg($im);
        return (string) ob_get_clean();
    }

    public function test_png_and_jpeg_dimensions(): void
    {
        $b = new PhpPdfBackend();
        $png = $b->loadImageBytes(self::pngBytes(40, 20, alpha: true));
        self::assertSame([40, 20], [$png->widthPx(), $png->heightPx()]);
        $jpg = $b->loadImageBytes(self::jpegBytes(30, 60));
        self::assertSame([30, 60], [$jpg->widthPx(), $jpg->heightPx()]);
    }

    public function test_load_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pc') . '.png';
        file_put_contents($tmp, self::pngBytes());
        try {
            self::assertSame(40, (new PhpPdfBackend())->loadImageFile($tmp)->widthPx());
        } finally {
            @unlink($tmp);
        }
    }

    public function test_unreadable_file(): void
    {
        $this->expectException(ImageUnreadable::class);
        (new PhpPdfBackend())->loadImageFile('/nope/missing.png');
    }

    public function test_unsupported_bytes(): void
    {
        $this->expectException(ImageUnreadable::class);
        (new PhpPdfBackend())->loadImageBytes('GIF89a...');
    }
}
```

- [ ] **Step 2: Run** → FAIL (classes missing).

- [ ] **Step 3: Implement**

`packages/core/src/Backend/PhpPdf/PhpPdfFont.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Papercut\Backend\FontHandle;

final class PhpPdfFont implements FontHandle
{
    public function __construct(
        private readonly PdfFont $font,
        private readonly string $id,
    ) {
    }

    public function pdfFont(): PdfFont
    {
        return $this->font;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function advance(string $text, float $size, float $letterSpacing = 0.0): float
    {
        if ($text === '') {
            return 0.0;
        }
        $units = 0;
        $count = 0;
        $prev = null;
        foreach ($this->font->shapedGlyphs($text) as $glyph) {
            $gid = $glyph['gid'];
            $units += $this->font->widthOfGlyphPdfUnits($gid);
            if ($letterSpacing === 0.0 && $prev !== null) {
                $units -= $this->font->kerningPdfUnits($prev, $gid);
            }
            $prev = $gid;
            $count++;
        }
        return $units / 1000 * $size + ($letterSpacing !== 0.0 ? $count * $letterSpacing : 0.0);
    }

    public function ascent(float $size): float
    {
        $ttf = $this->font->ttf();
        return $ttf->ascent() / $ttf->unitsPerEm() * $size;
    }

    public function descent(float $size): float
    {
        $ttf = $this->font->ttf();
        return abs($ttf->descent()) / $ttf->unitsPerEm() * $size;
    }

    public function covers(string $text): bool
    {
        return $this->font->supportsText($text);
    }
}
```

`packages/core/src/Backend/PhpPdf/PhpPdfImage.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Image\PdfImage;
use Papercut\Backend\ImageHandle;

final class PhpPdfImage implements ImageHandle
{
    public function __construct(
        private readonly PdfImage $image,
        private readonly string $id,
    ) {
    }

    public function pdfImage(): PdfImage { return $this->image; }
    public function id(): string { return $this->id; }
    public function widthPx(): int { return $this->image->widthPx; }
    public function heightPx(): int { return $this->image->heightPx; }
}
```

`packages/core/src/Backend/PhpPdf/PhpPdfBackend.php` (load half):
```php
<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Font\Ttf\TtfFile;
use Dskripchenko\PhpPdf\Image\PdfImage;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Papercut\Backend\Backend;
use Papercut\Backend\FontHandle;
use Papercut\Backend\ImageHandle;
use Papercut\Exception\FontNotFound;
use Papercut\Exception\ImageUnreadable;
use Papercut\Paint\DocumentMeta;

final class PhpPdfBackend implements Backend
{
    /** @var array<string, PhpPdfFont> */
    private array $fonts = [];

    public function loadFont(string $path): FontHandle
    {
        $real = realpath($path);
        if ($real === false || !is_readable($real) || !is_file($real)) {
            throw new FontNotFound('Font file not found: ' . $path);
        }
        if (isset($this->fonts[$real])) {
            return $this->fonts[$real];
        }
        try {
            $font = new PdfFont(TtfFile::fromFile($real));
        } catch (\Throwable $e) {
            throw new FontNotFound('Font file is not a valid TrueType/OpenType font: ' . $path, 0, $e);
        }
        return $this->fonts[$real] = new PhpPdfFont($font, sha1($real));
    }

    public function loadImageFile(string $path): ImageHandle
    {
        $bytes = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            throw new ImageUnreadable(sprintf('Cannot read image "%s": file not found or not readable', $path));
        }
        try {
            return new PhpPdfImage(PdfImage::fromBytes($bytes), sha1($bytes));
        } catch (\Throwable $e) {
            throw new ImageUnreadable(sprintf('Cannot read image "%s": %s', $path, $e->getMessage()), 0, $e);
        }
    }

    public function loadImageBytes(string $bytes): ImageHandle
    {
        try {
            return new PhpPdfImage(PdfImage::fromBytes($bytes), sha1($bytes));
        } catch (\Throwable $e) {
            throw new ImageUnreadable('Cannot read image bytes: ' . $e->getMessage(), 0, $e);
        }
    }

    public function render(DocumentMeta $meta, array $pages): string
    {
        throw new \LogicException('Not implemented');
    }
}
```

- [ ] **Step 4: Run tests** → PASS. If `test_invalid_font_file` does not throw because `TtfFile::fromFile` accepts garbage lazily, force validation by calling `$font->ttf()->unitsPerEm()` inside the `try` block.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Backend/PhpPdf packages/core/tests/Backend
git commit -m "feat(core): add php-pdf backed font and image handles"
```

---

### Task 8: `FontStyle`, `FontRegistry`, `CachedFont`

**Files:**
- Create: `packages/core/src/Text/FontStyle.php`, `FontRegistry.php`, `CachedFont.php`
- Test: `packages/core/tests/Text/FontRegistryTest.php`, `packages/core/tests/Text/CachedFontTest.php`

**Interfaces:**
- Consumes: `Backend::loadFont()`, `FontHandle`, `FontNotFound`.
- Produces: `enum FontStyle: string { case Normal = 'normal'; case Italic = 'italic'; }`
- Produces: `final class CachedFont implements FontHandle { __construct(FontHandle $inner); inner(): FontHandle; }` — caches `advance()` per `(text, letterSpacing)` at size 1.0 and scales linearly (`advance(t, s, ls)` = `cached(t, ls_per_size)`: compute as `inner->advance(t, 1.0, 0.0)` cached per text, times size, plus, when `ls != 0`, recompute via `inner->advance(t, size, ls)` with its own cache key `t|size|ls` since kerning is dropped). Other methods delegate. `id()` delegates.
- Produces: `final class FontRegistry`:
  - `__construct(Backend $backend)`
  - `static withDefaults(Backend $backend): self` — registers family `Inter` faces 400 normal, 400 italic, 700 normal, 700 italic from `resources/fonts/Inter/`.
  - `register(string $family, string $path, int $weight = 400, FontStyle $style = FontStyle::Normal): self` — weight must be 1–1000 (else `\InvalidArgumentException`); path must exist (else `FontNotFound("Font file not found: <path>")`); family names compare case-insensitively; re-registering same family/weight/style replaces.
  - `resolve(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal): CachedFont` — lazily loads via backend; returns the same instance for the same resolved face.
  - `has(string $family): bool`; `families(): list<string>` (original casing, registration order).
  - Unknown family → `FontNotFound('Unknown font family "<f>". Registered families: Inter, ...')`.
- **Matching (CSS Fonts 4 §5.2, simplified):** pick faces of the requested style; if none, use the other style. Among them, choose weight: if desired is 400–500 inclusive: weights ≥ desired and ≤ 500 ascending, then weights < desired descending, then weights > 500 ascending. If desired < 400: weights ≤ desired descending, then > desired ascending. If desired > 500: weights ≥ desired ascending, then < desired descending.

- [ ] **Step 1: Write the failing tests**

`packages/core/tests/Text/FontRegistryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Exception\FontNotFound;
use Papercut\Text\{FontRegistry, FontStyle};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FontRegistryTest extends TestCase
{
    private const string DIR = __DIR__ . '/../../resources/fonts/Inter';

    private function registry(): FontRegistry
    {
        // Same family registered at 100/400/700/900 using bundled files as stand-ins.
        return (new FontRegistry(new PhpPdfBackend()))
            ->register('Test', self::DIR . '/Inter-Regular.ttf', 100)
            ->register('Test', self::DIR . '/Inter-Italic.ttf', 400)
            ->register('Test', self::DIR . '/Inter-Bold.ttf', 700)
            ->register('Test', self::DIR . '/Inter-BoldItalic.ttf', 900);
    }

    /** @return iterable<string, array{int, string}> */
    public static function weights(): iterable
    {
        // desired => expected file (by face registered at that weight)
        yield 'exact 400' => [400, 'Italic'];
        yield '450 falls below to 400' => [450, 'Italic'];
        yield '500 falls below to 400' => [500, 'Italic'];
        yield '300 goes down to 100' => [300, 'Regular'];
        yield '600 goes up to 700' => [600, 'Bold'];
        yield '800 goes up to 900' => [800, 'BoldItalic'];
        yield '950 has nothing above, goes down to 900' => [950, 'BoldItalic'];
        yield '50 has nothing below, goes up to 100' => [50, 'Regular'];
    }

    #[DataProvider('weights')]
    public function test_weight_matching(int $desired, string $face): void
    {
        $r = $this->registry();
        $expected = (new PhpPdfBackend())->loadFont(self::DIR . "/Inter-$face.ttf")->id();
        self::assertSame($expected, $r->resolve('Test', $desired)->id());
    }

    public function test_400_range_prefers_up_to_500_first(): void
    {
        $r = (new FontRegistry(new PhpPdfBackend()))
            ->register('W', self::DIR . '/Inter-Regular.ttf', 300)
            ->register('W', self::DIR . '/Inter-Bold.ttf', 500);
        $expected = (new PhpPdfBackend())->loadFont(self::DIR . '/Inter-Bold.ttf')->id();
        self::assertSame($expected, $r->resolve('W', 400)->id());
    }

    public function test_italic_falls_back_to_normal(): void
    {
        $r = (new FontRegistry(new PhpPdfBackend()))->register('N', self::DIR . '/Inter-Regular.ttf');
        self::assertSame($r->resolve('N'), $r->resolve('N', 400, FontStyle::Italic));
    }

    public function test_defaults_and_case_insensitive_family(): void
    {
        $r = FontRegistry::withDefaults(new PhpPdfBackend());
        self::assertTrue($r->has('inter'));
        self::assertSame(['Inter'], $r->families());
        self::assertNotSame($r->resolve('Inter'), $r->resolve('Inter', 700));
        self::assertNotSame($r->resolve('Inter'), $r->resolve('Inter', 400, FontStyle::Italic));
        self::assertSame($r->resolve('inter'), $r->resolve('INTER'));
    }

    public function test_unknown_family_lists_known(): void
    {
        $this->expectException(FontNotFound::class);
        $this->expectExceptionMessage('Unknown font family "Helvetica". Registered families: Inter');
        FontRegistry::withDefaults(new PhpPdfBackend())->resolve('Helvetica');
    }

    public function test_register_missing_file_fails_fast(): void
    {
        $this->expectException(FontNotFound::class);
        (new FontRegistry(new PhpPdfBackend()))->register('X', '/nope.ttf');
    }

    public function test_register_rejects_bad_weight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FontRegistry(new PhpPdfBackend()))->register('X', self::DIR . '/Inter-Regular.ttf', 0);
    }
}
```
Note: `resolve()` returns a `CachedFont`, whose `id()` delegates to the inner handle, so comparing ids against a fresh backend's handle works because ids are `sha1(realpath)`.

`packages/core/tests/Text/CachedFontTest.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use Papercut\Tests\Support\FakeFont;
use Papercut\Text\CachedFont;
use PHPUnit\Framework\TestCase;

final class CachedFontTest extends TestCase
{
    public function test_caches_and_scales(): void
    {
        $inner = new FakeFont();
        $f = new CachedFont($inner);
        self::assertSame(6.0, $f->advance('ab', 6));
        self::assertSame(12.0, $f->advance('ab', 12));
        self::assertSame(24.0, $f->advance('ab', 24));
        self::assertSame(1, $inner->advanceCalls);
    }

    public function test_letter_spacing_bypasses_scaled_cache_but_is_cached_itself(): void
    {
        $inner = new FakeFont();
        $f = new CachedFont($inner);
        self::assertSame(14.0, $f->advance('ab', 12, 1.0));
        self::assertSame(14.0, $f->advance('ab', 12, 1.0));
        self::assertSame(1, $inner->advanceCalls);
    }

    public function test_delegates(): void
    {
        $inner = new FakeFont();
        $f = new CachedFont($inner);
        self::assertSame($inner, $f->inner());
        self::assertSame('fake', $f->id());
        self::assertSame(8.0, $f->ascent(10));
        self::assertSame(2.0, $f->descent(10));
        self::assertFalse($f->covers('漢'));
    }
}
```

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Implement**

`packages/core/src/Text/CachedFont.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Text;

use Papercut\Backend\FontHandle;

final class CachedFont implements FontHandle
{
    /** @var array<string, float> unit-size advances keyed by text */
    private array $unit = [];
    /** @var array<string, float> */
    private array $spaced = [];

    public function __construct(private readonly FontHandle $inner)
    {
    }

    public function inner(): FontHandle { return $this->inner; }
    public function id(): string { return $this->inner->id(); }

    public function advance(string $text, float $size, float $letterSpacing = 0.0): float
    {
        if ($letterSpacing !== 0.0) {
            $key = $text . "\0" . $size . "\0" . $letterSpacing;
            return $this->spaced[$key] ??= $this->inner->advance($text, $size, $letterSpacing);
        }
        return ($this->unit[$text] ??= $this->inner->advance($text, 1.0)) * $size;
    }

    public function ascent(float $size): float { return $this->inner->ascent($size); }
    public function descent(float $size): float { return $this->inner->descent($size); }
    public function covers(string $text): bool { return $this->inner->covers($text); }
}
```

`packages/core/src/Text/FontStyle.php`: as in **Interfaces**.

`packages/core/src/Text/FontRegistry.php`:
```php
<?php

declare(strict_types=1);

namespace Papercut\Text;

use Papercut\Backend\Backend;
use Papercut\Exception\FontNotFound;

final class FontRegistry
{
    /** @var array<string, array{name: string, faces: array<string, string>}> family key => faces "weight|style" => path */
    private array $families = [];
    /** @var array<string, CachedFont> keyed by path */
    private array $loaded = [];

    public function __construct(private readonly Backend $backend)
    {
    }

    public static function withDefaults(Backend $backend): self
    {
        $dir = __DIR__ . '/../../resources/fonts/Inter';
        return (new self($backend))
            ->register('Inter', "$dir/Inter-Regular.ttf", 400)
            ->register('Inter', "$dir/Inter-Italic.ttf", 400, FontStyle::Italic)
            ->register('Inter', "$dir/Inter-Bold.ttf", 700)
            ->register('Inter', "$dir/Inter-BoldItalic.ttf", 700, FontStyle::Italic);
    }

    public function register(string $family, string $path, int $weight = 400, FontStyle $style = FontStyle::Normal): self
    {
        if ($weight < 1 || $weight > 1000) {
            throw new \InvalidArgumentException("Font weight must be between 1 and 1000, got $weight.");
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new FontNotFound('Font file not found: ' . $path);
        }
        $key = strtolower($family);
        $this->families[$key] ??= ['name' => $family, 'faces' => []];
        $this->families[$key]['faces'][$weight . '|' . $style->value] = $path;
        return $this;
    }

    public function has(string $family): bool
    {
        return isset($this->families[strtolower($family)]);
    }

    /** @return list<string> */
    public function families(): array
    {
        return array_values(array_map(static fn (array $f): string => $f['name'], $this->families));
    }

    public function resolve(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal): CachedFont
    {
        $entry = $this->families[strtolower($family)] ?? null;
        if ($entry === null) {
            throw new FontNotFound(sprintf(
                'Unknown font family "%s". Registered families: %s',
                $family,
                implode(', ', $this->families()),
            ));
        }
        $path = $this->match($entry['faces'], $weight, $style);
        return $this->loaded[$path] ??= new CachedFont($this->backend->loadFont($path));
    }

    /** @param array<string, string> $faces */
    private function match(array $faces, int $desired, FontStyle $style): string
    {
        $byStyle = [];
        foreach ($faces as $k => $path) {
            [$w, $s] = explode('|', $k);
            $byStyle[$s][(int) $w] = $path;
        }
        $candidates = $byStyle[$style->value] ?? $byStyle[$style === FontStyle::Italic ? 'normal' : 'italic'];
        $weights = array_keys($candidates);
        sort($weights);
        $up = static fn (int $from, int $to = 1000): array => array_values(array_filter($weights, static fn (int $w): bool => $w >= $from && $w <= $to));
        $down = static fn (int $below): array => array_reverse(array_values(array_filter($weights, static fn (int $w): bool => $w < $below)));
        $order = match (true) {
            $desired >= 400 && $desired <= 500 => [...$up($desired, 500), ...$down($desired), ...$up(501)],
            $desired < 400 => [...array_reverse(array_values(array_filter($weights, static fn (int $w): bool => $w <= $desired))), ...$up($desired + 1)],
            default => [...$up($desired), ...$down($desired)],
        };
        return $candidates[$order[0]];
    }
}
```

- [ ] **Step 4: Run tests** → PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Text packages/core/tests/Text
git commit -m "feat(core): add font registry with CSS weight matching and cached measurement"
```

---

### Task 9: `PhpPdfBackend::render()`

**Files:**
- Modify: `packages/core/src/Backend/PhpPdf/PhpPdfBackend.php`
- Test: `packages/core/tests/Backend/PhpPdfBackendRenderTest.php`

**Interfaces:**
- Consumes: all `Paint` ops, `PhpPdfFont::pdfFont()`, `PhpPdfImage::pdfImage()`, `CachedFont::inner()`, `Color::toUnitRgb()`.
- Produces: `render(DocumentMeta $meta, list<PageDisplayList> $pages): string`.

**Mapping rules** (H = page height; our (x, y) top-left → PDF (x, H − y − h) bottom-left):
- Zero pages → `\InvalidArgumentException('Cannot render a document with no pages.')`.
- Page: `$doc->addPage(customDimensionsPt: [$page->width, $page->height])` with the default portrait orientation.
- Meta: `$doc->metadata(title, author, subject, keywords, creator: 'Papercut', producer: 'Papercut', creationDate: $meta->createdAt ?? new \DateTimeImmutable())`; `setLang($meta->lang)` when non-null.
- `TextRun`: skip if `text === ''`; unwrap `CachedFont` → must be `PhpPdfFont` (else `\LogicException`); `showEmbeddedText($text, $x, H − $baseline, $pdfFont, $size, r, g, b, $letterSpacing)`. Non-opaque color → wrap in `withOpacity(alpha, alpha, fn)`; fully transparent → skip.
- `Rect`: fill (if non-null and not transparent) via `fillRoundedRect(x, H−y−h, w, h, radius, r,g,b)`; then stroke (if non-null, not transparent, strokeWidth > 0) via `strokeRoundedRect(x, H−y−h, w, h, radius, strokeWidth, r,g,b)`. Each wrapped in `withOpacity` when its color alpha < 1.
- `Line`: y' = H − y. Solid: `strokeLine`; dashed: `strokeDashedLine(x1, y1', x2, y2', width, r, g, b, [3 * width, 2 * width])`. Alpha as above.
- `DrawImage`: unwrap `PhpPdfImage` (else `\LogicException`); `drawImage(img, x, H−y−h, w, h)`.
- `Clip`: `withClipRect(x, H−y−h, w, h, fn (Page $p) => render children)`.
- `Opacity`: `withOpacity(alpha, alpha, fn () => render children)`; alpha 1.0 → render children directly.
- `Link`: `addExternalLink(x, H−y−h, w, h, uri)`.
- Return `$doc->toBytes()`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Papercut\Tests\Backend;

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Paint\{Clip, DocumentMeta, DrawImage, Line, Link, Opacity, PageDisplayList, Rect, TextRun};
use Papercut\Tests\Support\{FakeFont, PdfInspector};
use Papercut\Text\FontRegistry;
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

final class PhpPdfBackendRenderTest extends TestCase
{
    private PhpPdfBackend $backend;
    private FontRegistry $fonts;

    protected function setUp(): void
    {
        $this->backend = new PhpPdfBackend();
        $this->fonts = FontRegistry::withDefaults($this->backend);
    }

    private function meta(): DocumentMeta
    {
        return new DocumentMeta(title: 'Café ✓ Report', author: 'Zoë', lang: 'en-US', createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function render(array $ops, float $w = 595.0, float $h = 842.0): string
    {
        return $this->backend->render($this->meta(), [new PageDisplayList($w, $h, $ops)]);
    }

    public function test_text_lands_at_top_left_coordinates(): void
    {
        $font = $this->fonts->resolve('Inter');
        $pdf = $this->render([new TextRun(72, 100, 'Hello', $font, 12, Color::black())]);
        PdfInspector::check($pdf);
        $words = PdfInspector::words($pdf);
        self::assertSame('Hello', $words[0]['text']);
        self::assertEqualsWithDelta(72.0, $words[0]['xMin'], 0.5);
        self::assertLessThan(100.0, $words[0]['yMin']);
        self::assertGreaterThan(100.0, $words[0]['yMax']);
    }

    public function test_measured_advance_matches_drawn_width(): void
    {
        $font = $this->fonts->resolve('Inter');
        foreach ([0.0, 2.0] as $spacing) {
            $w = $font->advance('AVATAR', 24, $spacing);
            $pdf = $this->render([new TextRun(500 - $w, 100, 'AVATAR', $font, 24, Color::black(), $spacing)]);
            $word = PdfInspector::words($pdf)[0];
            // pdftotext's xMax includes trailing letter spacing; allow 1pt + spacing.
            self::assertEqualsWithDelta(500.0, $word['xMax'], 1.0 + $spacing, "letterSpacing=$spacing");
        }
    }

    public function test_every_op_type_produces_a_valid_pdf(): void
    {
        $font = $this->fonts->resolve('Inter', 700);
        $png = $this->backend->loadImageBytes(PhpPdfImageTest::pngBytes(alpha: true));
        $pdf = $this->render([
            new Rect(50, 50, 200, 100, fill: Color::parse('slate-100'), stroke: Color::parse('slate-400'), strokeWidth: 1, radius: 6),
            new Line(50, 170, 250, 170, Color::parse('red-500'), 0.5),
            new Line(50, 180, 250, 180, Color::parse('red-500'), 0.5, dashed: true),
            new DrawImage(300, 50, 80, 40, $png),
            new Clip(50, 200, 100, 20, [new TextRun(50, 215, 'Clipped long text that overflows', $font, 12, Color::black())]),
            new Opacity(0.5, [new Rect(300, 200, 50, 50, fill: Color::black())]),
            new Link(50, 50, 200, 100, 'https://example.com/papercut'),
        ]);
        PdfInspector::check($pdf);
        $qdf = PdfInspector::qdf($pdf);
        self::assertStringContainsString('/URI (https://example.com/papercut)', $qdf);
        self::assertStringContainsString('/SMask', $qdf);
    }

    public function test_alpha_color_draws_translucent(): void
    {
        $pdf = $this->render([new Rect(10, 10, 50, 50, fill: Color::parse('#33415580'))]);
        PdfInspector::check($pdf);
        self::assertMatchesRegularExpression('/\/ca 0?\.50?2?/', PdfInspector::qdf($pdf));
    }

    public function test_unicode_metadata_and_lang(): void
    {
        $pdf = $this->render([new Rect(0, 0, 10, 10, fill: Color::black())]);
        $info = PdfInspector::info($pdf);
        self::assertSame('Café ✓ Report', $info['Title']);
        self::assertSame('Zoë', $info['Author']);
        self::assertSame('Papercut', $info['Creator']);
        self::assertStringContainsString('/Lang (en-US)', PdfInspector::qdf($pdf));
    }

    public function test_empty_and_uncovered_text_do_not_break_output(): void
    {
        $font = $this->fonts->resolve('Inter');
        $pdf = $this->render([
            new TextRun(10, 20, '', $font, 12, Color::black()),
            new TextRun(10, 40, '   ', $font, 12, Color::black()),
            new TextRun(10, 60, '漢字 😀 ok', $font, 12, Color::black()),
        ]);
        PdfInspector::check($pdf);
        self::assertStringContainsString('ok', PdfInspector::text($pdf));
    }

    public function test_multiple_pages_and_page_sizes(): void
    {
        $font = $this->fonts->resolve('Inter');
        $pdf = $this->backend->render($this->meta(), [
            new PageDisplayList(595, 842, [new TextRun(72, 72, 'One', $font, 12, Color::black())]),
            new PageDisplayList(612, 792, [new TextRun(72, 72, 'Two', $font, 12, Color::black())]),
        ]);
        PdfInspector::check($pdf);
        self::assertSame('2', PdfInspector::info($pdf)['Pages']);
        self::assertSame('Two', PdfInspector::words($pdf, 2)[0]['text']);
    }

    public function test_output_is_deterministic(): void
    {
        $font = $this->fonts->resolve('Inter');
        $ops = [new TextRun(72, 72, 'Same bytes', $font, 12, Color::black())];
        self::assertSame($this->render($ops), (new PhpPdfBackend())->render($this->meta(), [new PageDisplayList(595, 842, [
            new TextRun(72, 72, 'Same bytes', FontRegistry::withDefaults(new PhpPdfBackend())->resolve('Inter'), 12, Color::black()),
        ])]));
    }

    public function test_no_pages_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->backend->render($this->meta(), []);
    }

    public function test_foreign_font_handle_is_rejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->render([new TextRun(0, 10, 'x', new FakeFont(), 12, Color::black())]);
    }
}
```

- [ ] **Step 2: Run** → FAIL (`Not implemented`).

- [ ] **Step 3: Implement `render()`**

Replace the stub in `PhpPdfBackend` with:
```php
use Dskripchenko\PhpPdf\Pdf\Document as PdfDocument;
use Dskripchenko\PhpPdf\Pdf\Page as PdfPage;
use Papercut\Backend\FontHandle;
use Papercut\Paint\{Clip, DocumentMeta, DrawImage, Line, Link, Op, Opacity, PageDisplayList, Rect, TextRun};
use Papercut\Text\CachedFont;
use Papercut\Value\Color;

    public function render(DocumentMeta $meta, array $pages): string
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('Cannot render a document with no pages.');
        }
        $doc = PdfDocument::new();
        $doc->metadata(
            title: $meta->title,
            author: $meta->author,
            subject: $meta->subject,
            keywords: $meta->keywords,
            creator: 'Papercut',
            producer: 'Papercut',
            creationDate: $meta->createdAt ?? new \DateTimeImmutable(),
        );
        if ($meta->lang !== null) {
            $doc->setLang($meta->lang);
        }
        foreach ($pages as $page) {
            $pdfPage = $doc->addPage(customDimensionsPt: [$page->width, $page->height]);
            $this->drawOps($pdfPage, $page->height, $page->ops);
        }
        return $doc->toBytes();
    }

    /** @param list<Op> $ops */
    private function drawOps(PdfPage $p, float $h, array $ops): void
    {
        foreach ($ops as $op) {
            match (true) {
                $op instanceof TextRun => $this->drawText($p, $h, $op),
                $op instanceof Rect => $this->drawRect($p, $h, $op),
                $op instanceof Line => $this->drawLine($p, $h, $op),
                $op instanceof DrawImage => $p->drawImage($this->pdfImage($op), $op->x, $h - $op->y - $op->height, $op->width, $op->height),
                $op instanceof Clip => $p->withClipRect($op->x, $h - $op->y - $op->height, $op->width, $op->height, fn () => $this->drawOps($p, $h, $op->ops)),
                $op instanceof Opacity => $op->alpha >= 1.0
                    ? $this->drawOps($p, $h, $op->ops)
                    : $p->withOpacity($op->alpha, $op->alpha, fn () => $this->drawOps($p, $h, $op->ops)),
                $op instanceof Link => $p->addExternalLink($op->x, $h - $op->y - $op->height, $op->width, $op->height, $op->uri),
                default => throw new \LogicException('Unsupported op ' . $op::class),
            };
        }
    }

    private function drawText(PdfPage $p, float $h, TextRun $t): void
    {
        if ($t->text === '' || $t->color->isTransparent()) {
            return;
        }
        $font = $this->pdfFont($t->font);
        [$r, $g, $b] = $t->color->toUnitRgb();
        $this->withAlpha($p, $t->color, fn () => $p->showEmbeddedText($t->text, $t->x, $h - $t->baseline, $font, $t->size, $r, $g, $b, $t->letterSpacing));
    }

    private function drawRect(PdfPage $p, float $h, Rect $o): void
    {
        $y = $h - $o->y - $o->height;
        if ($o->fill !== null && !$o->fill->isTransparent()) {
            [$r, $g, $b] = $o->fill->toUnitRgb();
            $this->withAlpha($p, $o->fill, fn () => $p->fillRoundedRect($o->x, $y, $o->width, $o->height, $o->radius, $r, $g, $b));
        }
        if ($o->stroke !== null && !$o->stroke->isTransparent() && $o->strokeWidth > 0) {
            [$r, $g, $b] = $o->stroke->toUnitRgb();
            $this->withAlpha($p, $o->stroke, fn () => $p->strokeRoundedRect($o->x, $y, $o->width, $o->height, $o->radius, $o->strokeWidth, $r, $g, $b));
        }
    }

    private function drawLine(PdfPage $p, float $h, Line $l): void
    {
        if ($l->color->isTransparent()) {
            return;
        }
        [$r, $g, $b] = $l->color->toUnitRgb();
        $this->withAlpha($p, $l->color, fn () => $l->dashed
            ? $p->strokeDashedLine($l->x1, $h - $l->y1, $l->x2, $h - $l->y2, $l->width, $r, $g, $b, [3 * $l->width, 2 * $l->width])
            : $p->strokeLine($l->x1, $h - $l->y1, $l->x2, $h - $l->y2, $l->width, $r, $g, $b));
    }

    private function withAlpha(PdfPage $p, Color $c, \Closure $draw): void
    {
        $c->isOpaque() ? $draw() : $p->withOpacity($c->alpha, $c->alpha, $draw);
    }

    private function pdfFont(FontHandle $font): \Dskripchenko\PhpPdf\Pdf\PdfFont
    {
        $inner = $font instanceof CachedFont ? $font->inner() : $font;
        if (!$inner instanceof PhpPdfFont) {
            throw new \LogicException('PhpPdfBackend can only draw fonts it loaded; got ' . $inner::class);
        }
        return $inner->pdfFont();
    }

    private function pdfImage(DrawImage $op): \Dskripchenko\PhpPdf\Image\PdfImage
    {
        if (!$op->image instanceof PhpPdfImage) {
            throw new \LogicException('PhpPdfBackend can only draw images it loaded; got ' . $op->image::class);
        }
        return $op->image->pdfImage();
    }
```
Notes for the implementer:
- `withClipRect` passes the page to its callback; `withOpacity`'s callback takes no args. The closures above ignore args, which is fine in PHP.
- `match` arms must be expressions; `drawOps` returns void, so wrap void calls if PHPStan complains (`$this->drawX(...)` inside match is allowed; the match result is discarded).
- If `test_alpha_color_draws_translucent` fails because the library formats alpha differently (e.g. `/ca 0.502`), adjust only the regex. The assertion is "an ExtGState with fill alpha ≈ 0.5 exists".
- If `pdfinfo` doesn't print `Creator`, check `qdf` for `/Creator`.

- [ ] **Step 4: Run all tests** → PASS. Run PHPStan → clean.

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Backend/PhpPdf/PhpPdfBackend.php packages/core/tests/Backend/PhpPdfBackendRenderTest.php
git commit -m "feat(core): render paint ops to PDF through php-pdf backend"
```

---

### Task 10: Example, dependency fence, and CI

**Files:**
- Create: `examples/core/hello.php`
- Create: `packages/core/tests/ArchitectureTest.php`
- Create: `.github/workflows/core.yml`
- Create: `packages/core/README.md` (short: what it is, install, status "pre-release, low-level API only")

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Write the architecture fence test**

```php
<?php

declare(strict_types=1);

namespace Papercut\Tests;

use PHPUnit\Framework\TestCase;

final class ArchitectureTest extends TestCase
{
    public function test_only_phppdf_backend_imports_dskripchenko(): void
    {
        $src = realpath(__DIR__ . '/../src');
        self::assertIsString($src);
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if (str_starts_with($path, $src . '/Backend/PhpPdf/')) {
                continue;
            }
            if (str_contains((string) file_get_contents($path), 'Dskripchenko')) {
                $offenders[] = substr($path, strlen($src) + 1);
            }
        }
        self::assertSame([], $offenders, 'Only src/Backend/PhpPdf may reference Dskripchenko\\*');
    }
}
```

Run it → PASS (fence holds).

- [ ] **Step 2: Write `examples/core/hello.php`**

```php
<?php

declare(strict_types=1);

// Low-level demo: draws a card with the backend directly. Run: php examples/core/hello.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Paint\{DocumentMeta, Line, Link, PageDisplayList, Rect, TextRun};
use Papercut\Text\FontRegistry;
use Papercut\Value\Color;

use function Papercut\mm;

$backend = new PhpPdfBackend();
$fonts = FontRegistry::withDefaults($backend);
$bold = $fonts->resolve('Inter', 700);
$regular = $fonts->resolve('Inter');

$x = mm(20)->points;
$title = 'Papercut';
$ops = [
    new Rect($x, $x, 595 - 2 * $x, 120, fill: Color::parse('slate-50'), stroke: Color::parse('slate-200'), strokeWidth: 1, radius: 8),
    new TextRun($x + 20, $x + 48, $title, $bold, 28, Color::parse('slate-900')),
    new Line($x + 20, $x + 62, $x + 20 + $bold->advance($title, 28), $x + 62, Color::parse('indigo-500'), 2),
    new TextRun($x + 20, $x + 92, 'Component-based PDF layout for modern PHP.', $regular, 12, Color::parse('slate-600')),
    new Link($x, $x, 595 - 2 * $x, 120, 'https://github.com/'),
];

$pdf = $backend->render(new DocumentMeta(title: 'Hello Papercut'), [new PageDisplayList(595.28, 841.89, $ops)]);
file_put_contents(__DIR__ . '/hello.pdf', $pdf);
echo 'Wrote examples/core/hello.pdf (' . strlen($pdf) . " bytes)\n";
```

Run: `php examples/core/hello.php` → `Wrote examples/core/hello.pdf (...)`; `qpdf --check examples/core/hello.pdf` → no errors. (The `.pdf` is git-ignored.)

- [ ] **Step 3: Write CI workflow `.github/workflows/core.yml`**

```yaml
name: core

on:
  push:
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.4', '8.5']
    defaults:
      run:
        working-directory: packages/core
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, zlib, dom, gd
          coverage: none
      - run: sudo apt-get update && sudo apt-get install -y qpdf poppler-utils
      - run: composer install --no-interaction --no-progress
      - run: vendor/bin/phpunit
      - run: vendor/bin/phpstan analyse --memory-limit=1G
```

- [ ] **Step 4: Write `packages/core/README.md`**

```markdown
# papercut/core

Component-based, page-aware PDF layout engine for modern PHP.

> **Status: pre-release.** This package currently ships the foundation only: typed values
> (`Length`, `Color`, `Edges`, `TrackList`), backend-neutral paint ops, a font registry with
> the bundled Inter family, and a PDF backend built on `dskripchenko/php-pdf`.
> The component tree and layout engine land next.

## Requirements

PHP 8.4+, `ext-mbstring`, `ext-zlib`, `ext-dom`.

## License

MIT. Bundled Inter font: SIL Open Font License 1.1 (see `resources/fonts/Inter/LICENSE.txt`).
Color palette derived from Tailwind CSS (MIT).
```

- [ ] **Step 5: Run full suite + PHPStan, then commit**

Run: `packages/core/vendor/bin/phpunit -c packages/core/phpunit.xml.dist` → PASS; `cd packages/core && vendor/bin/phpstan analyse --memory-limit=1G` → no errors.

```bash
git add examples .github packages/core/tests/ArchitectureTest.php packages/core/README.md
git commit -m "chore(core): add low-level example, dependency fence test and CI"
```
