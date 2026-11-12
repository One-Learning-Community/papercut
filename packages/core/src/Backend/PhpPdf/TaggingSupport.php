<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Pdf\Document as PdfDocument;

/**
 * Whether the installed php-pdf can write a tagged PDF. Tagging needs methods that older releases
 * lack, and some are marked @internal upstream, so it is checked rather than assumed.
 *
 * @internal Part of Papercut's PDF writer. It may change in any release.
 */
final class TaggingSupport
{
    /** php-pdf's document class to inspect; a test points it at a stand-in to simulate an old release. */
    public static string $documentClass = PdfDocument::class;

    /** Methods tagging calls, with the number of parameters each needs. */
    private const array REQUIRED = [
        'enablePdfUa' => 0,
        'addStructElement' => 5,
        'beginStructContainer' => 3,
        'endStructContainer' => 0,
        'placeLinkInStructure' => 1,
    ];

    /** @return list<string> the methods the installed php-pdf lacks (or has without the parameters tagging passes) */
    public static function missing(): array
    {
        $missing = [];
        foreach (self::REQUIRED as $method => $parameters) {
            if (!method_exists(self::$documentClass, $method) || (new \ReflectionMethod(self::$documentClass, $method))->getNumberOfParameters() < $parameters) {
                $missing[] = $method;
            }
        }
        return $missing;
    }

    public static function available(): bool
    {
        return self::missing() === [];
    }
}
