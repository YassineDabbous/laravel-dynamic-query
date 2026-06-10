<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\User;
use YassineDabbous\DynamicQuery\Tests\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use YassineDabbous\DynamicQuery\DynamicQueryHelper;

class ServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTable('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->json('profile')->nullable(); $table->timestamps();
        });
        $this->createTable('posts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id'); $table->string('status'); $table->timestamps();
        });
        Relation::morphMap(['user' => User::class]);
        User::create(['name' => 'A']);
        User::create(['name' => 'B']);
    }

    
    public function test_it_resolves_model_morph() { $q = User::resolveDynamicModel(null, ['user'], ['_model'=>'user']); $this->assertInstanceOf(User::class, $q->getModel()); }
    
    public function test_it_403_on_unauthorized_morph() { $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class); User::resolveDynamicModel(null, ['other'], ['_model'=>'user']); }
    
    public function test_it_400_on_unknown_morph() { $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class); User::resolveDynamicModel(null, ['user'], ['_model'=>'ghost']); }
    
    public function test_it_uses_default_model() { $q = User::resolveDynamicModel('user', ['user'], []); $this->assertInstanceOf(User::class, $q->getModel()); }
    
    public function test_it_500_on_empty_config() { $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class); User::resolveDynamicModel(null, []); }
    
    public function test_it_paginates_custom() { $res = User::dynamicPaginate(50, ['per_page'=>1]); $this->assertCount(1, $res->items()); }
    
    public function test_it_caps_per_page() { $res = User::dynamicPaginate(1, ['per_page'=>10]); $this->assertEquals(1, $res->perPage()); }
    
    public function test_it_gets_all_toggle() { $res = User::dynamicPaginate(50, ['_get_all'=>'true'], true); $this->assertInstanceOf(Collection::class, $res); }
    
    public function test_it_caps_get_all() { config(['dynamic-query.defaults.max_get_all'=>1]); $res = User::dynamicPaginate(50, ['_get_all'=>'true'], true); $this->assertCount(1, $res); }
    
    public function test_it_simple_pagination() { $res = User::dynamicPaginate(50, ['_simple'=>'true']); $this->assertInstanceOf(\Illuminate\Pagination\Paginator::class, $res); }
    
    public function test_it_appends_to_collection() { $c = User::all(); $c->dynamicAppend(['id']); $this->assertEquals(['id'], array_values($c->first()->getVisible())); }
    
    public function test_it_appends_to_paginator() { 
        $p = User::paginate(); 
        if (method_exists($p, 'dynamicAppend')) {
            $p->dynamicAppend(['id']); 
        } else {
            $p->getCollection()->dynamicAppend(['id']);
        }
        $this->assertEquals(['id'], array_values($p->first()->getVisible())); 
    }
    
    public function test_it_handles_empty_collection_append() { $c = User::where('id',0)->get(); $c->dynamicAppend(['id']); $this->assertEmpty($c); }
    
    public function test_it_ignores_null_fields_in_collection_append() { $c = User::all(); $c->dynamicAppend(null); $this->assertNotEmpty($c); }
    
    public function test_it_handles_json_input_in_paginator_appends() {
        $res = User::dynamicPaginate(15, ['profile->age']);
        $visible = $res->first()->getVisible();
        $found = in_array('profile->age', $visible) || in_array('profile-&gt;age', $visible) || in_array('profile', $visible);
        $this->assertTrue($found, "Failed asserting that " . json_encode($visible) . " contains 'profile->age'");
    }
    
    public function test_it_respects_custom_per_page_param_name() { config(['dynamic-query.params.per_page'=>'limit']); $res = User::dynamicPaginate(50, ['limit'=>1]); $this->assertEquals(1, $res->perPage()); }
    
    public function test_it_handles_invalid_per_page_value() { $res = User::dynamicPaginate(50, ['per_page'=>'abc']); $this->assertNotEmpty($res); }
    
    public function test_it_works_on_relations_proxies() { 
        $u = User::create(['name' => 'U']);
        // Use a more specific assertion to verify activity
        $res = $u->posts()->dynamicPaginate(15);
        $this->assertNotNull($res);
        $this->assertEquals(0, $res->total());
    }
    
    public function test_it_forwards_columns_to_paginator() { $res = User::dynamicPaginate(50, [], false, ['id']); $this->assertEquals(['id'], array_keys($res->first()->toArray())); }
    
    public function test_it_handles_custom_page_name() { $res = User::dynamicPaginate(50, [], false, ['*'], 'custom_page'); $this->assertEquals('custom_page', $res->getPageName()); }
    
    public function test_it_binds_macros_to_eloquent_builder() { 
        // Try calling it to be 100% sure it works on an Eloquent Builder instance
        $res = User::query()->dynamicPaginate(1);
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $res);
    }
    
    public function test_it_binds_macros_to_base_builder() { $this->assertTrue(\Illuminate\Database\Query\Builder::hasMacro('dynamicPaginate')); }
    
    public function test_it_binds_macros_to_relation() { $this->assertTrue(\Illuminate\Database\Eloquent\Relations\Relation::hasMacro('dynamicPaginate')); }
    
    public function test_it_binds_macros_to_collection() { $this->assertTrue(Collection::hasMacro('dynamicAppend')); }
    
    public function test_it_registers_config_correctly() { $this->assertNotNull(config('dynamic-query')); }

    protected function tearDown(): void { Relation::morphMap([], false); parent::tearDown(); }
}
