<?php

namespace Metalinked\LaravelSettingsKit\Exceptions;

use InvalidArgumentException;

/**
 * A value does not fit its setting's type. Surfaced to callers as a
 * ValidationException keyed by the setting key.
 */
class InvalidSettingValue extends InvalidArgumentException {
}
