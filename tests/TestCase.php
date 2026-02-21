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
    }
}
