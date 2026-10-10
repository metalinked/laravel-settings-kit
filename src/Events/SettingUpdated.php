<?php

namespace Metalinked\LaravelSettingsKit\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Metalinked\LaravelSettingsKit\Scope;

/**
 * Dispatched after a value is stored, once the surrounding transaction commits.
 */
class SettingUpdated {
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly mixed $previous,
        public readonly Scope $scope,
        public readonly ?Model $settable = null,
    ) {
    }
}
