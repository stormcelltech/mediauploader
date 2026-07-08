<?php

namespace StormcellTech\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use StormcellTech\MediaUploaderServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MediaUploaderServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Force the framework test runner to run entirely inside an isolated SQLite memory bubble
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Automatically find and execute your internal migration script inside the isolated memory block
        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');
    }
}
