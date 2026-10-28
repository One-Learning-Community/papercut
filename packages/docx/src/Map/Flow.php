<?php

declare(strict_types=1);

namespace Papercut\Docx\Map;

use Dskripchenko\PhpDocx\Element\{BlockElement, PageBreak, Paragraph, Run, Table};
use Dskripchenko\PhpDocx\Style\{ParagraphStyle, RunStyle};
use Papercut\Docx\Walk\Frame;
use Papercut\Style\ComputedStyle;

/** A column's children as a run of blocks: gap and vertical margins as paragraph spacing, pagination as flags. */
final class Flow
{
    /** @return list<BlockElement> */
    public static function blocks(Frame $frame): array
    {
        $gap = $frame->style->gap ?? 0.0;
        $out = [];
        $items = array_values(array_filter($frame->items, static fn (array $i): bool => $i['blocks'] !== []));
        $count = count($items);
        foreach ($items as $i => $item) {
            $blocks = $item['blocks'];
            $s = $item['style'];
            $before = $s?->margin->top->points ?? 0.0;
            $after = ($s?->margin->bottom->points ?? 0.0) + ($i < $count - 1 ? $gap : 0.0);
            if ($s !== null) {
                $blocks = self::pagination($blocks, $s);
            }
            if ($before > 0.0) {
                $blocks = self::spaceBefore($blocks, $before);
            }
            // Two tables back to back merge into one in Word; a hairline paragraph keeps them apart.
            if ($out !== [] && end($out) instanceof Table && $blocks[0] instanceof Table) {
                $out[] = self::hairline();
            }
            foreach (self::spaceAfter($blocks, $after) as $b) {
                $out[] = $b;
            }
        }

        return $out;
    }

    /**
     * @param non-empty-list<BlockElement> $blocks
     * @return non-empty-list<BlockElement>
     */
    private static function pagination(array $blocks, ComputedStyle $s): array
    {
        $last = count($blocks) - 1;
        foreach ($blocks as $i => $b) {
            if (!$b instanceof Paragraph) {
                continue;
            }
            $changes = [];
            if ($s->keepTogether) {
                $changes['keepLines'] = true;
                if ($i < $last) {
                    $changes['keepWithNext'] = true;
                }
            }
            if ($s->keepWithNext && $i === $last) {
                $changes['keepWithNext'] = true;
            }
            if ($s->breakBefore && $i === 0) {
                $changes['pageBreakBefore'] = true;
            }
            if ($changes !== []) {
                $blocks[$i] = self::withParagraph($b, $changes);
            }
        }
        if ($s->breakAfter) {
            $blocks[] = new PageBreak();
        }

        return $blocks;
    }

    /**
     * A hairline paragraph with $after points of space after it. Its height is spacing, not line height,
     * and its run is 1pt, so readers without exact line heights (Google Docs) draw it tiny too.
     */
    public static function hairline(float $after = 0.0): Paragraph
    {
        return new Paragraph(
            [new Run('', new RunStyle(sizeHalfPoints: 2))],
            new ParagraphStyle(spaceAfterTwips: max(0, Units::tw($after) - 2), lineSpacingTwips: 2, lineSpacingRule: 'exact'),
        );
    }

    /**
     * @param non-empty-list<BlockElement> $blocks
     * @return non-empty-list<BlockElement>
     */
    public static function spaceAfter(array $blocks, float $points): array
    {
        if ($points <= 0.0) {
            return $blocks;
        }
        $last = count($blocks) - 1;
        $p = $blocks[$last];
        if ($p instanceof Paragraph) {
            $blocks[$last] = self::withParagraph($p, ['spaceAfterTwips' => $p->style->spaceAfterTwips + Units::tw($points)]);
        } else {
            $blocks[] = self::hairline($points);
        }

        return $blocks;
    }

    /**
     * @param non-empty-list<BlockElement> $blocks
     * @return non-empty-list<BlockElement>
     */
    public static function spaceBefore(array $blocks, float $points): array
    {
        if ($points <= 0.0) {
            return $blocks;
        }
        $p = $blocks[0];
        if ($p instanceof Paragraph) {
            $blocks[0] = self::withParagraph($p, ['spaceBeforeTwips' => $p->style->spaceBeforeTwips + Units::tw($points)]);
        } else {
            array_unshift($blocks, self::hairline($points));
        }

        return $blocks;
    }

    /** @param array<string, mixed> $changes ParagraphStyle constructor arguments */
    public static function withParagraph(Paragraph $p, array $changes): Paragraph
    {
        return new Paragraph($p->children, new ParagraphStyle(...[...get_object_vars($p->style), ...$changes]), $p->headingLevel, $p->styleId);
    }
}
