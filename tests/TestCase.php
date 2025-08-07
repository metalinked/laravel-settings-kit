<?php

namespace Metalinked\LaravelSettingsKit\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Metalinked\LaravelSettingsKit\SettingsKitServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array {
        return [
            SettingsKitServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array {
        return [
            'Settings' => \Metalinked\LaravelSettingsKit\Facades\Settings::class,
        ];
    }

    protected function defineEnvironment($app): void {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('settings-kit.cache.enabled', false);
    }
}
