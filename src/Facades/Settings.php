<?php

namespace Metalinked\LaravelSettingsKit\Facades;

use Illuminate\Support\Facades\Facade;
use Metalinked\LaravelSettingsKit\SettingsManager;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array many(array $keys)
 * @method static bool enabled(string $key)
 * @method static void set(string|array $key, mixed $value = null)
 * @method static bool has(string $key)
 * @method static void forget(string|array $keys)
 * @method static int reset()
 * @method static array all()
 * @method static array stored()
 * @method static array panel(?string $group = null)
 * @method static \Metalinked\LaravelSettingsKit\ScopedSettings for(\Illuminate\Database\Eloquent\Model|string $settable, int|string|null $id = null)
 * @method static \Metalinked\LaravelSettingsKit\ScopedSettings global()
 * @method static \Metalinked\LaravelSettingsKit\Definition define(string $key)
 * @method static \Metalinked\LaravelSettingsKit\Definition|null definition(string $key)
 * @method static array definitions()
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
