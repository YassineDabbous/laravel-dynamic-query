<?php
 
namespace YassineDabbous\DynamicQuery\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use YassineDabbous\DynamicQuery\DynamicQueryServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            DynamicQueryServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        config()->set('dynamic-query.settings.relation_guess', true);
    }

    protected function createTable(string $table, \Closure $callback)
    {
        \Illuminate\Support\Facades\Schema::create($table, $callback);
    }

    protected function callProtectedMethod($object, $method, array $args = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    protected function assertObjectHasAttribute($attribute, $object, $message = '')
    {
        // Eloquent models store attributes in an array, property_exists doesn't work for them.
        $hasAttribute = property_exists($object, $attribute) || (method_exists($object, 'getAttributes') && array_key_exists($attribute, $object->getAttributes())) || isset($object->$attribute);
        $this->assertTrue($hasAttribute, $message ?: "Failed asserting that object has property or attribute '{$attribute}'.");
    }

    protected function assertObjectNotHasAttribute($attribute, $object, $message = '')
    {
        $this->assertFalse(property_exists($object, $attribute), $message ?: "Failed asserting that object does not have property '{$attribute}'.");
    }
}
