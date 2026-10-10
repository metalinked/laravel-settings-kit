<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Metalinked\LaravelSettingsKit\Console\ClearCacheCommand;
use Metalinked\LaravelSettingsKit\Console\ExportSettingsCommand;
use Metalinked\LaravelSettingsKit\Console\ImportSettingsCommand;
use Metalinked\LaravelSettingsKit\Console\ListSettingsCommand;
use Metalinked\LaravelSettingsKit\Console\PruneSettingsCommand;
use Metalinked\LaravelSettingsKit\Console\UpgradeFromV1Command;

class SettingsKitServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/settings-kit.php', 'settings-kit');

        // Definitions are declared once at boot and live for the whole process.
        $this->app->singleton(SettingsRegistry::class, function ($app) {
            return (new SettingsRegistry())->load($app['config']->get('settings-kit.settings', []));
        });

        // Stored values are memoised per request / queued job (Octane-safe).
        $this->app->scoped(SettingsRepository::class);
        $this->app->scoped(SettingsManager::class);
        $this->app->alias(SettingsManager::class, 'settings-kit');
    }

    public function boot(): void {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Blade::directive('setting', function (string $expression) {
            return "<?php echo e(\\Metalinked\\LaravelSettingsKit\\Definition::export(app(\\Metalinked\\LaravelSettingsKit\\SettingsManager::class)->get({$expression}))); ?>";
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/settings-kit.php' => config_path('settings-kit.php'),
            ], 'settings-kit-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'settings-kit-migrations');

            $this->commands([
                ListSettingsCommand::class,
                ClearCacheCommand::class,
                ExportSettingsCommand::class,
                ImportSettingsCommand::class,
                PruneSettingsCommand::class,
                UpgradeFromV1Command::class,
            ]);
        }
    }
}
