<?php

declare(strict_types=1);

namespace Papercut\Tests\Support;

use Papercut\{Component, Node, OverflowMode};
use Papercut\Layout\{BreakToken, Constraints, LayoutContext, LayoutEngine, LayoutResult};
use Papercut\Style\{ComputedStyle, Theme};
use Papercut\Text\FontRegistry;

final class LayoutHarness
{
    /** @var list<string> */
    public array $warnings = [];
    public LayoutEngine $engine;

    public function __construct(?Theme $theme = null, OverflowMode $overflow = OverflowMode::Throw, ?FakeBackend $backend = null)
    {
        $backend ??= new FakeBackend();
        $ctx = new LayoutContext(FontRegistry::withDefaults($backend), $theme ?? Theme::default(), $backend, $overflow, function (string $w): void { $this->warnings[] = $w; });
        $this->engine = new LayoutEngine($ctx);
    }

    public function layout(Node|Component $node, float $width, ?float $height = null, ?BreakToken $token = null, bool $fresh = true): LayoutResult
    {
        return $this->engine->layout($node, ComputedStyle::root(Theme::default()), new Constraints($width, $height, $fresh), $token);
    }

    /**
     * All TextRun texts in a fragment tree, depth-first, with absolute positions.
     *
     * @return list<array{text: string, x: float, baseline: float}>
     */
    public static function runs(?\Papercut\Layout\Fragment $f, float $ox = 0, float $oy = 0): array
    {
        if ($f === null) {
            return [];
        }
        $out = [];
        foreach ($f->ops() as $op) {
            if ($op instanceof \Papercut\Paint\TextRun) {
                $out[] = ['text' => $op->text, 'x' => $ox + $op->x, 'baseline' => $oy + $op->baseline];
            }
        }
        foreach ($f->children() as $c) {
            array_push($out, ...self::runs($c['fragment'], $ox + $c['x'], $oy + $c['y']));
        }
        return $out;
    }
}
