<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Events\SettingReset;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Exceptions\InvalidSettingValue;
use Metalinked\LaravelSettingsKit\Exceptions\SettingNotOverridableException;

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
     * @throws ValidationException
     */
    public function set(string|array $key, mixed $value = null): void {
        $values = is_array($key) ? $key : [$key => $value];
        $definitions = [];
        $normalized = [];
        $errors = [];

        foreach ($values as $k => $v) {
            $k = (string) $k;
            $definition = $this->registry->resolve($k);

            if (!$this->scope->isGlobal() && !$definition->isOverridableBy($this->scope->modelClass())) {
                throw SettingNotOverridableException::for($k, $this->scope->modelClass());
            }

            try {
                $normalized[$k] = $definition->normalize($v);
                $this->validateRules($definition, $normalized[$k]);
                $definitions[$k] = $definition;
            } catch (InvalidSettingValue $e) {
                $errors[$k] = [$e->getMessage()];
            } catch (ValidationException $e) {
                $errors[$k] = $e->validator->errors()->all();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $previous = $this->many(array_keys($normalized));

        $encoded = [];

        foreach ($normalized as $k => $v) {
            $encoded[$k] = $definitions[$k]->encode($v);
        }

        $this->repository->put($this->scope, $encoded);

        DB::afterCommit(function () use ($normalized, $previous) {
            foreach ($normalized as $k => $v) {
                event(new SettingUpdated($k, $v, $previous[$k], $this->scope, $this->settable));
            }
        });
    }

    /**
     * Whether this scope stores its own value for the key (for a model: whether
     * it overrides the global value; for global: whether it differs from the
     * code default).
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
     * Only the values stored in this scope (a user's overrides, or the global
     * values that differ from their defaults).
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

            $items[] = [
                'key' => $key,
                'group' => $definition->getGroup(),
                'type' => $definition->getType(),
                'label' => $definition->getLabel(),
                'description' => $definition->getDescription(),
                'value' => Definition::export($this->get($key)),
                // What the value falls back to when this scope stores nothing.
                'default' => Definition::export($this->scope->isGlobal()
                    ? $definition->getDefault()
                    : $this->globalValue($definition, $definition->getDefault())),
                'overridden' => $this->has($key),
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

    protected function validateRules(Definition $definition, mixed $value): void {
        if ($definition->getRules() === []) {
            return;
        }

        // Size rules (min, max, between…) need to know the type to compare
        // numbers, string lengths or array counts correctly.
        $typeRule = match ($definition->getType()) {
            'integer' => 'integer',
            'float' => 'numeric',
            'array' => 'array',
            'string' => 'string',
            default => null,
        };

        Validator::make(
            ['value' => Definition::export($value)],
            ['value' => array_merge($value === null ? ['nullable'] : [], $typeRule ? [$typeRule] : [], $definition->getRules())],
            [],
            ['value' => $definition->getLabel()]
        )->validate();
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
