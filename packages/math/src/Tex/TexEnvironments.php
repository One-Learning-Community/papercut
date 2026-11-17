<?php

declare(strict_types=1);

namespace Papercut\Math\Tex;

use Papercut\Math\Math;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Grid;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Row;
use Papercut\Math\NoteLevel;

/**
 * What each LaTeX environment means: its rows of cells and column alignment become a node, with the notes for what is
 * not drawn. No parser state; {@see TexReader} reads the source and calls in here.
 *
 * A note is [level, element, reason, byte offset].
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class TexEnvironments
{
    private const array MATRICES = [
        'matrix' => 'none',
        'pmatrix' => 'paren',
        'bmatrix' => 'bracket',
        'Bmatrix' => 'brace',
        'vmatrix' => 'bar',
        'Vmatrix' => 'doublebar',
    ];

    private const array SMALL_MATRICES = [
        'smallmatrix' => 'none',
        'psmallmatrix' => 'paren',
        'bsmallmatrix' => 'bracket',
        'Bsmallmatrix' => 'brace',
        'vsmallmatrix' => 'bar',
        'Vsmallmatrix' => 'doublebar',
    ];

    private const array ALIGNED = ['aligned', 'align', 'split', 'alignat', 'alignedat', 'flalign', 'xalignat', 'xxalignat'];

    /** Environments that are display math by themselves. */
    private const array DISPLAY = ['align', 'alignat', 'flalign', 'xalignat', 'xxalignat', 'gather', 'equation', 'displaymath'];

    /** The environment is display math by itself (`displaymath` has no starred form). */
    public static function isDisplay(string $name): bool
    {
        $base = rtrim($name, '*');

        return $base === 'displaymath' ? $name === $base : in_array($base, self::DISPLAY, true);
    }

    /**
     * What follows `\begin{name}` before the first cell: the column spec of an array, the column count of alignat, the
     * `[r]` of a starred matrix. Returns the column alignment it gave (empty for none).
     *
     * @param list<array{NoteLevel, string, string, int}> $notes
     * @return list<string>
     */
    public static function arguments(string $name, TexCursor $c, array &$notes): array
    {
        $base = rtrim($name, '*');
        if (in_array($name, ['array', 'subarray', 'darray'], true)) {
            return self::columnSpec($c, $notes);
        }
        if (in_array($base, ['alignat', 'alignedat', 'xalignat', 'xxalignat'], true)) {
            // The column count: the cells say it already.
            $c->skipSp();
            $t = $c->peek();
            if ($t !== null && ($t[0] === '{' || $t[0] === 'ch' && ctype_digit($t[1]))) {
                $c->rawGroup();
            }
        } elseif ($name !== $base && isset(self::MATRICES[$base])) {
            $c->skipSp();
            $t = $c->peek();
            $letter = $c->t[$c->p + 1] ?? null;
            $close = $c->t[$c->p + 2] ?? null;
            if ($t !== null && $t[0] === 'ch' && $t[1] === '[' && $letter !== null && $close !== null && $close[1] === ']' && in_array($letter[1], ['l', 'c', 'r'], true)) {
                $c->p += 3;

                return [['l' => 'left', 'c' => 'center', 'r' => 'right'][$letter[1]]];
            }
            if ($t !== null && $t[0] === 'ch' && $t[1] === '[') {
                $notes[] = [NoteLevel::Approximated, $name, 'column option must be [l], [c] or [r]; drawn as content', $t[2]];
            }
        }

        return [];
    }

    /**
     * The `{rcl}` of an array: one alignment per column. Rules and `@{}`/`p{}` columns are noted.
     *
     * @param list<array{NoteLevel, string, string, int}> $notes
     * @return list<string>
     */
    public static function columnSpec(TexCursor $c, array &$notes): array
    {
        $c->skipSp();
        if (! $c->peekIs('{')) {
            return [];
        }
        $c->p++;
        $align = [];
        $depth = 1;
        while (($t = $c->peek()) !== null) {
            $c->p++;
            if ($t[0] === '{') {
                $depth++;
            } elseif ($t[0] === '}') {
                if (--$depth === 0) {
                    break;
                }
            } elseif ($t[0] === 'ch') {
                switch ($t[1]) {
                    case 'l':
                        $align[] = 'left';
                        break;
                    case 'c':
                        $align[] = 'center';
                        break;
                    case 'r':
                        $align[] = 'right';
                        break;
                    case '|':
                    case ':':
                        $notes[] = [NoteLevel::Dropped, 'array', 'column rules are not drawn', $t[2]];
                        break;
                    case '@':
                    case '!':
                        self::skipGroupArgument($c);
                        $notes[] = [NoteLevel::Approximated, 'array', '@{…} column separators are not drawn', $t[2]];
                        break;
                    case 'p':
                    case 'm':
                    case 'b':
                        self::skipGroupArgument($c);
                        $align[] = 'left';
                        $notes[] = [NoteLevel::Approximated, 'array', 'p{…} columns are drawn left-aligned without their width', $t[2]];
                        break;
                }
            }
        }

        return $align;
    }

    private static function skipGroupArgument(TexCursor $c): void
    {
        $c->skipSp();
        if ($c->peekIs('{')) {
            $c->p++;
            $c->skipBalanced();
        }
    }

    /**
     * @param list<list<Node>> $rows
     * @param list<string> $align
     * @param list<array{NoteLevel, string, string, int}> $notes
     */
    public static function node(string $name, array $rows, array $align, array &$notes, int $at): Node
    {
        $base = rtrim($name, '*');
        if (isset(self::MATRICES[$base])) {
            return Math::matrix($rows, self::MATRICES[$base], $align === [] ? 'center' : $align);
        }
        if (isset(self::SMALL_MATRICES[$name])) {
            $notes[] = [NoteLevel::Approximated, $name, 'smallmatrix drawn at normal size', $at];

            return Math::matrix($rows, self::SMALL_MATRICES[$name]);
        }
        switch ($name) {
            case 'cases':
                return Math::cases($rows);
            case 'dcases':
                return new Fenced('{', '', new Grid($rows, ['left'], 'baseline', true));
            case 'rcases':
                return new Fenced('', '}', new Grid($rows, ['left']));
            case 'drcases':
                return new Fenced('', '}', new Grid($rows, ['left'], 'baseline', true));
            case 'array':
            case 'subarray':
                return new Grid($rows, $align);
            case 'darray':
                return new Grid($rows, $align, 'baseline', true);
            case 'CD':
                $notes[] = [NoteLevel::Approximated, 'CD', 'not supported', $at];

                return new Row(self::cells($rows));
        }
        if ($base === 'equation' || $name === 'displaymath') {
            // One equation has no columns or rows: what they would separate is joined, and said.
            if (count($rows) > 1) {
                $notes[] = [NoteLevel::Dropped, '\\\\', 'line break outside an environment', $at];
            }
            foreach ($rows as $row) {
                if (count($row) > 1) {
                    $notes[] = [NoteLevel::Dropped, '&', 'alignment tab outside an alignment environment', $at];
                    break;
                }
            }
            $cells = self::cells($rows);

            return count($cells) === 1 ? $cells[0] : new Row($cells);
        }
        if (in_array($base, self::ALIGNED, true)) {
            // alignat puts no space between pairs: the author adds it. xalignat and xxalignat spread their pairs across the line; they are approximated the same way.
            return Math::aligned($rows, in_array($base, ['alignat', 'alignedat', 'xalignat', 'xxalignat'], true) ? 0.0 : 2.0);
        }
        if ($base === 'gathered' || $base === 'gather') {
            return new Grid($rows, ['center'], 'baseline', true);
        }
        $notes[] = [NoteLevel::Approximated, $name, 'unknown environment, drawn as a matrix', $at];

        return Math::matrix($rows, 'none');
    }

    /**
     * @param list<list<Node>> $rows
     *
     * @return list<Node> every cell, in reading order
     */
    private static function cells(array $rows): array
    {
        $cells = [];
        foreach ($rows as $row) {
            array_push($cells, ...$row);
        }

        return $cells;
    }
}
