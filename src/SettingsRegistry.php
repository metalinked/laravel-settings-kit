<?php

namespace Metalinked\LaravelSettingsKit;

use Metalinked\LaravelSettingsKit\Exceptions\UndefinedSettingException;

/**
 * Holds every setting definition declared in config or with Settings::define().
 */
class SettingsRegistry {
    /** @var array<string, Definition> */
    protected array $definitions = [];

    /**
     * Register definitions in their config-file form.
     *
     * @param array<string, mixed> $settings
     */
    public function load(array $settings): static {
        foreach ($settings as $key => $config) {
            $this->add(Definition::fromConfig((string) $key, $config));
        }

        return $this;
    }

    /**
     * Start (or replace) a definition with the fluent builder.
     */
    public function define(string $key): Definition {
        return $this->add(new Definition($key));
    }

    public function add(Definition $definition): Definition {
        return $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ?Definition {
        return $this->definitions[$key] ?? null;
    }

    /**
     * The definition for a key, or an untyped one when the key was never
     * declared and strict mode is off.
     *
     * @throws UndefinedSettingException
     */
    public function resolve(string $key): Definition {
        if (isset($this->definitions[$key])) {
            return $this->definitions[$key];
        }

        if (config('settings-kit.strict', false)) {
            throw UndefinedSettingException::for($key);
        }

        return Definition::undefined($key);
    }

    /**
     * @return array<string, Definition>
     */
    public function all(): array {
        return $this->definitions;
    }

    public function forget(string $key): void {
        unset($this->definitions[$key]);
    }
}
