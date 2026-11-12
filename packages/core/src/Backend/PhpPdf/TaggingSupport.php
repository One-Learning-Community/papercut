<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Pdf\Document as PdfDocument;
use Dskripchenko\PhpPdf\Pdf\Page as PdfPage;

/**
 * Whether the installed php-pdf can write a tagged PDF. Tagging needs methods that older releases
 * lack, and some are marked @internal upstream, so it is checked rather than assumed.
 *
 * @internal Part of Papercut's PDF writer. It may change in any release.
 */
final class TaggingSupport
{
    /** Methods tagging calls, by class, with the number of parameters each needs. */
    private const array REQUIRED = [
        [PdfDocument::class, 'enablePdfUa', 0],
        [PdfDocument::class, 'addStructElement', 5],
        [PdfDocument::class, 'beginStructContainer', 3],
        [PdfDocument::class, 'endStructContainer', 0],
        [PdfDocument::class, 'placeLinkInStructure', 1],
        [PdfPage::class, 'addExternalLink', 6],
        [PdfPage::class, 'addInternalLink', 6],
    ];

    /**
     * The methods missing from php-pdf's classes (or present without the parameters tagging passes).
     * $classes maps a class to the one to inspect in its place, so a test can stand in an old release.
     *
     * @param array<class-string, string> $classes
     * @return list<string>
     */
    public static function missing(array $classes = []): array
    {
        $missing = [];
        foreach (self::REQUIRED as [$class, $method, $parameters]) {
            $class = $classes[$class] ?? $class;
            if (!method_exists($class, $method) || (new \ReflectionMethod($class, $method))->getNumberOfParameters() < $parameters) {
                $missing[] = $method;
            }
        }
        return $missing;
    }
}
