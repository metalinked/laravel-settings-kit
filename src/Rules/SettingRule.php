<?php

namespace Metalinked\LaravelSettingsKit\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Metalinked\LaravelSettingsKit\Definition;
use Metalinked\LaravelSettingsKit\Exceptions\InvalidSettingValue;

/**
 * Checks a form field the way ScopedSettings::fill() checks the setting behind
 * it, with the custom messages and attribute names of the surrounding
 * validator. Built by Settings::rules().
 */
class SettingRule implements ValidationRule, ValidatorAwareRule {
    /** Run for empty and missing fields too: not every setting accepts them. */
    public bool $implicit = true;

    protected ?ValidatorContract $validator = null;

    public function __construct(protected Definition $definition) {
    }

    public function setValidator(ValidatorContract $validator): static {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if ($this->definition->ignoresBlank($value)) {
            return;
        }

        $custom = $this->validator instanceof Validator ? $this->validator->customAttributes : [];
        $name = $custom[$attribute] ?? $this->definition->getLabel();
        $messages = $this->messagesFor($attribute);

        try {
            $this->definition->validate($value, $messages, $name);
        } catch (InvalidSettingValue $e) {
            $fail($this->typeMessage($e, $messages, $name));
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $fail($message);
            }
        }
    }

    /**
     * The custom messages that apply to this field, under the attribute name
     * Definition::validate() uses.
     *
     * @return array<string, mixed>
     */
    protected function messagesFor(string $attribute): array {
        $messages = [];

        $custom = $this->validator instanceof Validator ? $this->validator->customMessages : [];

        foreach ($custom as $key => $message) {
            if (str_starts_with((string) $key, "{$attribute}.")) {
                $messages['value.' . substr((string) $key, strlen($attribute) + 1)] = $message;
            } elseif (!str_contains((string) $key, '.')) {
                $messages[$key] = $message;
            }
        }

        return $messages;
    }

    /**
     * A type error, replaced by the custom message of the matching Laravel rule.
     *
     * @param array<string, mixed> $messages
     */
    protected function typeMessage(InvalidSettingValue $e, array $messages, string $name): string {
        $rule = match ($e->messageLine()) {
            'number' => 'numeric',
            'one_of' => 'in',
            default => $e->messageLine(),
        };
        $custom = $messages["value.{$rule}"] ?? $messages[$rule] ?? null;

        return is_string($custom) ? str_replace(':attribute', $name, $custom) : $e->messageFor($name);
    }
}
