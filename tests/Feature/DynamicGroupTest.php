<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\Post;

class DynamicGroupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTable('posts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id'); $table->string('title'); $table->string('status'); $table->timestamps();
        });
        Post::forceCreate(['user_id' => 1, 'title' => 'P1', 'status' => 'active', 'created_at' => '2024-01-15 10:00:00']);
        Post::forceCreate(['title' => 'P2', 'status' => 'active', 'user_id' => 1, 'created_at' => '2024-01-20 11:00:00']);
        Post::forceCreate(['title' => 'P3', 'status' => 'inactive', 'user_id' => 1, 'created_at' => '2024-02-15 10:00:00']);
    }

    /** @test */
    public function it_groups_by_single_col() { $this->assertCount(2, Post::dynamicGroupBy(['_group'=>'status'], ['status'])->get()); }
    /** @test */
    public function it_groups_by_multiple_cols() { $this->assertCount(2, Post::dynamicGroupBy(['_group'=>'status,user_id'], ['status','user_id'])->get()); }
    /** @test */
    public function it_supports_array_input() { $this->assertCount(2, Post::dynamicGroupBy(['_group'=>['status','user_id']], ['status','user_id'])->get()); }
    /** @test */
    public function it_ignores_non_whitelisted() { $q = Post::dynamicGroupBy(['_group'=>'user_id'], ['status']); $this->assertStringNotContainsString('group by "user_id"', $q->toSql()); }
    /** @test */
    public function it_respects_ignore_param() { $q = Post::dynamicGroupBy(['_group'=>'status'], ['status'], [], ['status']); $this->assertStringNotContainsString('group by "status"', $q->toSql()); }
    /** @test */
    public function it_applies_defaults() { $this->assertStringContainsString('group by "posts"."status"', Post::dynamicGroupBy([], ['status'], ['status'])->toSql()); }
    /** @test */
    public function it_validates_defaults() { $this->assertStringNotContainsString('group by "user_id"', Post::dynamicGroupBy([], ['status'], ['user_id'])->toSql()); }
    /** @test */
    public function it_groups_by_year() { $res = Post::dynamicGroupBy(['_group'=>'created_at:year'], ['created_at'])->get(); $this->assertCount(1, $res); }
    /** @test */
    public function it_groups_by_month() { $this->assertCount(2, Post::dynamicGroupBy(['_group'=>'created_at:month'], ['created_at'])->get()); }
    /** @test */
    public function it_groups_by_day() { $this->assertCount(2, Post::dynamicGroupBy(['_group'=>'created_at:day'], ['created_at'])->get()); }
    /** @test */
    public function it_groups_by_hour() { $this->assertCount(2, Post::dynamicGroupBy(['_group'=>'created_at:hour'], ['created_at'])->get()); }
    /** @test */
    public function it_ignores_invalid_macro() { $q = Post::dynamicGroupBy(['_group'=>'created_at:week'], ['created_at']); $this->assertStringNotContainsString('strftime', $q->toSql()); }
    /** @test */
    public function it_joins_relation_for_grouping() { $q = Post::dynamicGroupBy(['_group'=>'user.name'], ['user.name']); $this->assertStringContainsString('inner join "users"', $q->toSql()); }
    /** @test */
    public function it_qualifies_group_columns() { $q = Post::dynamicGroupBy(['_group'=>'status'], ['status']); $this->assertStringContainsString('"posts"."status"', $q->toSql()); }
    /** @test */
    public function it_handles_json_path_grouping() { $q = Post::dynamicGroupBy(['_group'=>'meta->key'], ['meta->key']); $this->assertStringContainsString('json_extract', $q->toSql()); }
    /** @test */
    public function it_handles_multiple_date_macros() { $q = Post::dynamicGroupBy(['_group'=>'created_at:year,created_at:month'], ['created_at']); $this->assertStringContainsString('created_at_year', $q->toSql()); $this->assertStringContainsString('created_at_month', $q->toSql()); }
    /** @test */
    public function it_validates_timezones_utc() { $model = new Post(); $this->assertEquals('UTC', $this->callProtectedMethod($model, 'validateTimezone', ['Invalid'])); }
    /** @test */
    public function it_accepts_valid_named_timezone() { $model = new Post(); $this->assertEquals('Europe/London', $this->callProtectedMethod($model, 'validateTimezone', ['Europe/London'])); }
    /** @test */
    public function it_accepts_offset_timezone() { $model = new Post(); $this->assertEquals('+02:00', $this->callProtectedMethod($model, 'validateTimezone', ['+02:00'])); }
    /** @test */
    public function it_sanitizes_group_alias() { $model = new Post(); $this->assertEquals('posts_status', $this->callProtectedMethod($model, 'sanitizeAlias', ['posts.status'])); }
    /** @test */
    public function it_handles_null_group_input() { $this->assertNotEmpty(Post::dynamicGroupBy(null)->get()); }
    /** @test */
    public function it_groups_by_aliased_column() { 
        $q = Post::selectRaw('status as st')->dynamicGroupBy(['_group'=>'st'], ['st', 'status']); 
        $this->assertMatchesRegularExpression('/group by (["`]?posts["`]?\.)?["`]?st["`]?/i', $q->toSql()); 
    }
    /** @test */
    public function it_handles_empty_string_group() { $this->assertNotEmpty(Post::dynamicGroupBy(['_group'=>''], ['status'])->get()); }
    /** @test */
    public function it_preserves_selects_when_grouping() { $q = Post::select('id')->dynamicGroupBy(['_group'=>'status'], ['status']); $this->assertStringContainsString('select "id"', $q->toSql()); }
    /** @test */
    public function it_handles_integer_column_grouping() { $this->assertNotEmpty(Post::dynamicGroupBy(['_group'=>'user_id'], ['user_id'])->get()); }
    /** @test */
    public function it_works_with_custom_param_name() { config(['dynamic-query.params.group'=>'g']); $this->assertCount(2, Post::dynamicGroupBy(['g'=>'status'], ['status'])->get()); }

    protected function callProtectedMethod($object, $method, array $args = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
