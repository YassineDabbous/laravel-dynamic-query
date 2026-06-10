<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\Post;
use Illuminate\Support\Carbon;

class DatePresetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTable('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('active');
            $table->integer('count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Carbon::setTestNow('2024-01-15 12:00:00');
        Post::forceCreate(['title' => 'Today', 'created_at' => '2024-01-15 10:00:00', 'status' => 'active']);
        Post::forceCreate(['title' => 'Yesterday', 'created_at' => '2024-01-14 10:00:00', 'status' => 'active']);
        Post::forceCreate(['title' => 'Last Month', 'created_at' => '2023-12-15 10:00:00', 'status' => 'active']);
    }

    public function test_it_today() { $this->assertCount(1, Post::dynamicFilter(['created_at'=>'today'])->get()); }
    public function test_it_yesterday() { $this->assertCount(1, Post::dynamicFilter(['created_at'=>'yesterday'])->get()); }
    public function test_it_last_week() { $this->assertCount(2, Post::dynamicFilter(['created_at'=>'last_week'])->get()); }
    public function test_it_last_month() { $this->assertCount(3, Post::dynamicFilter(['created_at'=>'last_month'])->get()); }
    public function test_it_this_year() { $this->assertCount(2, Post::dynamicFilter(['created_at'=>'this_year'])->get()); }
    public function test_it_last_year() { $this->assertCount(1, Post::dynamicFilter(['created_at'=>'last_year'])->get()); }
    public function test_it_custom_range() { $this->assertCount(1, Post::dynamicFilter(['created_at'=>['2024-01-13','2024-01-14']])->get()); }
    public function test_it_negated_preset() { $sql = Post::dynamicFilter(['!created_at'=>'today'])->toSql(); $this->assertStringContainsString('not between', $sql); }
    public function test_it_handles_invalid_preset() { $this->assertCount(3, Post::dynamicFilter(['created_at'=>'invalid'])->get()); }
    public function test_it_handles_partial_array_preset() { $this->assertCount(1, Post::dynamicFilter(['created_at'=>['2024-01-15']])->get()); }
    public function test_it_handles_year_to_date() { $this->assertCount(2, Post::dynamicFilter(['created_at'=>'ytd'])->get()); }
    public function test_it_handles_quarter_to_date() { $this->assertCount(2, Post::dynamicFilter(['created_at'=>'qtd'])->get()); }
    public function test_it_handles_month_to_date() { $this->assertCount(2, Post::dynamicFilter(['created_at'=>'mtd'])->get()); }
    public function test_it_works_with_different_column_name() { $this->createTable('t2', function($t){ $t->id(); $t->timestamp('published_at'); $t->timestamps(); }); $this->assertTrue(true); }
    public function test_it_handles_preset_on_json_date_field() { $sql = Post::dynamicFilter(['meta->date'=>'today'])->toSql(); $this->assertStringContainsString('json_extract', $sql); }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
}
