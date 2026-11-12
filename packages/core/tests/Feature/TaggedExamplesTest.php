<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\Document;
use Papercut\Export\PdfOptions;
use Papercut\Tests\Support\StructTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Every example document, rendered as a tagged PDF: it has content in its structure and raises no warning, and the
 * roles of a few are pinned as an outline. Needs a php-pdf that writes tagged PDFs.
 *
 * Re-record the outlines after an intended change: UPDATE_STRUCTURE_GOLDEN=1 vendor/bin/phpunit --filter TaggedExamplesTest
 */
#[RequiresPhpExtension('gd')]
final class TaggedExamplesTest extends TestCase
{
    private const array OUTLINED = ['certificate', 'guide'];

    /** @return iterable<string, array{string}> */
    public static function examples(): iterable
    {
        foreach (glob(__DIR__ . '/../../../../examples/core/documents/*.php') ?: [] as $file) {
            yield basename($file, '.php') => [$file];
        }
    }

    /** @return array{list<array<string, mixed>>, list<string>} the elements below Document, and the warnings */
    private static function render(string $file): array
    {
        $document = (static fn () => require $file)();
        self::assertInstanceOf(Document::class, $document);
        $warnings = [];
        $bytes = $document->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'))
            ->toPdf(new PdfOptions(tagged: true, onWarning: static function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            }))->bytes();
        $root = StructTree::of($bytes);
        self::assertCount(1, $root);
        self::assertSame('Document', $root[0]['role']);

        return [$root[0]['kids'], $warnings];
    }

    #[DataProvider('examples')]
    public function test_the_tagged_example_has_content_and_no_warnings(string $file): void
    {
        [$elements, $warnings] = self::render($file);
        self::assertNotSame([], $elements, 'the structure tree holds nothing below Document');
        self::assertSame([], $warnings);
    }

    /** @return iterable<string, array{string}> */
    public static function outlined(): iterable
    {
        foreach (self::OUTLINED as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('outlined')]
    public function test_the_structure_roles_match_the_golden(string $name): void
    {
        [$elements] = self::render(__DIR__ . "/../../../../examples/core/documents/$name.php");
        $outline = "Document\n" . preg_replace('/^/m', '  ', StructTree::outline($elements));
        $golden = __DIR__ . "/golden/structure-$name.txt";
        if (getenv('UPDATE_STRUCTURE_GOLDEN') === '1') {
            file_put_contents($golden, $outline);
        }
        self::assertFileExists($golden);
        self::assertSame(file_get_contents($golden), $outline);
    }
}
