<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

/** Lays glyphs side by side on the baseline (no pair kerning). At script levels it swaps in the font's `ssty` forms. */
final class Run
{
    /**
     * @param list<array{int, string}> $glyphs [codepoint to draw, source character]
     */
    public static function build(Context $c, array $glyphs, float $lead = 0.0, float $trail = 0.0): Box
    {
        $f = $c->font;
        $u = $c->unit();
        $box = new Box($lead, 0.0, 0.0, 0.0, true);
        $last = 0;
        $scripted = $c->scriptAlternates && $c->level > 0 && $f->hasScriptAlternates();
        foreach ($glyphs as [$cp, $src]) {
            $gid = $f->gid($cp);
            if ($gid === 0) {
                $gid = $f->gid(mb_ord($src)); // fall back to the source character
            }
            if ($scripted) {
                $gid = $f->scriptVariant($gid, min($c->level, 2));
            }
            $box->firstGid ??= $gid;
            $box->lastGid = $gid;
            $bb = $f->bbox($gid);
            $box->add(new Glyph($gid, $src, $box->width, 0.0, $c->size()));
            $box->ascent = max($box->ascent, $bb[3] * $u);
            $box->descent = max($box->descent, -$bb[1] * $u);
            $box->width += $f->advance($gid) * $u;
            $last = $gid;
        }
        $box->italic = $last ? $f->math()->italicsCorrection($last) * $u : 0.0;
        $box->width += $trail;

        return $box;
    }
}
