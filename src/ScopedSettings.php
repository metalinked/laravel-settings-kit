<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Metalinked\LaravelSettingsKit\Events\SettingReset;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Exceptions\InvalidSettingValue;
use Metalinked\LaravelSettingsKit\Exceptions\SettingNotOverridableException;
use Metalinked\LaravelSettingsKit\Exceptions\UndefinedSettingException;
use Metalinked\LaravelSettingsKit\Rules\SettingRule;

/**
 * Settings as seen from one scope: global, or a model (user, team, tenant…).
 * A model reads its own value first, then the global value, then the default.
 */
class ScopedSettings {
    public function __construct(
        protected SettingsRegistry $registry,
        protected SettingsRepository $repository,
        protected Scope $scope,
        protected ?Model $settable = null,
    ) {
    }

    public function scope(): Scope {
        return $this->scope;
    }

    /**
     * Resolved value of a setting. $default is only used for keys that were
     * never defined; defined keys fall back to their declared default.
     */
    public function get(string $key, mixed $default = null): mixed {
        $definition = $this->registry->resolve($key);
        $fallback = $definition->isDefined() ? $definition->getDefault() : $default;

        if ($this->usesOwnValue($definition)) {
            $own = $this->repository->values($this->scope);

            if (array_key_exists($key, $own)) {
                return $definition->decode($own[$key], $this->globalValue($definition, $fallback));
            }
        }

        return $this->globalValue($definition, $fallback);
    }

    /**
     * Resolved values for several keys.
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * Shortcut for boolean settings.
     */
    public function enabled(string $key): bool {
        return (bool) $this->get($key);
    }

    /**
     * Store one value, or several at once (set(['a' => 1, 'b' => 2])).
     * Every value is validated first; nothing is written if any fails.
     *
     * @param string|array<string, mixed> $key
     *
     * @throws ValidationException
     */
    public function set(string|array $key, mixed $value = null): void {
        $values = is_array($key) ? $key : [$key => $value];
        $definitions = [];

        foreach (array_keys($values) as $k) {
            $definition = $this->registry->resolve((string) $k);

            if (!$this->usesOwnValue($definition)) {
                throw SettingNotOverridableException::for((string) $k, $this->scope->modelClass());
            }

            $definitions[$k] = $definition;
        }

        [$normalized, $errors] = $this->check($definitions, $values);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->write($definitions, $normalized);
    }

    /**
     * Save a submitted form in one call. Like set(), except that an empty field
     * (null or "") keeps an encrypted value, and removes the stored value of a
     * nullable setting so it falls back again. Keys that are not in the array
     * are left alone. Every field is checked before anything is written, and
     * keys that are undefined or not overridable here are validation errors,
     * so user input can be passed in. Anything but an array is one too.
     *
     * With the field => key map given to rules(), $values is read by field name
     * (such as the validated data of a request) and errors use those names.
     *
     * @param mixed $values setting key => value, or field name => value with $fields
     * @param array<array-key, string>|null $fields field name => setting key
     *
     * @throws ValidationException
     */
    public function fill(mixed $values, ?array $fields = null): void {
        if (!is_array($values)) {
            throw ValidationException::withMessages(['settings' => [Messages::get('not_array')]]);
        }

        $names = [];

        if ($fields !== null) {
            $input = [];

            foreach ($this->definitionsOfFields($fields, 'fill') as $field => $definition) {
                if (Arr::has($values, $field)) {
                    $input[$definition->key] = Arr::get($values, $field);
                    $names[$definition->key] = $field;
                }
            }

            $values = $input;
        }

        $definitions = [];
        $filled = [];
        $blank = [];
        $errors = [];

        foreach ($values as $k => $v) {
            $definition = $this->registry->get((string) $k);

            if ($definition === null) {
                $errors[$k] = [Messages::get('unknown', ['key' => $k])];
            } elseif (!$this->usesOwnValue($definition)) {
                $errors[$k] = [Messages::get('not_editable', ['key' => $k])];
            } elseif (!$definition->ignoresBlank($v)) {
                $definitions[$k] = $definition;
                $filled[$k] = $v;
            } elseif (!$definition->isEncrypted()) {
                $blank[] = (string) $k;
            }
        }

        [$normalized, $invalid] = $this->check($definitions, $filled);

        if ($errors + $invalid !== []) {
            $messages = [];

            foreach ($errors + $invalid as $k => $message) {
                $messages[$names[$k] ?? $k] = $message;
            }

            throw ValidationException::withMessages($messages);
        }

        DB::transaction(function () use ($definitions, $normalized, $blank) {
            $this->write($definitions, $normalized);
            $this->forget($blank);
        });
    }

