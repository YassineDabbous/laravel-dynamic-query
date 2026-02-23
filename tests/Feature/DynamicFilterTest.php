<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\Post;
use YassineDabbous\DynamicQuery\Tests\Models\User;

class DynamicFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTable('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->json('profile')->nullable();
            $table->timestamps();
        });

        $this->createTable('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('status')->default('active');
            $table->integer('count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        User::forceCreate(['id' => 1, 'name' => 'Alice', 'email' => 'alice@test.com', 'profile' => ['age' => 20]]);
        User::forceCreate(['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com', 'profile' => ['age' => 30]]);
        Post::forceCreate(['user_id' => 1, 'title' => 'Post A', 'status' => 'active', 'count' => 10, 'meta' => ['key' => 'val1']]);
        Post::forceCreate(['user_id' => 1, 'title' => 'Post B', 'status' => 'inactive', 'count' => 20, 'meta' => ['key' => 'val2']]);
    }

    /** @test */
    public function it_filters_by_eq() { $this->assertCount(1, Post::dynamicFilter(['title' => 'Post A'])->get()); }
    /** @test */
    public function it_filters_by_neq() { $this->assertCount(1, Post::dynamicFilter(['title'=>'Post A'], [], [], ['title'=>'!='])->get()); }
    /** @test */
    public function it_filters_by_gt() { $this->assertCount(1, Post::dynamicFilter(['count'=>10], [], [], ['count'=>'>'])->get()); }
    /** @test */
    public function it_filters_by_gte() { $this->assertCount(2, Post::dynamicFilter(['count'=>10], [], [], ['count'=>'>='])->get()); }
    /** @test */
    public function it_filters_by_lt() { $this->assertCount(1, Post::dynamicFilter(['count'=>20], [], [], ['count'=>'<'])->get()); }
    /** @test */
    public function it_filters_by_lte() { $this->assertCount(2, Post::dynamicFilter(['count'=>20], [], [], ['count'=>'<='])->get()); }
    /** @test */
    public function it_filters_by_like() { $this->assertCount(2, Post::dynamicFilter(['title'=>'%Post%'], [], [], ['title'=>'like'])->get()); }
    /** @test */
    public function it_filters_by_like_prefix() { $this->assertCount(2, Post::dynamicFilter(['title'=>'Post'], [], [], ['title'=>'like%'])->get()); }
    /** @test */
    public function it_filters_by_like_suffix() { $this->assertCount(1, Post::dynamicFilter(['title'=>'A'], [], [], ['title'=>'%like'])->get()); }
    /** @test */
    public function it_filters_by_like_both() { $this->assertCount(2, Post::dynamicFilter(['title'=>'ost'], [], [], ['title'=>'%like%'])->get()); }
    /** @test */
    public function it_filters_by_not_like() { $this->assertCount(1, Post::dynamicFilter(['title'=>'%Post A%'], [], [], ['title'=>'!like'])->get()); }
    /** @test */
    public function it_filters_by_in() { $this->assertCount(2, Post::dynamicFilter(['title'=>['Post A', 'Post B']], [], [], ['title'=>'in'])->get()); }
    /** @test */
    public function it_filters_by_not_in() { $this->assertCount(1, Post::dynamicFilter(['title'=>['Post A']], [], [], ['title'=>'!in'])->get()); }
    /** @test */
    public function it_filters_by_between() { $this->assertCount(1, Post::dynamicFilter(['count'=>[5,15]], [], [], ['count'=>'between'])->get()); }
    /** @test */
    public function it_filters_by_not_between() { $this->assertCount(1, Post::dynamicFilter(['count'=>[5,15]], [], [], ['count'=>'!between'])->get()); }
    /** @test */
    public function it_filters_by_null() { $this->assertCount(0, Post::dynamicFilter(['title'=>true], [], [], ['title'=>'null'])->get()); }
    /** @test */
    public function it_filters_by_not_null() { $this->assertCount(2, Post::dynamicFilter(['title'=>true], [], [], ['title'=>'!null'])->get()); }
    /** @test */
    public function it_filters_by_json_contains() { $this->assertCount(1, Post::dynamicFilter(['meta'=>['key'=>'val1']], [], [], ['meta'=>'json_contains'])->get()); }
    /** @test */
    public function it_filters_by_json_contains_key() { $this->assertCount(2, Post::dynamicFilter(['meta'=>'key'], [], [], ['meta'=>'json_contains_key'])->get()); }
    /** @test */
    public function it_filters_by_has_relation() { $this->assertCount(2, Post::dynamicFilter(['user'=>true], [], [], ['user'=>'has'])->get()); }
    /** @test */
    public function it_filters_by_not_has_relation() { $this->assertCount(0, Post::dynamicFilter(['user'=>true], [], [], ['user'=>'!has'])->get()); }
    /** @test */
    public function it_filters_relation_field() { $this->assertCount(2, Post::dynamicFilter(['user.name'=>'Alice'])->get()); }
    /** @test */
    public function it_filters_json_field_path() { $this->assertCount(1, Post::dynamicFilter(['meta->key'=>'val1'])->get()); }
    /** @test */
    public function it_supports_or_logic() { $this->assertCount(2, Post::dynamicFilter(['title'=>'Post A', 'status'=>'inactive', '_logic'=>'or'])->get()); }
    /** @test */
    public function it_supports_having_clause() { $this->assertStringContainsString('having', Post::dynamicFilter(['title'=>'A', '_clause'=>'having'])->toSql()); }
    /** @test */
    public function it_ignores_internal_params() { $this->assertCount(2, Post::dynamicFilter(['_sort'=>'id'])->get()); }
    /** @test */
    public function it_handles_empty_input() { $this->assertCount(2, Post::dynamicFilter([])->get()); }
    /** @test */
    public function it_sanitizes_injection_attempts() { $this->assertCount(0, Post::dynamicFilter(['title'=>"'; DROP TABLE users; --"])->get()); }
    /** @test */
    public function it_respects_per_field_operator_override() { $this->assertCount(1, Post::dynamicFilter(['count'=>'10', '_operators'=>['count'=>'>']])->get()); } // count is 10, so >10 is false for the only match? No, wait. 
    /** @test */
    public function it_handles_json_array_contains() { 
        User::create(['name'=>'J1', 'email'=>'j1@test.com', 'profile'=>['tags'=>['a', 'b']]]);
        $this->assertCount(1, User::dynamicFilter(['profile->tags'=>'a'], [], [], ['profile->tags'=>'json_contains'])->get());
    }
    /** @test */
    public function it_filters_by_multiple_values_for_same_key() { $this->assertCount(2, Post::dynamicFilter(['count'=>['10', '20']])->get()); }
    /** @test */
    public function it_handles_zero_as_filter_value() { 
        Post::create(['user_id'=>1, 'title'=>'Zero', 'count'=>0]);
        $this->assertCount(1, Post::dynamicFilter(['count'=>0])->get());
    }
    /** @test */
    public function it_handles_boolean_false_as_filter_value() {
        $this->assertCount(0, Post::dynamicFilter(['status'=>false])->get());
    }
    /** @test */
    public function it_ignores_fields_not_in_dynamic_filters_if_strict() {
        config(['dynamic-query.settings.strict_filtering'=>true]);
        // Assuming 'count' is allowed but 'secret' is not
        $this->assertCount(2, Post::dynamicFilter(['secret'=>'val'], ['count'])->get());
    }
}
