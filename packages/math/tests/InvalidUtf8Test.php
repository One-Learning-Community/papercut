<?php

declare(strict_types=1);

namespace Quoin\Math\Tests;

use Quoin\Math\Math;
use Quoin\Math\Node\Node;
use Quoin\Math\NoteLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvalidUtf8Test extends TestCase
{
    protected function setUp(): void
    {
        set_error_handler(static function (int $no, string $msg, string $file, int $line): never {
            throw new \ErrorException($msg, 0, $no, $file, $line);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    /** @return array<string, array{\Closure(): Node}> */
    public static function builders(): array
    {
        return [
            'row' => [static fn (): Node => Math::row("\xff")],
            'row with several bad strings' => [static fn (): Node => Math::row("a\xff", "\xfe", 'b')],
            'text' => [static fn (): Node => Math::text("\xff")],
            'fenced content' => [static fn (): Node => Math::fenced('(', ')', "\xff")],
            'fenced delimiters' => [static fn (): Node => Math::fenced("\xff", ')', 'x')],
            'bigOp' => [static fn (): Node => Math::bigOp("\xff")],
            'bigOp with limits' => [static fn (): Node => Math::bigOp("\xff", 'a', 'b')],
            'accent mark' => [static fn (): Node => Math::accent('x', "\xff")],
            'styled' => [static fn (): Node => Math::styled("\xff", 'bold')],
            'styled partly bad' => [static fn (): Node => Math::styled("a\xffb", 'bold')],
            'frac' => [static fn (): Node => Math::frac("x\xff", '1')],
        ];
    }

    /** @param \Closure(): Node $build */
    #[DataProvider('builders')]
    public function test_builds_lays_out_and_notes_once(\Closure $build): void
    {
        $node = $build();
        $box = Math::layout($node);
        self::assertGreaterThan(0.0, $box->width);
        $notes = Math::notes($node);
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('text', $notes[0]->element);
        self::assertSame('invalid UTF-8 replaced with U+FFFD', $notes[0]->reason);
    }

    public function test_valid_text_gives_no_note(): void
    {
        foreach ([Math::row('x+é'), Math::text('héllo'), Math::fenced('(', ')', 'x'), Math::styled('ab', 'bold'), Math::bigOp('∑', 'i', 'n'), Math::accent('x', "\u{0302}")] as $node) {
            self::assertSame([], Math::notes($node));
        }
    }

    public function test_public_node_constructors_scrub_silently(): void
    {
        foreach ([new \Quoin\Math\Node\Identifier("a\xff"), new \Quoin\Math\Node\Text("a\xff"), new \Quoin\Math\Node\Number("1\xff"), new \Quoin\Math\Node\Fenced("\xff", ')', Math::row('x'))] as $node) {
            self::assertGreaterThan(0.0, Math::layout($node)->width);
        }
    }
}
