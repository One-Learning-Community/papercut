<?php

declare(strict_types=1);

namespace Quoin\Math\Tex\Chem;

use Quoin\Math\Math;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Row;

/**
 * The reaction arrows of mhchem and the nodes they are drawn as. An arrow stretches to its labels and keeps the
 * minimum length of TeX's extensible arrows. The unequal pair is drawn as the equal one.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class ChemArrows
{
    /** mhchem spelling to arrow, longest spelling first. The unequal pairs are marked by their own names. */
    private const array ARROWS = [
        '<-->' => '⇄',
        '<=>>' => 'forward',
        '<<=>' => 'backward',
        '<->' => '↔',
        '<=>' => '⇌',
        '->' => '→',
        '<-' => '←',
    ];

    /**
     * The arrow spelled at a position of the source: longest spelling first.
     *
     * @return array{arrow: string, length: int}|null the arrow (a character, or `forward` or `backward` for an unequal pair) and the length of its spelling
     */
    public static function match(string $src, int $at): ?array
    {
        if ($at < 0 || $at > strlen($src)) {
            return null;
        }
        foreach (self::ARROWS as $spelling => $arrow) {
            if (substr_compare($src, $spelling, $at, strlen($spelling)) === 0) {
                return ['arrow' => $arrow, 'length' => strlen($spelling)];
            }
        }

        return null;
    }

    /** The arrow as a relation: a stretchy arrow with the labels over and under it. */
    public static function node(string $arrow, ?Node $over, ?Node $under): Node
    {
        $char = self::isUnequal($arrow) ? '⇌' : $arrow;

        // With no label, an empty one above keeps the arrow stretchy and at the minimum length (UnderOver needs a part).
        return Math::xarrow($char, $over ?? ($under === null ? new Row([]) : null), $under);
    }

    /**
     * Whether a matched arrow is an unequal pair, which node() draws as an equilibrium arrow: no stack of existing
     * nodes keeps the short harpoon shorter than the long one as the labels stretch them.
     */
    public static function isUnequal(string $arrow): bool
    {
        return $arrow === 'forward' || $arrow === 'backward';
    }
}
