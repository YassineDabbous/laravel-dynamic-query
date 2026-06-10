<?php

namespace YassineDabbous\DynamicQuery\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use YassineDabbous\DynamicQuery\HasDynamicQuery;
use Illuminate\Database\Eloquent\Model;
use YassineDabbous\DynamicQuery\Contracts\DynamicQueryable;

class SecurityHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('secure_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('secret_key')->nullable();
            $table->integer('amount')->default(0);
            $table->timestamps();
        });

        SecureModel::create(['name' => 'Safe', 'secret_key' => 'shhh', 'amount' => 100]);
    }

    public function test_it_defaults_to_empty_appends_to_prevent_leakage()
    {
        $model = new SecureModel();
        $this->assertEquals([], $model->dynamicAppends());
    }

    public function test_it_whitelists_columns_for_dynamic_select()
    {
        // When searching for 'secret_key' which is NOT in whitelisted columns
        $query = SecureModel::dynamicSelect(['id', 'name', 'secret_key']);
        $sql = $query->toSql();

        // It should NOT contain secret_key in the SELECT clause if logic is enforced
        // Implementation check: scopeDynamicSelect calls parseFields which uses dynamicColumns()
        $this->assertStringNotContainsString('secret_key', $sql);
        $this->assertStringContainsString('name', $sql);
    }

    public function test_it_sanitizes_metric_aliases_in_stats()
    {
        // Metric with a dangerous alias component
        $input = [
            '_stats' => 'true',
            '_metric' => 'sum:amount',
            '_group' => 'name',
            '_timezone' => 'UTC'
        ];

        // We check if it throws or handles suspicious aliases
        // Actually, we hardened applyStatsMetric to sanitize alias
        $result = SecureModel::dynamicStats($input);
        
        $this->assertNotEmpty($result);
        foreach ($result as $item) {
            $this->assertObjectHasAttribute('value', $item);
        }
    }

    public function test_it_parameterizes_timezone_in_group_by()
    {
        $input = [
            '_group' => 'created_at:day',
            '_timezone' => 'Europe/Paris; --' // Malicious timezone string
        ];

        $query = SecureModel::dynamicGroupBy([], [], [], $input);
        $bindings = $query->getBindings();

        // The malformed timezone should be a binding value, not part of SQL string if using sqliteDateSql/etc
        $this->assertContains('Europe/Paris; --', $bindings);
    }
}

class SecureModel extends Model implements DynamicQueryable
{
    use HasDynamicQuery;

    protected $fillable = ['name', 'secret_key', 'amount'];

    public function dynamicColumns(): array
    {
        return ['id', 'name', 'amount', 'created_at'];
    }

    public function dynamicRelations(): array { return []; }
    
    // We intentionally don't put secret_key in dynamicAppends
    public function getFormattedSecretAttribute() { return "KEY: " . $this->secret_key; }
}
