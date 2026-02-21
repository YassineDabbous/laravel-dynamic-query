<?php

namespace YassineDabbous\DynamicQuery\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use YassineDabbous\DynamicQuery\HasDynamicQuery;
use Illuminate\Database\Eloquent\Model;
use YassineDabbous\DynamicQuery\Contracts\DynamicQueryable;

class TestModel extends Model implements DynamicQueryable
{
    use HasDynamicQuery;
    protected $table = 'test_models';
    protected $guarded = [];

    public function dynamicColumns(): array {
        return ['id', 'name', 'status', 'created_at'];
    }
}

class BasicQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });

        TestModel::create(['name' => 'Item 1', 'status' => 'active']);
        TestModel::create(['name' => 'Item 2', 'status' => 'inactive']);
    }

    public function test_it_can_filter_dynamically()
    {
        $result = TestModel::dynamicFilter([], [], [], ['name' => 'Item 1'])->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Item 1', $result->first()->name);
    }

    public function test_it_can_sort_dynamically()
    {
        $result = TestModel::dynamicSort([], [], ['_sort' => '-name'])->get();

        $this->assertEquals('Item 2', $result->first()->name);
    }
}
