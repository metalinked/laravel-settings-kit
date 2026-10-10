<?php

namespace Metalinked\LaravelSettingsKit\Exceptions;

use InvalidArgumentException;

class SettingNotOverridableException extends InvalidArgumentException {
    public static function for(string $key, ?string $model): self {
        $who = $model !== null ? "[{$model}] models" : 'this scope';

        return new self("Setting '{$key}' cannot be overridden by {$who}. Mark it as overridable() in its definition.");
    }
}
