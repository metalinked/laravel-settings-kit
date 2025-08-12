<?php

namespace Metalinked\LaravelSettingsKit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, int $userId = null)
 * @method static void set(string $key, mixed $value, int $userId = null, bool $autoCreate = false)
 * @method static void setWithAutoCreate(string $key, mixed $value, int $userId = null)
 * @method static bool isEnabled(string $key, int $userId = null)
 * @method static string label(string $key, string $locale = null)
 * @method static string description(string $key, string $locale = null)
 * @method static array all(string $role = null, int $userId = null)
 * @method static void forget(string $key, int $userId = null)
 * @method static array getCategories()
 * @method static array getByCategory(string $category, int $userId = null)
 * @method static bool exists(string $key)
 * @method static bool has(string $key)
 * @method static \Metalinked\LaravelSettingsKit\Models\Preference create(array $data)
 * @method static \Metalinked\LaravelSettingsKit\Models\Preference|null createIfNotExists(string $key, array $data)
 * @method static \Metalinked\LaravelSettingsKit\Models\Preference|null createWithTranslations(string $key, array $preferenceData, array $translations = [])
 * @method static void addTranslations(string $key, array $translations)
 * @method static array allWithTranslations(string $locale = null, string $role = null, int $userId = null)
 * @method static void clearAllCache()
 *
 * @see \Metalinked\LaravelSettingsKit\Services\SettingsService
 */
class Settings extends Facade {
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string {
        return 'settings-kit';
    }
}
