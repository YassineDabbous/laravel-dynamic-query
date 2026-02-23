<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\Post;
use YassineDabbous\DynamicQuery\Tests\Models\User;

class DynamicSortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTable('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->timestamps();
        });
        $this->createTable('posts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable(); $table->string('title'); $table->integer('likes')->default(0); $table->timestamps();
        });
        Post::create(['user_id' => 1, 'title' => 'A', 'likes' => 10]);
        Post::create(['title' => 'A', 'user_id' => 1]);
        Post::create(['title' => 'Z', 'user_id' => 1, 'likes' => 50]);
    }

    /** @test */
    public function it_sorts_asc() { $this->assertEquals('A', Post::dynamicSort(['_sort'=>'title'], ['title'])->first()->title); }
    /** @test */
    public function it_sorts_desc() { $this->assertEquals('Z', Post::dynamicSort(['_sort'=>'-title'], ['title'])->first()->title); }
    /** @test */
    public function it_sorts_multiple_cols() { Post::create(['title'=>'A','likes'=>5]); $this->assertEquals(0, Post::dynamicSort(['_sort'=>'title,likes'], ['title','likes'])->first()->likes); }
    /** @test */
    public function it_sorts_multiple_cols_mixed() { Post::create(['title'=>'A','likes'=>5]); $this->assertEquals(10, Post::dynamicSort(['_sort'=>'title,-likes'], ['title','likes'])->first()->likes); }
    /** @test */
    public function it_ignores_non_whitelisted() { $q = Post::dynamicSort(['_sort'=>'likes'], ['title']); $this->assertStringNotContainsString('order by "likes"', $q->toSql()); }
    /** @test */
    public function it_joins_relation_for_sorting() { $u = User::create(['name'=>'J']); Post::create(['user_id'=>$u->id,'title'=>'T']); $q = Post::dynamicSort(['_sort'=>'user.name'], ['user.name']); $this->assertStringContainsString('"users"."name"', $q->toSql()); }
    /** @test */
    public function it_handles_json_path_sorting() { $q = Post::dynamicSort(['_sort'=>'meta->key'], ['meta->key']); $this->assertStringContainsString('json_extract', $q->toSql()); }
    /** @test */
    public function it_handles_desc_json_path_sorting() { $q = Post::dynamicSort(['_sort'=>'-meta->key'], ['meta->key']); $this->assertStringContainsString('desc', $q->toSql()); }
    /** @test */
    public function it_applies_defaults_when_empty_input() { $this->assertEquals('Z', Post::dynamicSort([], ['title'], ['title'=>'desc'])->first()->title); }
    /** @test */
    public function it_input_overrides_defaults() { $this->assertEquals('A', Post::dynamicSort(['_sort'=>'title'], ['title'], ['title'=>'desc'])->first()->title); }
    /** @test */
    public function it_respects_ignore_list() { $q = Post::dynamicSort(['_sort'=>'title'], ['title'], [], ['title']); $this->assertStringNotContainsString('order by "title"', $q->toSql()); }
    /** @test */
    public function it_handles_null_input_gracefully() { $this->assertNotEmpty(Post::dynamicSort(null)->get()); }
    /** @test */
    public function it_normalizes_array_sort_input() { $q = Post::dynamicSort(['_sort'=>['title', '-likes']], ['title', 'likes']); $this->assertStringContainsString('"likes" desc', $q->toSql()); }
    /** @test */
    public function it_handles_numeric_strings_in_array_input() { $q = Post::dynamicSort(['_sort'=>['title']], ['title']); $this->assertStringContainsString('order by "posts"."title" asc', $q->toSql()); }
    /** @test */
    public function it_ignores_internal_params_in_sort() { $q = Post::dynamicSort(['_fields'=>'id','_sort'=>'title'], ['title']); $this->assertStringContainsString('order by "posts"."title"', $q->toSql()); }
    /** @test */
    public function it_qualifies_columns_correctly() { $q = Post::dynamicSort(['_sort'=>'title'], ['title']); $this->assertStringContainsString('"posts"."title"', $q->toSql()); }
    /** @test */
    public function it_handles_multiple_dots_in_nested_relations_if_any() { $q = Post::dynamicSort([], ['user.profile.age']); $this->assertNotEmpty($q); }
    /** @test */
    public function it_preserves_previous_order_bys() { $q = Post::orderBy('id')->dynamicSort(['_sort'=>'title'], ['title']); $this->assertStringContainsString('order by "id" asc, "posts"."title" asc', $q->toSql()); }
    /** @test */
    public function it_sanitizes_sort_keys() { $q = Post::dynamicSort(['_sort'=>"id; drop table users"], ['id']); $this->assertStringNotContainsString('drop table', $q->toSql()); }
    /** @test */
    public function it_handles_empty_string_sort() { $this->assertNotEmpty(Post::dynamicSort(['_sort'=>''], ['title'])->get()); }
}
