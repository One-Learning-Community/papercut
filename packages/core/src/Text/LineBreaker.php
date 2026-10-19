<?php

declare(strict_types=1);

namespace Papercut\Text;

final class LineBreaker
{
    private const float EPS = 1e-9;

    /**
     * @param list<InlineItem> $items
     * @return list<Line>
     */
    public static function break(array $items, float $maxWidth): array
    {
        $max = $maxWidth <= 0.0 ? 1.0 : $maxWidth;
        /** @var list<array{list<InlineItem>, float, bool}> $lines */
        $lines = [];
        /** @var list<InlineItem> $cur */
        $cur = [];
        $w = 0.0;
        /** @var ?InlineItem $space */
        $space = null;
        /** @var list<InlineItem> $chunk */
        $chunk = [];

        $place = static function () use (&$chunk, &$cur, &$w, &$space, &$lines, $max): void {
            if ($chunk === []) {
                return;
            }
            $cw = array_sum(array_map(static fn (InlineItem $i): float => $i->width, $chunk));
            $sw = $cur !== [] && $space !== null ? $space->width : 0.0;
            if ($cur !== [] && $w + $sw + $cw > $max + self::EPS) {
                $lines[] = [$cur, $w, false];
                $cur = [];
                $w = 0.0;
                $sw = 0.0;
                $space = null;
            }
            if ($cur === [] && $cw > $max + self::EPS) {
                foreach (self::splitChunk($chunk, $max) as [$done, $dw]) {
                    if ($cur !== []) {
                        $lines[] = [$cur, $w, false];
                    }
                    $cur = $done;
                    $w = $dw;
                }
            } else {
                if ($sw > 0.0 && $space !== null) {
                    $cur[] = $space;
                    $w += $sw;
                }
                foreach ($chunk as $it) {
                    $cur[] = $it;
                    $w += $it->width;
                }
            }
            $chunk = [];
            $space = null;
        };

        foreach ($items as $it) {
            if ($it->kind === 'break') {
                $place();
                $lines[] = [$cur, $w, true];
                $cur = [];
                $w = 0.0;
                $space = null;
            } elseif ($it->kind === 'space') {
                $place();
                $space = $it;
            } else {
                $chunk[] = $it;
                if ($it->breakAfter) {
                    $place();
                }
            }
        }
        $place();
        if ($cur !== []) {
            $lines[] = [$cur, $w, false];
        }

        $out = [];
        $last = count($lines) - 1;
        foreach ($lines as $i => [$its, $width, $forced]) {
            $out[] = new Line($its, $width, $forced, $i === $last);
        }
        return $out;
    }

    /**
     * Splits an over-wide chunk character by character. Returns lines as
     * [items, width]; the last entry is the unfinished remainder line.
     *
     * @param list<InlineItem> $chunk
     * @return list<array{list<InlineItem>, float}>
     */
    private static function splitChunk(array $chunk, float $max): array
    {
        $lines = [];
        $cur = [];
        $w = 0.0;
        foreach ($chunk as $item) {
            $buf = '';
            $bufW = 0.0;
            foreach (mb_str_split($item->text, 1, 'UTF-8') as $ch) {
                $tryW = $item->font->advance($buf . $ch, $item->size, $item->style->letterSpacing);
                if ($w + $tryW > $max + self::EPS && ($buf !== '' || $cur !== [])) {
                    if ($buf !== '') {
                        $cur[] = $item->withText($buf, $bufW, false);
                        $w += $bufW;
                    }
                    $lines[] = [$cur, $w];
                    $cur = [];
                    $w = 0.0;
                    $buf = $ch;
                    $bufW = $item->font->advance($ch, $item->size, $item->style->letterSpacing);
                } else {
                    $buf .= $ch;
                    $bufW = $tryW;
                }
            }
            if ($buf !== '') {
                $cur[] = $item->withText($buf, $bufW, $item->breakAfter);
                $w += $bufW;
            }
        }
        $lines[] = [$cur, $w];
        return $lines;
    }
}
