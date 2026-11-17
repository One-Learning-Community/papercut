<?php

declare(strict_types=1);

namespace Quoin\Math\Tex;

/**
 * TeX's atom classes and the space they put before and after themselves, in mu (1/18 em).
 *
 * @internal Engine detail, not public API. It may change in any release.
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
