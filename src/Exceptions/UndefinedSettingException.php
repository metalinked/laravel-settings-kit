<?php

namespace Metalinked\LaravelSettingsKit\Exceptions;

use InvalidArgumentException;

class UndefinedSettingException extends InvalidArgumentException {
    public static function for(string $key): self {
        return new self("Setting '{$key}' is not defined. Declare it in config/settings-kit.php or with Settings::define().");
    }
}
