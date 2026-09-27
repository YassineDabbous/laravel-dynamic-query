<?php

namespace YassineDabbous\DynamicQuery\Tests\Unit;

use Illuminate\Support\Facades\Validator;
use YassineDabbous\DynamicQuery\Http\Requests\Concerns\ValidatesDynamicQuery;
use YassineDabbous\DynamicQuery\Tests\TestCase;

class ValidatesDynamicQueryTest extends TestCase
{
    use ValidatesDynamicQuery;

    public function test_it_passes_empty_input()
    {
        $this->assertFalse(
            Validator::make([], $this->dynamicQueryRules())->fails()
        );
    }

    public function test_it_passes_a_valid_dynamic_payload()
    {
        $validator = Validator::make([
            '_fields' => 'id,name',
            '_sort' => '-created_at',
            '_logic' => 'or',
            '_operators' => ['name' => '%like%'],
            'per_page' => 20,
            '_timezone' => 'UTC',
        ], $this->dynamicQueryRules());

        $this->assertFalse($validator->fails());
    }

    public function test_it_rejects_unknown_logic_values()
    {
        $validator = Validator::make(['_logic' => 'xor'], $this->dynamicQueryRules());

        $this->assertTrue($validator->fails());
    }

    public function test_it_enforces_per_page_bounds()
    {
        $this->assertTrue(
            Validator::make(['per_page' => 500], $this->dynamicQueryRules())->fails()
        );
        $this->assertFalse(
            Validator::make(['per_page' => 100], $this->dynamicQueryRules())->fails()
        );
    }

    public function test_it_rejects_unknown_transform_values()
    {
        $validator = Validator::make(['_transform' => 'pivot'], $this->dynamicQueryRules());

        $this->assertTrue($validator->fails());
    }
}