    /**
     * Validation rules for form fields that hold settings, to check them with
     * the rest of a request and get the errors under the form's field names:
     *
     *     $request->validate(Settings::rules(['conversion' => 'points.conversion']));
     *
     * @param array<array-key, string> $fields field name => setting key
     * @return array<string, list<SettingRule>>
     */
    public function rules(array $fields): array {
        return array_map(
            fn (Definition $definition) => [new SettingRule($definition)],
            $this->definitionsOfFields($fields, 'rules')
        );
    }

    /**
     * Write stored values into Laravel's configuration, for settings that
     * declare a "config" key. A setting with nothing stored leaves the
     * configuration alone, undoing what an earlier call wrote. For a nullable
     * setting, a stored null or "" counts as nothing stored.
     *
     * @param list<string>|null $keys every setting with a "config" key when null
     */
    public function applyConfig(?array $keys = null): void {
        $keys ??= array_map('strval', array_keys(array_filter(
            $this->registry->all(),
            fn (Definition $definition) => $definition->getConfig() !== null
        )));
        $targets = [];

        foreach ($keys as $key) {
            $definition = $this->registry->get($key) ?? throw UndefinedSettingException::for($key);
            $path = $definition->getConfig()
                ?? throw new InvalidArgumentException("Setting '{$key}' has no config key in its definition.");
            $targets[] = [$definition, $path];
        }

        $bridge = app(ConfigBridge::class);
        $config = app('config');
        $nothing = new \stdClass();

        foreach ($targets as [$definition, $path]) {
            $value = $this->storedValue($definition, $nothing);

            if ($value === $nothing) {
                $bridge->restore($config, $path);
            } else {
                $bridge->write($config, $path, Definition::export($value));
            }
        }
    }

    /**
     * Whether this scope stores its own value for the key (for a model: whether
     * it overrides the global value). A stored value may equal the default.
     */
    public function has(string $key): bool {
        return array_key_exists($key, $this->repository->values($this->scope));
    }

    /**
     * Remove stored values so they fall back again (model → global → default).
     *
     * @param string|list<string> $keys
     */
    public function forget(string|array $keys): void {
        $keys = array_values(array_filter((array) $keys, fn ($key) => $this->has($key)));

        if ($keys === []) {
            return;
        }

        $previous = $this->many($keys);
        $this->repository->delete($this->scope, $keys);
        $this->dispatchResets($previous);
    }

    /**
     * Remove every value stored in this scope. Returns how many were removed.
     */
    public function reset(): int {
        $keys = array_keys($this->repository->values($this->scope));

        if ($keys === []) {
            return 0;
        }

        $previous = $this->many($keys);
        $deleted = $this->repository->delete($this->scope);
        $this->dispatchResets($previous);

        return $deleted;
    }

    /**
     * Resolved values of every defined setting, plus any undefined keys that
     * have a stored value.
     *
     * @return array<string, mixed>
     */
    public function all(): array {
        $keys = array_unique(array_merge(
            array_keys($this->registry->all()),
            array_keys($this->repository->values(Scope::global())),
            array_keys($this->repository->values($this->scope)),
        ));

        return $this->many(array_values(array_filter(
            $keys,
            fn ($key) => $this->registry->has($key) || !config('settings-kit.strict', false)
        )));
    }

    /**
     * Only the values stored in this scope (a model's overrides, or the stored
     * global values).
     *
     * @return array<string, mixed>
     */
    public function stored(): array {
        $values = [];

        foreach ($this->repository->values($this->scope) as $key => $raw) {
            if ($this->registry->has($key) || !config('settings-kit.strict', false)) {
                $values[$key] = $this->get($key);
            }
        }

        ksort($values);

        return $values;
    }

