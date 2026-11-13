<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\OperatorDictionary;

/**
 * Operator forms by position. An operator is prefix when it is first in its row or follows another
 * operator or an opening fence; postfix when it is last and follows an operand, or is a closing
 * fence; infix otherwise. A closing fence counts as an operand for what comes after it, so ") - b" is infix.
 *
 * The bars `|` and `‖` are the same character on both sides, so within a row they pair up: the 1st, 3rd, ...
 * of a kind opens (prefix) and the 2nd, 4th, ... closes (postfix, an operand for what follows). A last bar
 * with no partner ("a | b") is a relation: infix.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class Forms
{
    /**
     * @param list<Node> $children
     * @return list<?string> per child: prefix, infix or postfix for operators, null for anything else
     */
    public static function of(array $children): array
    {
        $forms = [];
        $count = count($children);
        $prevIsOperand = false;
        $barTotals = [];
        foreach ($children as $child) {
            if ($child instanceof Operator && self::isBar($child->char)) {
                $barTotals[$child->char] = ($barTotals[$child->char] ?? 0) + 1;
            }
        }
        $barSeen = [];
        foreach ($children as $i => $child) {
            if (! ($child instanceof Operator)) {
                $forms[] = null;
                $prevIsOperand = true;
                continue;
            }
            if (self::isBar($child->char)) {
                $k = $barSeen[$child->char] = ($barSeen[$child->char] ?? 0) + 1;
                if ($k === $barTotals[$child->char] && $k % 2 === 1) {
                    $forms[] = $prevIsOperand ? OperatorDictionary::FORM_INFIX : OperatorDictionary::FORM_PREFIX;
                    $prevIsOperand = false;
                } elseif ($k % 2 === 1) {
                    $forms[] = OperatorDictionary::FORM_PREFIX;
                    $prevIsOperand = false;
                } else {
                    $forms[] = OperatorDictionary::FORM_POSTFIX;
                    $prevIsOperand = true;
                }
                continue;
            }
            $forms[] = match (true) {
                OperatorDictionary::isClosing($child->char) => OperatorDictionary::FORM_POSTFIX,
                ! $prevIsOperand => OperatorDictionary::FORM_PREFIX,
                $i === $count - 1 => OperatorDictionary::FORM_POSTFIX,
                default => OperatorDictionary::FORM_INFIX,
            };
            $prevIsOperand = OperatorDictionary::isClosing($child->char);
        }

        return $forms;
    }

    private static function isBar(string $char): bool
    {
        return $char === '|' || $char === '‖';
    }
}
