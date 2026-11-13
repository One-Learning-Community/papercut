<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use PHPUnit\Framework\TestCase;
use Papercut\Math\OperatorDictionary;

final class OperatorDictionaryTest extends TestCase
{
    public function test_relation_spacing_is_five_each_side(): void
    {
        $e = OperatorDictionary::lookup('=', 'infix');
        $this->assertSame([5, 5], [$e['lspace'], $e['rspace']]);
    }

    public function test_binary_operator_spacing_is_four_each_side(): void
    {
        $e = OperatorDictionary::lookup('+', 'infix');
        $this->assertSame([4, 4], [$e['lspace'], $e['rspace']]);
    }

    public function test_comma_is_flush_left_with_thin_space_after(): void
    {
        $e = OperatorDictionary::lookup(',', 'infix');
        $this->assertSame([0, 3], [$e['lspace'], $e['rspace']]);
    }

    public function test_opening_paren_prefix_is_flush_and_stretchy(): void
    {
        $e = OperatorDictionary::lookup('(', 'prefix');
        $this->assertSame([0, 0], [$e['lspace'], $e['rspace']]);
        $this->assertTrue($e['stretchy']);
        $this->assertTrue($e['symmetric']);
    }

    public function test_plus_minus_prefix_has_no_space(): void
    {
        $e = OperatorDictionary::lookup('±', 'prefix');
        $this->assertSame([0, 0], [$e['lspace'], $e['rspace']]);
    }

    public function test_minus_sign_is_flush_as_prefix_and_binary_as_infix(): void
    {
        $this->assertSame([0, 0], $this->spaces(OperatorDictionary::lookup('−', 'prefix')));
        $this->assertSame([4, 4], $this->spaces(OperatorDictionary::lookup('−', 'infix')));
    }

    public function test_summation_is_a_large_operator(): void
    {
        $this->assertTrue(OperatorDictionary::lookup('∑', 'prefix')['largeop']);
        $this->assertFalse(OperatorDictionary::lookup('=', 'infix')['largeop']);
    }

    public function test_unknown_character_gets_the_default_spacing(): void
    {
        $e = OperatorDictionary::lookup('☃', 'infix');
        $this->assertSame([5, 5], [$e['lspace'], $e['rspace']]);
        $this->assertFalse($e['stretchy']);
    }

    public function test_missing_form_falls_back_to_infix_then_postfix_then_prefix(): void
    {
        // '+' has no postfix entry: infix wins over prefix.
        $this->assertSame([4, 4], $this->spaces(OperatorDictionary::lookup('+', 'postfix')));
        // '!' has postfix and prefix but no infix: postfix wins.
        $this->assertSame(OperatorDictionary::lookup('!', 'postfix'), OperatorDictionary::lookup('!', 'infix'));
        // '∑' has only prefix.
        $this->assertTrue(OperatorDictionary::lookup('∑', 'infix')['largeop']);
    }

    /**
     * @param array{lspace: int, rspace: int, stretchy: bool, symmetric: bool, largeop: bool, movablelimits: bool} $e
     * @return array{int, int}
     */
    private function spaces(array $e): array
    {
        return [$e['lspace'], $e['rspace']];
    }
}