    /**
     * Everything a settings screen needs to render itself: one entry per
     * defined setting this scope may change, with labels, options and values.
     *
     * @return list<array<string, mixed>>
     */
    public function panel(?string $group = null): array {
        $items = [];

        foreach ($this->registry->all() as $key => $definition) {
            if (!$this->scope->isGlobal() && !$definition->isOverridableBy($this->scope->modelClass())) {
                continue;
            }

            if ($group !== null && $definition->getGroup() !== $group) {
                continue;
            }

            $value = $this->get($key);
            // What the value falls back to when this scope stores nothing.
            $default = $this->scope->isGlobal()
                ? $definition->getDefault()
                : $this->globalValue($definition, $definition->getDefault());
            // Secrets are write-only: a screen can replace them, never read them back.
            $secret = $definition->isEncrypted();

            $items[] = [
                'key' => $key,
                'group' => $definition->getGroup(),
                'type' => $definition->getType(),
                'label' => $definition->getLabel(),
                'description' => $definition->getDescription(),
                'value' => $secret ? null : Definition::export($value),
                'default' => $secret ? null : Definition::export($default),
                'filled' => $value !== null,
                'overridden' => $this->has($key),
                'encrypted' => $secret,
                'nullable' => $definition->isNullable(),
                'options' => $definition->getOptions(),
                'meta' => $definition->getMeta(),
            ];
        }

        return $items;
    }

    protected function usesOwnValue(Definition $definition): bool {
        return $this->scope->isGlobal() || $definition->isOverridableBy($this->scope->modelClass());
    }

    protected function globalValue(Definition $definition, mixed $fallback): mixed {
        $global = $this->repository->values(Scope::global());

        return array_key_exists($definition->key, $global)
            ? $definition->decode($global[$definition->key], $fallback)
            : $fallback;
    }

    /**
     * The value stored for this scope, or else for the global one; $fallback
     * when neither stores a usable value. Null and "" are not usable values of
     * a nullable setting.
     */
    protected function storedValue(Definition $definition, mixed $fallback): mixed {
        $scopes = [Scope::global()];

        if (!$this->scope->isGlobal() && $this->usesOwnValue($definition)) {
            $scopes[] = $this->scope;
        }

        $result = $fallback;

        foreach ($scopes as $scope) {
            $values = $this->repository->values($scope);

            if (!array_key_exists($definition->key, $values)) {
                continue;
            }

            $value = $definition->decode($values[$definition->key], $result);

            if (!$definition->isNullable() || ($value !== null && $value !== '')) {
                $result = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, string> $fields field name => setting key
     * @return array<string, Definition> by field name
     */
    protected function definitionsOfFields(array $fields, string $method): array {
        $definitions = [];

        foreach ($fields as $field => $key) {
            if (!is_string($field)) {
                throw new InvalidArgumentException("Pass {$method}() an array of field name => setting key, such as ['field' => '{$key}'].");
            }

            $definition = $this->registry->get($key) ?? throw UndefinedSettingException::for($key);

            if (!$this->usesOwnValue($definition)) {
                throw SettingNotOverridableException::for($key, $this->scope->modelClass());
            }

            $definitions[$field] = $definition;
        }

        return $definitions;
    }

    /**
     * @param array<array-key, Definition> $definitions
     * @param array<array-key, mixed> $values
     * @return array{array<array-key, mixed>, array<array-key, array<mixed>>} valid values and errors
     */
    protected function check(array $definitions, array $values): array {
        $valid = [];
        $errors = [];

        foreach ($values as $key => $value) {
            try {
                $valid[$key] = $definitions[$key]->validate($value);
            } catch (InvalidSettingValue $e) {
                $errors[$key] = [$e->messageFor($definitions[$key]->getLabel())];
            } catch (ValidationException $e) {
                $errors[$key] = $e->validator->errors()->all();
            }
        }

        return [$valid, $errors];
    }

    /**
     * Store values that already passed check().
     *
     * @param array<array-key, Definition> $definitions
     * @param array<array-key, mixed> $values
     */
    protected function write(array $definitions, array $values): void {
        if ($values === []) {
            return;
        }

        $previous = $this->many(array_map('strval', array_keys($values)));
        $encoded = [];

        foreach ($values as $k => $v) {
            $encoded[$k] = $definitions[$k]->encode($v);
        }

        $this->repository->put($this->scope, $encoded);

        DB::afterCommit(function () use ($values, $previous) {
            foreach ($values as $k => $v) {
                event(new SettingUpdated((string) $k, $v, $previous[$k], $this->scope, $this->settable));
            }
        });
    }

    /**
     * @param array<string, mixed> $previous
     */
    protected function dispatchResets(array $previous): void {
        DB::afterCommit(function () use ($previous) {
            foreach ($previous as $key => $value) {
                event(new SettingReset($key, $value, $this->scope, $this->settable));
            }
        });
    }
}
