<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Metalinked\LaravelSettingsKit\Http\SettingsController;

/**
 * Entry point behind the Settings facade and the settings() helper.
 * Calls without a scope (Settings::get(), Settings::set()…) act on global settings.
 *
 * @mixin ScopedSettings
 */
class SettingsManager {
    protected ?ScopedSettings $global = null;

    public function __construct(
        protected SettingsRegistry $registry,
        protected SettingsRepository $repository,
    ) {
    }

    /**
     * Settings of one model, e.g. Settings::for($user)->get('theme').
     * Also accepts a morph type and id: Settings::for('team', 5).
     */
    public function for(Model|string $settable, int|string|null $id = null): ScopedSettings {
        return new ScopedSettings(
            $this->registry,
            $this->repository,
            Scope::of($settable, $id),
            $settable instanceof Model ? $settable : null,
        );
    }

    public function global(): ScopedSettings {
        return $this->global ??= new ScopedSettings($this->registry, $this->repository, Scope::global());
    }

    /**
     * Declare a setting with the fluent builder, typically in a service provider:
     * Settings::define('appearance.theme')->select(['light', 'dark'])->default('light')->overridable();
     */
    public function define(string $key): Definition {
        return $this->registry->define($key);
    }

    public function definition(string $key): ?Definition {
        return $this->registry->get($key);
    }

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return $this->registry->all();
    }

    public function registry(): SettingsRegistry {
        return $this->registry;
    }

    /**
     * Drop every cached value (all scopes).
     */
    public function flushCache(): void {
        $this->repository->flush();
    }

    /**
     * Register the JSON endpoints inside your own route group, so your
     * middleware (auth:sanctum, etc.) applies:
     *
     *     Route::middleware('auth:sanctum')->group(fn () => Settings::routes());
     */
    public function routes(string $prefix = 'settings'): void {
        Route::prefix($prefix)->name('settings-kit.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::patch('/', [SettingsController::class, 'update'])->name('update');
            Route::get('me', [SettingsController::class, 'indexMine'])->name('me.index');
            Route::patch('me', [SettingsController::class, 'updateMine'])->name('me.update');
            Route::delete('me/{key?}', [SettingsController::class, 'destroyMine'])->name('me.destroy');
        });
    }

    public function __call(string $method, array $arguments): mixed {
        return $this->global()->{$method}(...$arguments);
    }
}
