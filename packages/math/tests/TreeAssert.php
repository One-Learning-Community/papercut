<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Row;
use PHPUnit\Framework\Assert;

/**
 * Structural comparison of two node trees, for round-trip tests. Both trees are normalized first: a Fallback with
 * content is its content, a Fallback without content is nothing, a Row of one child is that child. Then the class and
 * every public property are compared recursively (floats within 1e-6); notes are not compared.
 */
final class TreeAssert
{
    private const float EPSILON = 1e-6;

    public static function assertSameTree(Node $expected, Node $actual): void
    {
        $diff = self::diff(self::describe($expected) ?? self::emptyRow(), self::describe($actual) ?? self::emptyRow(), 'root');
        if ($diff !== null) {
            Assert::fail('Trees differ at ' . $diff);
        }
    }

    /** @return array{class: class-string, props: array<string, mixed>} */
    private static function emptyRow(): array
    {
        return ['class' => Row::class, 'props' => ['children' => []]];
    }

    /** @return array{class: class-string, props: array<string, mixed>}|null null for a Fallback with no content */
    private static function describe(Node $n): ?array
    {
        if ($n instanceof Fallback) {
            return $n->content === null ? null : self::describe($n->content);
        }
        if ($n instanceof Row) {
            $kids = [];
            foreach ($n->children as $child) {
                $d = self::describe($child);
                if ($d !== null) {
                    $kids[] = $d;
                }
            }

            return count($kids) === 1 ? $kids[0] : ['class' => Row::class, 'props' => ['children' => $kids]];
        }
        $props = [];
        foreach ((new \ReflectionObject($n))->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
            $props[$p->getName()] = self::value($p->getValue($n));
        }

        return ['class' => $n::class, 'props' => $props];
    }

    private static function value(mixed $v): mixed
    {
        if ($v instanceof Node) {
            return self::describe($v) ?? self::emptyRow();
        }
        if (is_array($v)) {
            return array_map(self::value(...), $v);
        }

        return $v;
    }

    private static function diff(mixed $e, mixed $a, string $path): ?string
    {
        $isNode = static fn (mixed $x): bool => is_array($x) && isset($x['class'], $x['props']);
        if ($isNode($e) && $isNode($a)) {
            if ($e['class'] !== $a['class']) {
                return sprintf('%s: expected %s, got %s', $path, self::short($e['class']), self::short($a['class']));
            }
            foreach ($e['props'] as $name => $value) {
                $d = self::diff($value, $a['props'][$name] ?? null, $path . '.' . $name);
                if ($d !== null) {
                    return $d . ' (' . self::short($e['class']) . ')';
                }
            }

            return null;
        }
        if (is_array($e) && is_array($a) && ! $isNode($e) && ! $isNode($a)) {
            foreach ($e as $k => $value) {
                if (! array_key_exists($k, $a)) {
                    return sprintf('%s[%s]: missing in the actual tree', $path, $k);
                }
                $d = self::diff($value, $a[$k], $path . '[' . $k . ']');
                if ($d !== null) {
                    return $d;
                }
            }
            foreach (array_keys($a) as $k) {
                if (! array_key_exists($k, $e)) {
                    return sprintf('%s[%s]: unexpected extra item', $path, $k);
                }
            }

            return null;
        }
        if ((is_int($e) || is_float($e)) && (is_int($a) || is_float($a))) {
            return abs($e - $a) <= self::EPSILON ? null : sprintf('%s: expected %s, got %s', $path, var_export($e, true), var_export($a, true));
        }
        if ($e === $a) {
            return null;
        }

        return sprintf('%s: expected %s, got %s', $path, self::show($e), self::show($a));
    }

    private static function short(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private static function show(mixed $v): string
    {
        return is_array($v) && isset($v['class']) ? self::short((string) $v['class']) . ' node' : var_export($v, true);
    }
}
