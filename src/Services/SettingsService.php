<?php

namespace Metalinked\LaravelSettingsKit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\UserPreference;

class SettingsService {
    protected bool $cacheEnabled;
    protected int $cacheTtl;
    protected string $cachePrefix;

    public function __construct() {
        $this->cacheEnabled = config('settings-kit.cache.enabled', true);
        $this->cacheTtl = config('settings-kit.cache.ttl', 3600);
        $this->cachePrefix = config('settings-kit.cache.prefix', 'settings_kit');
    }

    /**
     * Get a setting value.
     */
    public function get(string $key, int $userId = null): mixed {
        if (!$this->cacheEnabled) {
            return $this->getFromDatabase($key, $userId);
        }

        if ($userId === null) {
            return Cache::remember(
                $this->getCacheKey($key, null),
                $this->cacheTtl,
                fn () => $this->getFromDatabase($key, null)
            );
        }

        // Only cache when the user has an actual override in user_preferences.
        // Users without overrides fall through to the global cache so that a global
        // default change is immediately visible to everyone who hasn't customised it.
        $userCacheKey = $this->getCacheKey($key, $userId);

        if (Cache::has($userCacheKey)) {
            return Cache::get($userCacheKey);
        }

        $preference = $this->findPreference($key);

        if (!$preference) {
            return null;
        }

        $userPref = $preference->userPreferences()->where('user_id', $userId)->first();

        if ($userPref !== null) {
            $value = $preference->castValue($userPref->value);
            Cache::put($userCacheKey, $value, $this->cacheTtl);

            return $value;
        }

        return $this->get($key, null);
    }

    /**
     * Set multiple settings at once. All writes succeed or none do (database transaction).
     *
     * @param array<string, mixed> $keyValues
     */
    public function setMultiple(array $keyValues, int $userId = null): void {
        DB::transaction(function () use ($keyValues, $userId) {
            foreach ($keyValues as $key => $value) {
                $this->set($key, $value, $userId);
            }
        });
    }

    /**
     * Get multiple setting values in a single query.
     *
     * @param  string[]  $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys, int $userId = null): array {
        $preferences = Preference::whereIn('key', $keys)
            ->with(['userPreferences' => function ($q) use ($userId) {
                if ($userId) {
                    $q->where('user_id', $userId);
                }
            }])
            ->get()
            ->keyBy('key');

        $result = [];

        foreach ($keys as $key) {
            if ($preferences->has($key)) {
                $preference = $preferences->get($key);
                $result[$key] = $userId
                    ? $preference->getUserValue($userId)
                    : $preference->getDefaultValue();
            } else {
                $result[$key] = null;
            }
        }

        return $result;
    }

    /**
     * Get a setting value, or auto-create it with the given default if it does not exist.
     */
    public function remember(string $key, mixed $default, int $userId = null): mixed {
        $value = $this->get($key, $userId);

        if ($value === null && !$this->exists($key)) {
            $this->setWithAutoCreate($key, $default, $userId);

            return $default;
        }

        return $value;
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value, int $userId = null, bool $autoCreate = false): void {
        if ($userId !== null && $value === null) {
            throw new \InvalidArgumentException("Cannot store null as a user override for '{$key}'. Use Settings::forget() to revert to the global default.");
        }

        $preference = $this->findPreference($key);

        if (!$preference) {
            if ($autoCreate) {
                $type = match (true) {
                    is_bool($value) => 'boolean',
                    is_int($value) => 'integer',
                    is_array($value) => 'json',
                    default => 'string'
                };

                $preference = $this->create([
                    'key' => $key,
                    'type' => $type,
                    'default_value' => match ($type) {
                        'boolean' => $value ? '1' : '0',
                        'integer' => (string) $value,
                        'json' => json_encode($value),
                        default => (string) $value,
                    },
                    'category' => 'general',
                    'role' => null,
                    'is_user_customizable' => $userId !== null,
                ]);
            } else {
                throw new \InvalidArgumentException("Preference with key '{$key}' not found. Use Settings::createIfNotExists() or pass autoCreate=true to create it automatically.");
            }
        }

        if ($preference->type === 'select' && !empty($preference->options)) {
            $stringValue = (string) $value;

            if (!in_array($stringValue, array_map('strval', $preference->options), strict: true)) {
                $allowed = implode(', ', $preference->options);

                throw new \InvalidArgumentException("Invalid value for select setting '{$key}'. Allowed: {$allowed}.");
            }
        }

        if ($userId === null) {
            $preference->update(['default_value' => $preference->prepareValue($value)]);
        } else {
            if (!$preference->is_user_customizable) {
                throw new \InvalidArgumentException("Cannot set user-specific value for global unique setting '{$key}'");
            }

            $preference->setUserValue($userId, $value);
        }

        $this->clearCache($key, $userId);
        event(new SettingUpdated($key, $value, $userId));
    }

