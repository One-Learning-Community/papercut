<?php

declare(strict_types=1);

namespace Papercut\Export;

/**
 * An archival PDF/A level: the ISO 19005 part (1, 2 or 3) and conformance (b basic, u Unicode, a accessible).
 * `A2u` is the usual choice. The "a" levels also write a tagged PDF (as `PdfOptions(tagged: true)` does).
 * PDF/A cannot be combined with encryption.
 */
enum PdfA: string
{
    case A1b = '1b';
    case A2b = '2b';
    case A2u = '2u';
    case A2a = '2a';
    case A3b = '3b';
    case A3u = '3u';
    case A3a = '3a';

    /** The ISO 19005 part: 1, 2 or 3. */
    public function part(): int
    {
        return (int) $this->value[0];
    }

    /** The conformance letter, as PDF/A writes it: 'A', 'B' or 'U'. */
    public function conformance(): string
    {
        return strtoupper($this->value[1]);
    }

    /** Whether this level needs a tagged PDF. */
    public function requiresTagging(): bool
    {
        return $this->conformance() === 'A';
    }
}
