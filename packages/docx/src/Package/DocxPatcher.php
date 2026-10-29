<?php

declare(strict_types=1);

namespace Papercut\Docx\Package;

/**
 * Fixes php-docx's output where it can't express what Papercut needs: `left`/`right` alignment
 * (Google Docs ignores `start`/`end`), run shading in any colour, the theme's default font, and the document language.
 * It edits the exact markup php-docx writes and throws when that markup changes, so an upgrade fails loudly.
 */
final class DocxPatcher
{
    private const string DOC_DEFAULTS = '<w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:rPrDefault>';

    /** Marks a run's highlight as run shading in a colour of its own; see shading(). */
    private const string SHADING = 'papercut-shd-';

    /** A highlight value patch() turns into run shading in $hex (six lower-case hex digits). */
    public static function shading(string $hex): string
    {
        return self::SHADING . $hex;
    }

    public static function patch(string $bytes, PatchSpec $spec): string
    {
        $parts = self::read($bytes);
        foreach ($parts as $name => $xml) {
            if (preg_match('~^word/(document|styles|header\d+|footer\d+)\.xml$~', $name) === 1) {
                $xml = str_replace(['<w:jc w:val="start"/>', '<w:jc w:val="end"/>'], ['<w:jc w:val="left"/>', '<w:jc w:val="right"/>'], $xml);
                $xml = (string) preg_replace('~<w:highlight w:val="' . self::SHADING . '([0-9a-f]{6})"/>~', '<w:shd w:val="clear" w:color="auto" w:fill="$1"/>', $xml);
                if (str_contains($xml, self::SHADING)) {
                    throw new \LogicException('php-docx output changed: run highlight in ' . $name . ' not found');
                }
                $parts[$name] = $xml;
            }
        }

        $font = self::attr($spec->fontFamily);
        $lang = $spec->lang === null ? '' : '<w:lang w:val="' . self::attr($spec->lang) . '"/>';
        $parts['word/styles.xml'] = self::replaceOnce(
            '~' . preg_quote(self::DOC_DEFAULTS, '~') . '~',
            '<w:rPrDefault><w:rPr><w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font . '" w:cs="' . $font . '"/>'
                . '<w:sz w:val="' . $spec->fontSizeHalfPoints . '"/><w:szCs w:val="' . $spec->fontSizeHalfPoints . '"/>' . $lang . '</w:rPr></w:rPrDefault>',
            $parts['word/styles.xml'] ?? '',
            'default run properties in word/styles.xml',
        );

        if ($spec->mirrorMargins) {
            $parts = self::mirrorMargins($parts);
        }

        return DeterministicZip::write($parts, $spec->time);
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    private static function mirrorMargins(array $parts): array
    {
        if (isset($parts['word/settings.xml'])) {
            $parts['word/settings.xml'] = self::replaceOnce('~<w:evenAndOddHeaders/>~', '<w:mirrorMargins/><w:evenAndOddHeaders/>', $parts['word/settings.xml'], '<w:evenAndOddHeaders/> in word/settings.xml');

            return $parts;
        }

        return self::addPart(
            $parts,
            'word/settings.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:mirrorMargins/></w:settings>',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml',
        );
    }

    /**
     * Adds a part under word/ that php-docx did not write: the file, its relationship in document.xml.rels (a new rId)
     * and its content-type override.
     *
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    private static function addPart(array $parts, string $name, string $xml, string $relType, string $contentType): array
    {
        if (isset($parts[$name])) {
            throw new \LogicException('php-docx output changed: ' . $name . ' already exists');
        }
        $rels = $parts['word/_rels/document.xml.rels'] ?? '';
        preg_match_all('~\bId="rId(\d+)"~', $rels, $m);
        $id = 'rId' . ($m[1] === [] ? 1 : max(array_map('intval', $m[1])) + 1);
        $parts['word/_rels/document.xml.rels'] = self::replaceOnce(
            '~</Relationships>~',
            '<Relationship Id="' . $id . '" Type="' . $relType . '" Target="' . self::attr(substr($name, strlen('word/'))) . '"/></Relationships>',
            $rels,
            '</Relationships> in word/_rels/document.xml.rels',
        );
        $parts['[Content_Types].xml'] = self::replaceOnce(
            '~</Types>~',
            '<Override PartName="/' . self::attr($name) . '" ContentType="' . $contentType . '"/></Types>',
            $parts['[Content_Types].xml'] ?? '',
            '</Types> in [Content_Types].xml',
        );
        $parts[$name] = $xml;

        return $parts;
    }

    private static function replaceOnce(string $pattern, string $replacement, string $subject, string $what): string
    {
        $out = preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $replacement), $subject, -1, $count);
        if ($out === null || $count !== 1) {
            throw new \LogicException('php-docx output changed: ' . $what . ' not found');
        }

        return $out;
    }

    private static function attr(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @return array<string, string> entries in archive order */
    private static function read(string $bytes): array
    {
        $file = tempnam(sys_get_temp_dir(), 'papercut-docx');
        if ($file === false) {
            throw new \RuntimeException('Could not create a temporary file');
        }
        try {
            file_put_contents($file, $bytes);
            $zip = new \ZipArchive();
            if ($zip->open($file, \ZipArchive::RDONLY) !== true) {
                throw new \RuntimeException('php-docx wrote an unreadable archive');
            }
            $parts = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $data = $zip->getFromIndex($i);
                if ($name === false || $data === false) {
                    throw new \RuntimeException('php-docx wrote an unreadable archive entry');
                }
                $parts[$name] = $data;
            }
            $zip->close();

            return $parts;
        } finally {
            @unlink($file);
        }
    }
}