    /**
     * Set a setting value, creating the preference automatically if it doesn't exist.
     */
    public function setWithAutoCreate(string $key, mixed $value, int $userId = null): void {
        $this->set($key, $value, $userId, true);
    }

    /**
     * Check if a boolean setting is enabled.
     */
    public function isEnabled(string $key, int $userId = null): bool {
        return (bool) $this->get($key, $userId);
    }

    /**
     * Get the translated label for a setting.
     */
    public function label(string $key, string $locale = null): string {
        $preference = $this->findPreference($key);

        return $preference ? $preference->getLabel($locale) : $key;
    }

    /**
     * Get the translated description for a setting.
     */
    public function description(string $key, string $locale = null): string {
        $preference = $this->findPreference($key);

        return $preference ? $preference->getDescription($locale) : '';
    }

    /**
     * Get all settings, optionally filtered by role, userId, and category.
     *
     * @return array<string, mixed>
     */
    public function all(string $role = null, int $userId = null, string $category = null): array {
        $query = Preference::query();

        if ($role !== null) {
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)->orWhereNull('role');
            });
        } else {
            $query->whereNull('role');
        }

        if ($category !== null) {
            $query->forCategory($category);
        }

        $preferences = $query->with(['contents', 'userPreferences' => function ($q) use ($userId) {
            if ($userId) {
                $q->where('user_id', $userId);
            }
        }])->get();

        $result = [];

        foreach ($preferences as $preference) {
            $value = $userId ? $preference->getUserValue($userId) : $preference->getDefaultValue();

            $result[$preference->key] = [
                'value' => $value,
                'type' => $preference->type,
                'category' => $preference->category,
                'required' => $preference->required,
                'options' => $preference->options,
                'label' => $preference->getLabel(),
                'description' => $preference->getDescription(),
            ];
        }

        return $result;
    }

    /**
     * Reset a user-specific setting back to the global default.
     * Has no effect when called without a userId (global settings are changed via set()).
     */
    public function forget(string $key, int $userId = null): void {
        if ($userId === null) {
            return;
        }

        UserPreference::whereHas('preference', function ($query) use ($key) {
            $query->where('key', $key);
        })->where('user_id', $userId)->delete();

        $this->clearCache($key, $userId);
        event(new SettingUpdated($key, null, $userId));
    }

    /**
     * Remove all user-specific overrides for a given user, reverting everything to global defaults.
     * Returns the number of overrides removed.
     */
    public function forgetAll(int $userId): int {
        $userPreferences = UserPreference::where('user_id', $userId)->with('preference')->get();

        foreach ($userPreferences as $userPref) {
            if ($userPref->preference) {
                $this->clearCache($userPref->preference->key, $userId);
                event(new SettingUpdated($userPref->preference->key, null, $userId));
            }
        }

        UserPreference::where('user_id', $userId)->delete();

        return $userPreferences->count();
    }

    /**
     * Permanently delete a preference and all its user customizations and translations.
     */
    public function delete(string $key): bool {
        $preference = $this->findPreference($key);

        if (!$preference) {
            return false;
        }

        $preference->userPreferences()->delete();
        $preference->contents()->delete();
        $preference->delete();
        $this->clearCache($key, null);

        return true;
    }

    /**
     * Count the number of registered settings.
     */
    public function count(string $category = null, string $role = null): int {
        $query = Preference::query();

        if ($category !== null) {
            $query->forCategory($category);
        }

        if ($role !== null) {
            $query->forRole($role);
        }

        return $query->count();
    }

    /**
     * Get all available categories.
     *
     * @return string[]
     */
    public function getCategories(): array {
        return Preference::distinct('category')
            ->whereNotNull('category')
            ->pluck('category')
            ->toArray();
    }

    /**
     * Get all user-customisable settings with resolved values for a specific user.
     * Each item includes an `is_overridden` flag indicating whether the user has a
     * personal override or is inheriting the global default.
     *
     * @return array<string, mixed>
     */
    public function allForUser(int $userId, string $locale = null, string $category = null): array {
        $query = Preference::where('is_user_customizable', true);

        if ($category !== null) {
            $query->forCategory($category);
        }

        $preferences = $query->with([
            'contents',
            'userPreferences' => fn ($q) => $q->where('user_id', $userId),
        ])->get();

        $result = [];

        foreach ($preferences as $preference) {
            /** @var Preference $preference */
            $userPref = $preference->userPreferences->first();
            $isOverridden = $userPref !== null;
            $value = $isOverridden
                ? $preference->castValue($userPref->value)
                : $preference->getDefaultValue();

            $item = [
                'value' => $value,
                'is_overridden' => $isOverridden,
                'type' => $preference->type,
                'category' => $preference->category,
                'options' => $preference->options,
                'key' => $preference->key,
            ];

            if ($locale !== null) {
                $content = $preference->getTranslatedContent($locale);
                $item['label'] = $content ? $content->title : $preference->key;
                $item['description'] = $content ? $content->text : '';
            }

            $result[$preference->key] = $item;
        }

        return $result;
    }

    /**
     * Get only the settings a user has explicitly overridden (differs from global default).
     *
     * @return array<string, mixed>
     */
    public function getUserOverrides(int $userId): array {
        $userPreferences = UserPreference::where('user_id', $userId)
            ->with('preference')
            ->get();

        $result = [];

        foreach ($userPreferences as $userPref) {
            if ($userPref->preference) {
                $result[$userPref->preference->key] = $userPref->preference->castValue($userPref->value);
            }
        }

        return $result;
    }

    /**
     * Get preferences by category, optionally filtered by role.
     *
     * @return array<string, mixed>
     */
    public function getByCategory(string $category, int $userId = null, string $role = null): array {
        $query = Preference::forCategory($category);

        if ($role !== null) {
            $query->forRole($role);
        }

        $preferences = $query->with(['contents', 'userPreferences' => function ($q) use ($userId) {
            if ($userId) {
                $q->where('user_id', $userId);
            }
        }])->get();

        $result = [];

        foreach ($preferences as $preference) {
            /** @var Preference $preference */
            $value = $userId ? $preference->getUserValue($userId) : $preference->getDefaultValue();

            $result[$preference->key] = [
                'value' => $value,
                'type' => $preference->type,
                'category' => $preference->category,
                'required' => $preference->required,
                'options' => $preference->options,
                'label' => $preference->getLabel(),
                'description' => $preference->getDescription(),
                'key' => $preference->key,
            ];
        }

        return $result;
    }

    /**
     * Check if a preference exists.
     */
    public function exists(string $key): bool {
        return Preference::where('key', $key)->exists();
    }

    /**
     * Alias for exists().
     */
    public function has(string $key): bool {
        return $this->exists($key);
    }

    /**
     * Create a new preference.
     */
    public function create(array $data): Preference {
        return Preference::create($data);
    }

    /**
     * Create a preference only if it doesn't exist.
     */
    public function createIfNotExists(string $key, array $data): ?Preference {
        if ($this->exists($key)) {
            return null;
        }

        return $this->create(array_merge(['key' => $key], $data));
    }

    /**
     * Create a preference with translations. Returns null if the key already exists.
     */
    public function createWithTranslations(string $key, array $preferenceData, array $translations = []): ?Preference {
        if ($this->exists($key)) {
            return null;
        }

        $preference = $this->create(array_merge(['key' => $key], $preferenceData));

        foreach ($translations as $locale => $content) {
            if (is_array($content) && isset($content['title'])) {
                \Metalinked\LaravelSettingsKit\Models\PreferenceContent::create([
                    'preference_id' => $preference->id,
                    'locale' => $locale,
                    'title' => $content['title'],
                    'text' => $content['description'] ?? $content['text'] ?? '',
                ]);
            }
        }

        return $preference;
    }

    /**
     * Add or update translations for an existing preference.
     */
    public function addTranslations(string $key, array $translations): void {
        $preference = $this->findPreference($key);

        if (!$preference) {
            throw new \InvalidArgumentException("Preference with key '{$key}' not found.");
        }

        foreach ($translations as $locale => $content) {
            if (is_array($content) && isset($content['title'])) {
                \Metalinked\LaravelSettingsKit\Models\PreferenceContent::updateOrCreate(
                    [
                        'preference_id' => $preference->id,
                        'locale' => $locale,
                    ],
                    [
                        'title' => $content['title'],
                        'text' => $content['description'] ?? $content['text'] ?? '',
                    ]
                );
            }
        }
    }

    /**
     * Get all settings with translated labels and descriptions for a specific locale.
     *
     * @return array<string, mixed>
     */
    public function allWithTranslations(string $locale = null, string $role = null, int $userId = null, string $category = null): array {
        $query = Preference::query();

        if ($role !== null) {
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)->orWhereNull('role');
            });
        } else {
            $query->whereNull('role');
        }

        if ($category !== null) {
            $query->forCategory($category);
        }

        $preferences = $query->with(['contents', 'userPreferences' => function ($q) use ($userId) {
            if ($userId) {
                $q->where('user_id', $userId);
            }
        }])->get();

        $result = [];

        foreach ($preferences as $preference) {
            $value = $userId ? $preference->getUserValue($userId) : $preference->getDefaultValue();

            $result[$preference->key] = [
                'value' => $value,
                'type' => $preference->type,
                'category' => $preference->category,
                'required' => $preference->required,
                'options' => $preference->options,
                'label' => $preference->getLabel($locale),
                'description' => $preference->getDescription($locale),
                'key' => $preference->key,
            ];
        }

        return $result;
    }

    /**
     * Get setting value from database.
     */
    protected function getFromDatabase(string $key, int $userId = null): mixed {
        $preference = $this->findPreference($key);

        if (!$preference) {
            return null;
        }

        if ($userId !== null) {
            return $preference->getUserValue($userId);
        }

        return $preference->getDefaultValue();
    }

    /**
     * Find a preference by key.
     */
    protected function findPreference(string $key): ?Preference {
        return Preference::where('key', $key)->first();
    }

    /**
     * Generate a cache key for a setting.
     */
    protected function getCacheKey(string $key, int $userId = null): string {
        $suffix = $userId ? "user_{$userId}" : 'global';

        return "{$this->cachePrefix}:{$key}:{$suffix}";
    }

    /**
     * Clear cache for a specific setting.
     */
    protected function clearCache(string $key, int $userId = null): void {
        if (!$this->cacheEnabled) {
            return;
        }

        if ($userId !== null) {
            Cache::forget($this->getCacheKey($key, $userId));

            return;
        }

        // Global change: clearing the global key is sufficient.
        // Users without overrides have no user-cache entry and read from the global key.
        // Users with overrides hold their own user-cache entries, which are unaffected.
        Cache::forget($this->getCacheKey($key, null));
    }

    /**
     * Clear all cached settings.
     * Works with any cache driver — iterates all known keys instead of using tags.
     */
    public function clearAllCache(): void {
        if (!$this->cacheEnabled) {
            return;
        }

        foreach (Preference::pluck('key') as $key) {
            Cache::forget($this->getCacheKey($key, null));
        }

        foreach (UserPreference::with('preference')->get() as $userPref) {
            if ($userPref->preference) {
                Cache::forget($this->getCacheKey($userPref->preference->key, $userPref->user_id));
            }
        }
    }

    /**
     * Prepare a value for storage (same logic as Preference::prepareValue).
     * Kept here for convenience when the preference model is not available.
     */
    protected function prepareValue(Preference $preference, mixed $value): string {
        return $preference->prepareValue($value);
    }
}
