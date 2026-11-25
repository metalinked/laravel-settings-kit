<?php

namespace Metalinked\LaravelSettingsKit\Facades;

use Illuminate\Support\Facades\Facade;
use Metalinked\LaravelSettingsKit\SettingsManager;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array<string, mixed> many(list<string> $keys)
 * @method static bool enabled(string $key)
 * @method static void set(string|array<string, mixed> $key, mixed $value = null)
 * @method static bool has(string $key)
 * @method static void forget(string|list<string> $keys)
 * @method static void fill(mixed $values, ?array<array-key, string> $fields = null)
 * @method static array<string, list<\Metalinked\LaravelSettingsKit\Rules\SettingRule>> rules(array<array-key, string> $fields)
 * @method static void applyConfig(?list<string> $keys = null)
 * @method static int reset()
 * @method static array<string, mixed> all()
 * @method static array<string, mixed> stored()
 * @method static list<array<string, mixed>> panel(?string $group = null)
 * @method static \Metalinked\LaravelSettingsKit\ScopedSettings for(\Illuminate\Database\Eloquent\Model|string $settable, int|string|null $id = null)
 * @method static \Metalinked\LaravelSettingsKit\ScopedSettings global()
 * @method static \Metalinked\LaravelSettingsKit\Definition define(string $key)
 * @method static \Metalinked\LaravelSettingsKit\Definition|null definition(string $key)
 * @method static array<string, \Metalinked\LaravelSettingsKit\Definition> definitions()
 * @method static void flushCache()
 * @method static void routes(string $prefix = 'settings')
 *
 * @see SettingsManager
 */
class Settings extends Facade {
    /**
     * The manager is request-scoped (values are memoised per request), so the
     * facade must not hold on to an instance across requests or queued jobs.
     */
    protected static $cached = false;

    protected static function getFacadeAccessor(): string {
        return SettingsManager::class;
    }
}
