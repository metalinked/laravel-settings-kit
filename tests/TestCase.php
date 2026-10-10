<?php

namespace Metalinked\LaravelSettingsKit\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Metalinked\LaravelSettingsKit\SettingsKitServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {
    use RefreshDatabase;

    protected function getPackageProviders($app): array {
        return [SettingsKitServiceProvider::class];
    }

    protected function getPackageAliases($app): array {
        return ['Settings' => \Metalinked\LaravelSettingsKit\Facades\Settings::class];
    }

    protected function defineEnvironment($app): void {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
    }

    protected function defineDatabaseMigrations(): void {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function afterRefreshingDatabase(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->softDeletes();
        });
    }
}
