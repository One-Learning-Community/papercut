<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Support;

use Papercut\{Document, Page, Text};
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PendingDocument;

/** Helpers for tests of tagged PDF output. */
trait ReadsStructure
{
    private function document(): PendingDocument
    {
        return Papercut::make(Document::make(Page::a4()->body(Text::h1('Title'), Text::p('Body')))->title('Doc')->lang('en-US'));
    }

    /** Whether a PDF has a structure tree; looks inside object streams (and decrypts with $password). */
    private static function isTagged(string $pdf, string $password = ''): bool
    {
        $file = tempnam(sys_get_temp_dir(), 'pc') . '.pdf'; // qpdf's AppArmor profile wants .pdf
        rename(substr($file, 0, -4), $file);
        file_put_contents($file, $pdf);
        exec(sprintf('qpdf --qdf --object-streams=disable --password=%s %s %s.qdf 2>&1 < /dev/null', escapeshellarg($password), escapeshellarg($file), escapeshellarg($file)));
        $qdf = (string) file_get_contents($file . '.qdf');
        unlink($file);
        @unlink($file . '.qdf');

        return str_contains($qdf, '/StructTreeRoot');
    }
}
