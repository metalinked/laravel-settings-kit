<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Support\ServiceProvider;
use Metalinked\LaravelSettingsKit\Console\Commands\ExportSettingsCommand;
use Metalinked\LaravelSettingsKit\Console\Commands\ImportSettingsCommand;
use Metalinked\LaravelSettingsKit\Services\SettingsService;

class SettingsKitServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/settings-kit.php',
            'settings-kit'
        );

        $this->app->singleton('settings-kit', function ($app) {
            return new SettingsService();
        });

        $this->app->alias('settings-kit', SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/settings-kit.php' => config_path('settings-kit.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'migrations');

            $this->commands([
                ExportSettingsCommand::class,
                ImportSettingsCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['settings-kit', SettingsService::class];
    }
}
