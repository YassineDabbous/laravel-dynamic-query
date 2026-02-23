<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\User;
use YassineDabbous\DynamicQuery\Contracts\DynamicQueryable;

class ContractTest extends TestCase
{
    /** @test */
    public function it_implements_dynamic_queryable_interface()
    {
        $user = new User();
        $this->assertInstanceOf(DynamicQueryable::class, $user);
    }

    /** @test */
    public function it_has_required_methods_from_contract()
    {
        $user = new User();
        $this->assertTrue(method_exists($user, 'dynamicColumns'));
        $this->assertTrue(method_exists($user, 'dynamicRelations'));
        $this->assertTrue(method_exists($user, 'dynamicAppends'));
        $this->assertTrue(method_exists($user, 'dynamicAggregates'));
        $this->assertTrue(method_exists($user, 'dynamicFilters'));
        $this->assertTrue(method_exists($user, 'dynamicSorts'));
        $this->assertTrue(method_exists($user, 'dynamicGroups'));
        $this->assertTrue(method_exists($user, 'dynamicMetrics'));
        $this->assertTrue(method_exists($user, 'requiredColumns'));
    }
}
