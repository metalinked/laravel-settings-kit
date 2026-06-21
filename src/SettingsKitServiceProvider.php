<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Metalinked\LaravelSettingsKit\Console\Commands\ClearCacheCommand;
use Metalinked\LaravelSettingsKit\Console\Commands\ExportSettingsCommand;
use Metalinked\LaravelSettingsKit\Console\Commands\ImportSettingsCommand;
use Metalinked\LaravelSettingsKit\Console\Commands\ListSettingsCommand;
use Metalinked\LaravelSettingsKit\Http\Middleware\SettingsKitApiAuth;
use Metalinked\LaravelSettingsKit\Services\SettingsService;

class SettingsKitServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/settings-kit.php',
            'settings-kit'
        );

        $this->app->singleton('settings-kit', function ($app) {
            return new SettingsService();
        });

        $this->app->alias('settings-kit', SettingsService::class);
    }

    public function boot(): void {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        $router = $this->app['router'];
        $router->aliasMiddleware('settings-kit.api.auth', SettingsKitApiAuth::class);

        Blade::directive('setting', function ($expression) {
            return "<?php echo e(\\Metalinked\\LaravelSettingsKit\\Facades\\Settings::get({$expression})); ?>";
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/settings-kit.php' => config_path('settings-kit.php'),
            ], ['config', 'settings-kit-config']);

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], ['migrations', 'settings-kit-migrations']);

            $this->commands([
                ExportSettingsCommand::class,
                ImportSettingsCommand::class,
                ListSettingsCommand::class,
                ClearCacheCommand::class,
            ]);
        }
    }

    public function provides(): array {
        return ['settings-kit', SettingsService::class];
    }
}
