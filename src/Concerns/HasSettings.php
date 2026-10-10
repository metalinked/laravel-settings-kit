<?php

namespace Metalinked\LaravelSettingsKit\Concerns;

use Illuminate\Database\Eloquent\Model;
use Metalinked\LaravelSettingsKit\ScopedSettings;
use Metalinked\LaravelSettingsKit\SettingsManager;

/**
 * Gives a model its own settings: $user->settings()->get('theme').
 * If your model already has a `settings` attribute or relation, use
 * Settings::for($model) instead of this trait.
 */
trait HasSettings {
    public static function bootHasSettings(): void {
        static::deleted(function (Model $model) {
            // Keep overrides while a soft-deleted model can still be restored.
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            app(SettingsManager::class)->for($model)->reset();
        });
    }

    public function settings(): ScopedSettings {
        return app(SettingsManager::class)->for($this);
    }
}
