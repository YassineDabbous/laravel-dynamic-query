<?php

namespace YassineDabbous\DynamicQuery\Tests\Unit;

use YassineDabbous\DynamicQuery\DynamicQueryHelper;
use YassineDabbous\DynamicQuery\Tests\TestCase;

class DynamicQueryHelperTest extends TestCase
{
    /** @test */
    public function it_converts_indexed_strings_to_associative()
    {
        $input = ['a', 'b'];
        $expected = ['a' => null, 'b' => null];
        $this->assertEquals($expected, DynamicQueryHelper::toAssociative($input));
    }

    /** @test */
    public function it_keeps_already_keyed_entries()
    {
        $input = ['x' => 'y'];
        $expected = ['x' => 'y'];
        $this->assertEquals($expected, DynamicQueryHelper::toAssociative($input));
    }

    /** @test */
    public function it_handles_mixed_arrays()
    {
        $input = ['a', 'b' => 'c'];
        $expected = ['a' => null, 'b' => 'c'];
        $this->assertEquals($expected, DynamicQueryHelper::toAssociative($input));
    }

    /** @test */
    public function it_skips_non_scalar_indexed_values()
    {
        $input = [['nested']];
        $expected = [];
        $this->assertEquals($expected, DynamicQueryHelper::toAssociative($input));
    }

    /** @test */
    public function it_handles_empty_arrays()
    {
        $this->assertEquals([], DynamicQueryHelper::toAssociative([]));
    }

    /** @test */
    public function it_handles_integer_values()
    {
        $input = [0, 1, 2];
        $expected = [0 => null, 1 => null, 2 => null];
        $this->assertEquals($expected, DynamicQueryHelper::toAssociative($input));
    }

    /** @test */
    public function it_normalizes_and_wraps_scalar_values_in_array()
    {
        $input = ['a' => '='];
        $expected = ['a' => ['=']];
        $this->assertEquals($expected, DynamicQueryHelper::normalizeAssociativeArray($input));
    }

    /** @test */
    public function it_normalizes_null_to_empty_array()
    {
        $input = ['a' => null];
        $expected = ['a' => []];
        $this->assertEquals($expected, DynamicQueryHelper::normalizeAssociativeArray($input));
    }

    /** @test */
    public function it_normalizes_and_keeps_existing_arrays()
    {
        $input = ['a' => ['=', '>']];
        $expected = ['a' => ['=', '>']];
        $this->assertEquals($expected, DynamicQueryHelper::normalizeAssociativeArray($input));
    }

    /** @test */
    public function it_normalizes_indexed_keys_first()
    {
        $input = ['name'];
        $expected = ['name' => []];
        $this->assertEquals($expected, DynamicQueryHelper::normalizeAssociativeArray($input));
    }

    /** @test */
    public function it_sanitizes_valid_aliases()
    {
        $this->assertEquals('valid_alias_1', DynamicQueryHelper::sanitizeAlias('valid_alias_1'));
    }

    /** @test */
    public function it_sanitizes_aliases_by_replacing_dots()
    {
        $this->assertEquals('table_column', DynamicQueryHelper::sanitizeAlias('table.column'));
    }

    /** @test */
    public function it_sanitizes_aliases_by_stripping_suspicious_characters()
    {
        $this->assertEquals('a__DROP_TABLE__', DynamicQueryHelper::sanitizeAlias('a; DROP TABLE--'));
    }

    /** @test */
    public function it_handles_empty_string_alias_sanitization()
    {
        $this->assertEquals('', DynamicQueryHelper::sanitizeAlias(''));
    }

    /** @test */
    public function it_resolves_single_level_recursive_dependencies()
    {
        $associative = ['a' => 'b', 'b' => null];
        $keys = ['a'];
        $result = DynamicQueryHelper::recursiveDependencies($associative, $keys);
        $this->assertContains('a', $result);
        $this->assertContains('b', $result);
    }

    /** @test */
    public function it_resolves_chained_recursive_dependencies()
    {
        $associative = ['a' => 'b', 'b' => 'c', 'c' => null];
        $keys = ['a'];
        $result = DynamicQueryHelper::recursiveDependencies($associative, $keys);
        $this->assertContains('c', $result);
    }

    /** @test */
    public function it_handles_no_dependencies()
    {
        $associative = ['x' => null];
        $keys = ['x'];
        $this->assertEquals(['x'], DynamicQueryHelper::recursiveDependencies($associative, $keys));
    }

    /** @test */
    public function it_handles_circular_dependencies_safely()
    {
        $associative = ['a' => 'b', 'b' => 'a'];
        $keys = ['a'];
        $result = DynamicQueryHelper::recursiveDependencies($associative, $keys);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_respects_max_iterations_in_recursive_dependencies()
    {
        $associative = [];
        for ($i = 0; $i < 100; $i++) { $associative["k$i"] = "k" . ($i + 1); }
        $keys = ['k0'];
        $result = DynamicQueryHelper::recursiveDependencies($associative, $keys);
        $this->assertLessThanOrEqual(52, count($result));
    }

    /** @test */
    public function it_ignores_unrequested_keys_in_recursive_dependencies()
    {
        $associative = ['a' => 'b', 'c' => 'd'];
        $keys = ['a'];
        $result = DynamicQueryHelper::recursiveDependencies($associative, $keys);
        $this->assertNotContains('c', $result);
    }

    /** @test */
    public function it_handles_self_dependency()
    {
        $associative = ['a' => 'a'];
        $keys = ['a'];
        $result = DynamicQueryHelper::recursiveDependencies($associative, $keys);
        $this->assertEquals(['a'], $result);
    }

    /** @test */
    public function it_handles_multiple_dependencies() { 
        $res = DynamicQueryHelper::resolveRecursiveDependencies(['a'], ['a' => ['b', 'c']]);
        $this->assertCount(3, $res);
        $this->assertContains('a', $res);
        $this->assertContains('b', $res);
        $this->assertContains('c', $res);
    }

    /** @test */
    public function it_handles_non_existent_dependency()
    {
        $associative = ['a' => 'missing'];
        $keys = ['a'];
        $result = DynamicQueryHelper::recursiveDependencies($associative, $keys);
        $this->assertContains('a', $result);
        $this->assertContains('missing', $result);
    }
}
