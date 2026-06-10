<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\User;
use YassineDabbous\DynamicQuery\Tests\Models\Post;

class DynamicFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTable('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email'); $table->timestamps();
        });
        $this->createTable('posts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id'); $table->string('title'); $table->text('content')->nullable(); $table->string('status')->default('active'); $table->timestamps();
        });
        User::create(['name' => 'U1', 'email' => 'u1@test.com']);
        Post::create(['user_id' => 1, 'title' => 'P1', 'content' => 'Text', 'status' => 'active']);
    }

    public function test_it_resolves_input_from_request() { request()->merge(['_fields'=>'id']); $this->assertEquals(['_fields'=>'id'], $this->callProtectedMethod(new User(), 'resolveDynamicInput', [[]])); }
    public function test_it_gets_value_from_dot_input() { $this->assertEquals('J', $this->callProtectedMethod(new User(), 'getDynamicValue', [['u'=>['n'=>'J']], 'u.n'])); }
    public function test_it_parses_simple_csv_fields() { $res = $this->callProtectedMethod(new Post(), 'parseFields', [[], ['_fields'=>'id,title']]); $this->assertArrayHasKey('id', $res['fields']); $this->assertArrayHasKey('title', $res['fields']); }
    public function test_it_parses_deep_pipe_fields() { $res = $this->callProtectedMethod(new Post(), 'parseFields', [['user:id|name']]); $this->assertEquals(['id', 'name'], $res['deepFields']['user']); }
    public function test_it_handles_trailing_commas() { $res = $this->callProtectedMethod(new Post(), 'parseFields', [[], ['_fields'=>'id,']]); $this->assertArrayHasKey('id', $res['fields']); $this->assertCount(1, $res['fields']); }
    public function test_it_handles_empty_elements_in_csv() { $res = $this->callProtectedMethod(new Post(), 'parseFields', [[], ['_fields'=>',id,,title,']]); $this->assertCount(2, $res['fields']); }
    public function test_it_selects_all_with_asterisk() { $this->assertStringContainsString('select *', Post::dynamicSelect([], ['*'])->toSql()); }
    public function test_it_selects_all_with_null_input() { $this->assertStringContainsString('select *', Post::dynamicSelect(null)->toSql()); }
    public function test_it_includes_dynamic_aggregates() { $query = User::dynamicSelect([], ['posts_count']); $this->assertStringContainsString('count(*)', strtolower($query->toSql())); }
    public function test_it_ignores_non_whitelisted_columns() { $res = $this->callProtectedMethod(new User(), 'parseFields', [['secret']]); $this->assertArrayNotHasKey('secret', $res['fields']); }
    public function test_it_automatically_includes_id() { $sql = Post::dynamicSelect([], ['title'])->toSql(); $this->assertMatchesRegularExpression('/["`]id["`]/i', $sql); }
    public function test_it_automatically_includes_user_id_for_user_relation() { $sql = Post::dynamicSelect([], ['user'])->toSql(); $this->assertMatchesRegularExpression('/["`]user_id["`]/i', $sql); }
    public function test_it_eager_loads_relations_even_with_empty_fields() { $query = Post::dynamicSelect([], ['user:']); $this->assertArrayHasKey('user', $query->getEagerLoads()); }
    public function test_it_cleans_response_if_enabled() { config(['dynamic-query.settings.cleanup_response'=>true]); $p = Post::first(); $this->callProtectedMethod(new Post(), 'cleanResponse', [$p, ['title']]); $this->assertEquals(['title'], array_keys($p->toArray())); }
    public function test_it_skips_clean_on_wildcard() { config(['dynamic-query.settings.cleanup_response'=>true]); $p = Post::first(); $this->callProtectedMethod(new Post(), 'cleanResponse', [$p, ['*']]); $this->assertGreaterThan(1, count($p->toArray())); }
    public function test_it_handles_multilevel_json_select() { 
        $sql = Post::dynamicSelect([], ['meta->deep->key'])->toSql();
        $this->assertTrue(str_contains($sql, 'meta') && str_contains($sql, 'deep') && str_contains($sql, 'key'));
    }
    public function test_it_selects_multiple_json_fields() { 
        $sql = Post::dynamicSelect([], ['meta->a', 'meta->b'])->toSql();
        $this->assertTrue(str_contains($sql, 'meta') && str_contains($sql, 'a') && str_contains($sql, 'b'));
    }public function test_it_resolves_append_dependencies_recursively() { $sql = Post::dynamicSelect([], ['slug'])->toSql(); $this->assertMatchesRegularExpression('/["`]title["`]/i', $sql); }
    public function test_it_handles_null_provided_fields_safely() { $this->assertNotEmpty(Post::dynamicSelect(null)->get()); }
    public function test_it_sets_visible_on_appends() { $p = Post::first(); $p->dynamicAppend(['id', 'slug']); $this->assertEquals(['id', 'slug'], array_values($p->getVisible())); }
    public function test_it_appends_only_whitelisted_accessors() { $p = Post::first(); $p->dynamicAppend(['formatted_date']); $this->assertNotContains('formatted_date', $p->getAppends()); }
    public function test_it_handles_json_field_aliasing_if_implemented() { $sql = Post::dynamicSelect([], ['meta->key as alias'])->toSql(); $this->assertMatchesRegularExpression('/as ["`]alias["`]/i', $sql); }
    public function test_it_handles_numeric_index_strings_in_input() { $res = $this->callProtectedMethod(new Post(), 'parseFields', [[], ['_fields'=>['id', 'title']]]); $this->assertArrayHasKey('id', $res['fields']); }
}
