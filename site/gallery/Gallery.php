<?php

declare(strict_types=1);

namespace Papercut\Site;

/**
 * The docs gallery's manifest (gallery.json): groups of tiles, each with optional layout variants.
 * An entry is one image: a tile's default look or one of its variants.
 */
final class Gallery
{
    /**
     * @param list<array{title: string, tiles: list<array{id: string, title: string, api: string, alt: string, pages: int, variants: list<array{id: string, label: string, alt: string}>}>}> $groups
     */
    private function __construct(private readonly array $groups, private readonly string $base)
    {
    }

    public static function load(string $manifestPath): self
    {
        $json = file_get_contents($manifestPath);
        if ($json === false) {
            throw new \RuntimeException("Cannot read $manifestPath");
        }
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['groups']) || !is_array($data['groups'])) {
            throw new \RuntimeException("$manifestPath has no groups");
        }
        /** @var list<array{title: string, tiles: list<array{id: string, title: string, api: string, alt: string, pages: int, variants: list<array{id: string, label: string, alt: string}>}>}> $groups */
        $groups = $data['groups'];

        return new self($groups, dirname($manifestPath));
    }

    /** @return list<array{id: string, title: string, api: string, alt: string, pages: int, variants: list<array{id: string, label: string, alt: string}>}> */
    public function tiles(): array
    {
        $tiles = [];
        foreach ($this->groups as $group) {
            foreach ($group['tiles'] as $tile) {
                $tiles[] = $tile;
            }
        }

        return $tiles;
    }

    /**
     * Every image, a tile then its variants, in manifest order. Sources sit beside the manifest in sources/;
     * images go to $imageDir (the site's public gallery folder by default).
     *
     * @return list<array{id: string, source: string, image: string, pages: int}>
     */
    public function entries(?string $imageDir = null): array
    {
        $imageDir ??= dirname($this->base) . '/src/.vuepress/public/gallery';
        $entries = [];
        foreach ($this->tiles() as $tile) {
            $ids = [$tile['id'], ...array_map(static fn (array $v): string => $tile['id'] . '.' . $v['id'], $tile['variants'])];
            foreach ($ids as $id) {
                $entries[] = ['id' => $id, 'source' => $this->base . '/sources/' . $id . '.php', 'image' => $imageDir . '/' . $id . '.png', 'pages' => $tile['pages']];
            }
        }

        return $entries;
    }
}
