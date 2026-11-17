<?php

declare(strict_types=1);

namespace Papercut\Math\Tex;

/**
 * TeX's atom classes and the space they put before and after themselves, in mu (1/18 em).
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class TexClass
{
    /** @var array<string, array{int, int}> class name to [lspace, rspace] */
    public const array SPACING = [
        'rel' => [5, 5],
        'bin' => [4, 4],
        'punct' => [0, 3],
        'open' => [0, 0],
        'close' => [0, 0],
        'ord' => [0, 0],
        'op' => [1, 1],
    ];
}
