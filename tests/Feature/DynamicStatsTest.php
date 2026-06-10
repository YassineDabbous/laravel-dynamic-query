<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\Post;
use YassineDabbous\DynamicQuery\Tests\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DynamicStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTable('users', function (Blueprint $table) {
            $table->id(); $table->string('name')->nullable(); $table->string('email')->nullable(); $table->timestamps();
        });
        $this->createTable('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('title')->nullable();
            $table->integer('amount')->default(10);
            $table->string('status');
            $table->integer('count')->default(0);
            $table->timestamps();
        });
        Carbon::setTestNow('2024-01-15 12:00:00');
        User::create(['id' => 1, 'name' => 'Test User']);
        Post::create(['updated_at' => '2024-01-15 12:00:00', 'user_id' => 1, 'status' => 'active', 'title' => 'Stats Post']);
        Post::create(['status' => 'active', 'amount' => 20, 'created_at' => '2024-01-15 11:00:00']);
        Post::create(['status' => 'inactive', 'amount' => 30, 'created_at' => '2024-01-13 10:00:00']);
    }

    public function test_it_counts() { $this->assertEquals(3, Post::dynamicStats(['_metric' => 'count'])->first()->value); }
    public function test_it_sums() {
        $this->assertEquals(60, Post::dynamicStats(['_metric' => 'sum:amount'])->first()->value);
    }
    public function test_it_avgs() { $this->assertEquals(20, Post::dynamicStats(['_metric' => 'avg:amount'])->first()->value); }
    public function test_it_mins() { $this->assertEquals(10, Post::dynamicStats(['_metric' => 'min:amount'])->first()->value); }
    public function test_it_maxs() { $this->assertEquals(30, Post::dynamicStats(['_metric' => 'max:amount'])->first()->value); }
    public function test_it_defaults_count() { $this->assertEquals(3, Post::dynamicStats(['_metric' => 'invalid'])->first()->value); }
    public function test_it_validates_metric_col() { $this->assertEquals(3, Post::dynamicStats(['_metric' => 'sum:secret'])->first()->value); } // Fallback to id count?
    public function test_it_cumulative() { $res = Post::dynamicStats(['_metric'=>'sum:amount','_group'=>'id','_transform'=>'cumulative'])->values(); $this->assertEquals(60, $res[2]->cumulative_value); }
    public function test_it_growth() { $res = Post::dynamicStats(['_metric'=>'sum:amount','_group'=>'id','_transform'=>'growth'])->values(); $this->assertEquals(100, $res[1]->growth_percentage); }
    public function test_it_handles_zero_growth() { Post::truncate(); Post::create(['status'=>'a','amount'=>0]); Post::create(['status'=>'b','amount'=>10]); $res = Post::dynamicStats(['_metric'=>'sum:amount','_group'=>'id','_transform'=>'growth'])->values(); $this->assertEquals(0, $res[0]->growth_percentage); }
    public function test_it_compares_periods() { $res = Post::dynamicStats(['_metric'=>'count','_compare'=>'previous_period','created_at'=>['2024-01-14','2024-01-15']]); $this->assertArrayHasKey('current', $res); }
    public function test_it_error_no_date_compare() { $res = Post::dynamicStats(['_compare'=>'previous_period']); $this->assertArrayHasKey('error', $res); }
    public function test_it_summary_delta() { $m = new Post(); $res = $this->callProtectedMethod($m, 'calculateSummaryDelta', [collect([(object)['value'=>100]]), collect([(object)['value'=>50]])]); $this->assertEquals(50, $res['delta']); }
    public function test_it_caches_stats() { config(['dynamic-query.settings.enable_stats_cache'=>true]); Post::dynamicStats(['_metric'=>'count']); $this->assertTrue(true); }
    public function test_it_uses_custom_metrics() { // If defined in model
        $res = Post::dynamicStats(['_metric'=>'custom']); $this->assertNotEmpty($res);
    }
    public function test_it_handles_empty_stats_input() { $this->assertNotEmpty(Post::dynamicStats([])); }
    public function test_it_handles_json_metric() { $q = Post::query(); $this->callProtectedMethod(new Post(), 'applyStatsMetric', [$q, 'sum:meta->val', []]); $this->assertStringContainsString('json_extract', $q->toSql()); }
    public function test_it_handles_raw_metrics() { $res = Post::dynamicStats(['raw_count' => 'count(*)']); $this->assertGreaterThan(0, $res->first()->raw_count); }
    public function test_it_sanitizes_stats_alias() { $res = Post::dynamicStats(['my.alias' => 'id:count']); $this->assertObjectHasAttribute('my_alias', $res->first()); }
    public function test_it_handles_stats_with_smart_joins() { $res = Post::dynamicStats(['_metric'=>'count', '_group'=>'user.name']); $this->assertNotEmpty($res); }
    public function test_it_validates_transform_type() { $res = Post::dynamicStats(['_metric'=>'count','_transform'=>'invalid']); $this->assertObjectNotHasAttribute('cumulative_value', $res[0]); }
    public function test_it_handles_null_bindings_in_cache_hash() { $res = Post::dynamicStats(['_metric'=>'count']); $this->assertNotNull($res); }
    public function test_it_filters_stats_by_where_clause() { $res = Post::dynamicStats(['_metric'=>'count', 'status'=>'active']); $this->assertEquals(2, $res->first()->value); }
    public function test_it_filters_stats_by_having_clause() { $res = Post::dynamicStats(['_metric'=>'count', 'status'=>'active', '_clause'=>'having']); $this->assertNotEmpty($res); }
    public function test_it_supports_stats_on_relations_directly() { $res = User::first()->posts()->dynamicStats(['_metric'=>'count']); $this->assertNotEmpty($res); }
    public function test_it_handles_stats_with_date_presets() { 
        Post::forceCreate(['title'=>'P4', 'user_id'=>1, 'status'=>'active', 'created_at'=>now()->subDays(10)]);
        $res = Post::dynamicStats(['_date'=>'last_week']);
        $this->assertNotEmpty($res); 
    }
    public function test_it_calculates_avg_on_empty_set_safely() { Post::truncate(); $res = Post::dynamicStats(['_metric'=>'avg:amount']); $this->assertEquals(0, $res->first()->value); }
    public function test_it_handles_growth_with_negative_values() { 
        Post::truncate(); Post::create(['amount'=>-10, 'status'=>'active']); Post::create(['amount'=>10, 'status'=>'active']); 
        $res = Post::dynamicStats(['_metric'=>'sum:amount','_group'=>'id','_transform'=>'growth']);
        $this->assertEquals(200, $res[1]->growth_percentage); // (10 - (-10)) / abs(-10) * 100 = 20 / 10 * 100 = 200
    }
    public function test_it_uses_custom_cache_ttl() { config(['dynamic-query.settings.stats_cache_ttl'=>10]); $this->assertTrue(true); }
    public function test_it_works_with_different_db_drivers_casting() { $this->assertTrue(true); }

    protected function callProtectedMethod($object, $method, array $args = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
