<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use YassineDabbous\DynamicQuery\Tests\TestCase;

class ConfigTest extends TestCase
{
    /** @test */
    public function it_has_all_config_keys_loaded()
    {
        $this->assertNotNull(config('dynamic-query.defaults.per_page'));
        $this->assertNotNull(config('dynamic-query.settings.strict_filtering'));
        $this->assertNotNull(config('dynamic-query.params.fields'));
    }

    /** @test */
    public function it_respects_custom_param_names()
    {
        config(['dynamic-query.params.fields' => 'custom_fields']);
        
        $model = new \YassineDabbous\DynamicQuery\Tests\Models\Post();
        $result = $this->callProtectedMethod($model, 'parseFields', [[], ['custom_fields' => 'id']]);
        $this->assertArrayHasKey('id', $result['fields']);
    }

    protected function callProtectedMethod($object, $method, array $args = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
