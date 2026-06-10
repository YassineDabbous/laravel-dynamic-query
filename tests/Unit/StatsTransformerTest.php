<?php

namespace YassineDabbous\DynamicQuery\Tests\Unit;

use YassineDabbous\DynamicQuery\StatsTransformer;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use Illuminate\Support\Collection;

class StatsTransformerTest extends TestCase
{
    
    public function test_it_resolves_to_meta_summary_and_dataset() { $res = StatsTransformer::make([['value'=>10]]); $this->assertArrayHasKey('meta', $res); $this->assertArrayHasKey('summary', $res); $this->assertArrayHasKey('dataset', $res); }
    
    public function test_it_calculates_total_summary() { $res = StatsTransformer::make([['value'=>10],['value'=>20]]); $this->assertEquals(30, $res['summary']['value']); }
    
    public function test_it_calculates_avg_summary() { $res = StatsTransformer::make([['value'=>10],['value'=>20]], ['_metric'=>'avg:amount']); $this->assertEquals(15, $res['summary']['value']); }
    
    public function test_it_builds_dataset_with_labels() { $res = StatsTransformer::make([['s'=>'a','value'=>10]],['_group'=>'s']); $this->assertEquals('a', $res['dataset'][0]['label']); }
    
    public function test_it_matches_previous_data() { $res = StatsTransformer::make(['current'=>[['s'=>'a','value'=>10]],'previous'=>[['s'=>'a','value'=>5]]],['_group'=>'s']); $this->assertEquals(5, $res['dataset'][0]['previous_value']); }
    
    public function test_it_includes_transforms() { $res = StatsTransformer::make([(object)['value'=>10,'cumulative_value'=>10]]); $this->assertEquals(10, $res['dataset'][0]['transforms']->cumulative); }
    
    public function test_it_handles_empty_data_gracefully() { $res = StatsTransformer::make([]); $this->assertEmpty($res['dataset']); }
    
    public function test_it_handles_comparison_with_no_matches_in_previous() { $res = StatsTransformer::make(['current'=>[['s'=>'a','value'=>10]],'previous'=>[]],['_group'=>'s']); $this->assertEquals(0, $res['dataset'][0]['previous_value']); }
    
    public function test_it_formats_large_numbers_in_summary() { $res = StatsTransformer::make([['value'=>1000000]]); $this->assertEquals(1000000, $res['summary']['value']); }
    
    public function test_it_includes_metric_meta_information() { $res = StatsTransformer::make([], ['_metric'=>'sum:amount']); $this->assertEquals('sum', $res['meta']['metric']['type']); }
    
    public function test_it_includes_timezone_meta() { $res = StatsTransformer::make([], ['_timezone'=>'UTC']); $this->assertEquals('UTC', $res['meta']['timezone']); }
}
