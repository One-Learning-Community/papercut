<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\NoteLevel;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class TreeAssertTest extends TestCase
{
    public function test_equal_trees_pass(): void
    {
        TreeAssert::assertSameTree(Math::frac('a + 1', 'b'), Math::frac('a + 1', 'b'));
        self::addToAssertionCount(1);
    }

    public function test_one_different_identifier_fails_naming_the_path(): void
    {
        $a = new Row([new Identifier('p'), new Scripts(new Identifier('x'), new Identifier('i'), null)]);
        $b = new Row([new Identifier('p'), new Scripts(new Identifier('x'), new Identifier('j'), null)]);
        try {
            TreeAssert::assertSameTree($a, $b);
            self::fail('expected a difference');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('root.children[1].sub.name', $e->getMessage());
            self::assertStringContainsString("expected 'i', got 'j'", $e->getMessage());
        }
    }

    public function test_class_differences_are_reported(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('expected Identifier, got Number');
        TreeAssert::assertSameTree(new Row([new Identifier('x'), new Identifier('y')]), new Row([new Identifier('x'), new \Papercut\Math\Node\Number('1')]));
    }

    public function test_fallbacks_and_single_child_rows_are_normalised(): void
    {
        $note = new MathNote(NoteLevel::Approximated, 'e', 'm');
        $x = new Identifier('x');
        TreeAssert::assertSameTree($x, new Fallback(new Row([$x]), $note));
        TreeAssert::assertSameTree(new Row([$x, new Identifier('y')]), new Row([$x, new Fallback(null, $note), new Fallback(new Identifier('y'), $note)]));
        self::addToAssertionCount(1);
    }

    public function test_floats_compare_within_a_tolerance(): void
    {
        TreeAssert::assertSameTree(Math::space(0.5), Math::space(0.5 + 1e-8));
        $this->expectException(AssertionFailedError::class);
        TreeAssert::assertSameTree(Math::space(0.5), Math::space(0.51));
    }
}
