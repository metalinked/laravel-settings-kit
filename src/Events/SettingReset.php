<?php

namespace Metalinked\LaravelSettingsKit\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Metalinked\LaravelSettingsKit\Scope;

/**
 * Dispatched after a stored value is removed, so the scope falls back to the
 * global value (for models) or to the code default (for global settings).
 */
class SettingReset {
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly mixed $previous,
        public readonly Scope $scope,
        public readonly ?Model $settable = null,
    ) {
    }
}
