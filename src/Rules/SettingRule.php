<?php

namespace Metalinked\LaravelSettingsKit\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Definition;
use Metalinked\LaravelSettingsKit\Exceptions\InvalidSettingValue;

/**
 * Checks a form field the way ScopedSettings::fill() checks the setting behind
 * it. Built by Settings::rules().
 */
class SettingRule implements ValidationRule {
    /** Run for empty and missing fields too: not every setting accepts them. */
    public bool $implicit = true;

    public function __construct(protected Definition $definition) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if ($this->definition->ignoresBlank($value)) {
            return;
        }

        try {
            $this->definition->validate($value);
        } catch (InvalidSettingValue $e) {
            $fail($e->messageFor($this->definition->getLabel()));
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $fail($message);
            }
        }
    }
}
