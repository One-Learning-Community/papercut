<?php

declare(strict_types=1);

namespace Papercut\Tests\Support;

/**
 * Reads a PDF's structure tree back from a `qpdf --qdf` dump, for tests.
 *
 * Each element is an array with `role`, `kids` (elements; marked-content ids are left out), `alt` (or null),
 * `attrs` (the raw /A dictionary text, or null) and `objr` (true when it holds an annotation).
 */
final class StructTree
{
    /** @var array<int, string> object bodies by number */
    private array $objects = [];

    private function __construct(private readonly string $qdf)
    {
        preg_match_all('/^(\d+) 0 obj\n(.*?)\nendobj$/ms', $qdf, $m, PREG_SET_ORDER);
        foreach ($m as $o) {
            $this->objects[(int) $o[1]] = $o[2];
        }
    }

    /** @return list<array<string, mixed>> the root's elements */
    public static function of(string $pdf): array
    {
        $tree = new self(PdfInspector::qdf($pdf));
        $root = $tree->find('/Type /StructTreeRoot');
        return $root === null ? [] : $tree->kids($root);
    }

    /** The QDF text, to look at what the tree does not carry (the XMP packet, the catalog). */
    public static function qdf(string $pdf): string
    {
        return PdfInspector::qdf($pdf);
    }

    private function find(string $needle): ?string
    {
        foreach ($this->objects as $body) {
            if (str_contains($body, $needle)) {
                return $body;
            }
        }
        return null;
    }

    /** @return list<array<string, mixed>> */
    private function kids(string $body): array
    {
        $out = [];
        if (preg_match('/^  \/K \[\n(.*?)\n  \]$/ms', $body, $m) === 1) {
            // Inline dictionaries are object references (OBJR), not child elements.
            preg_match_all('/(\d+) 0 R/', (string) preg_replace('/<<.*?>>/s', '', $m[1]), $refs);
            foreach ($refs[1] as $ref) {
                $out[] = $this->element((int) $ref);
            }
        } elseif (preg_match('/^  \/K (\d+) 0 R$/m', $body, $m) === 1) {
            $out[] = $this->element((int) $m[1]);
        }
        return $out;
    }

    /** The /Alt text of an element, which php-pdf writes as UTF-16 hex. */
    private static function alt(string $body): ?string
    {
        if (preg_match('/^  \/Alt <([0-9a-fA-F]+)>$/m', $body, $m) === 1) {
            $bytes = (string) hex2bin($m[1]);
            return mb_convert_encoding(str_starts_with($bytes, "\xFE\xFF") ? substr($bytes, 2) : $bytes, 'UTF-8', 'UTF-16BE');
        }
        return preg_match('/^  \/Alt \((.*)\)$/m', $body, $m) === 1 ? $m[1] : null;
    }

    /** @return array<string, mixed> */
    private function element(int $n): array
    {
        $body = $this->objects[$n];
        preg_match('/^  \/S \/(\w+)$/m', $body, $role);
        $alt = self::alt($body);
        preg_match('/^  \/A <<\n(.*?)\n  >>$/ms', $body, $attrs);
        return [
            'role' => $role[1] ?? '?',
            'kids' => $this->kids($body),
            'alt' => $alt,
            'attrs' => isset($attrs[1]) ? trim((string) preg_replace('/\s+/', ' ', $attrs[1])) : null,
            'objr' => str_contains($body, '/Type /OBJR'),
        ];
    }

    /**
     * The /Contents of every link annotation, in file order.
     *
     * @return list<string>
     */
    public static function linkContents(string $pdf): array
    {
        preg_match_all('/^  \/Contents (?:<([0-9a-fA-F]+)>|\((.*)\))$/m', PdfInspector::qdf($pdf), $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $c) {
            if (($c[1] ?? '') !== '') {
                $bytes = (string) hex2bin($c[1]);
                $out[] = mb_convert_encoding(str_starts_with($bytes, "\xFE\xFF") ? substr($bytes, 2) : $bytes, 'UTF-8', 'UTF-16BE');
            } else {
                $out[] = $c[2] ?? '';
            }
        }
        return $out;
    }
}
