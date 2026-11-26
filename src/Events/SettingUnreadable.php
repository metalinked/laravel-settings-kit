<?php

namespace Metalinked\LaravelSettingsKit\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Metalinked\LaravelSettingsKit\Scope;

/**
 * Dispatched when a stored value cannot be read and its fallback is used
 * instead. Once per request or job for each scope and key. It never carries
 * the stored value.
 */
class SettingUnreadable {
    use Dispatchable;

    /** The setting is encrypted and the stored value cannot be decrypted. */
    public const DECRYPTION = 'decryption';

    /** The stored value does not fit the definition. */
    public const INVALID = 'invalid';

    public function __construct(
        public readonly string $key,
        public readonly string $reason,
        public readonly Scope $scope,
        public readonly ?Model $settable = null,
    ) {
    }
}
