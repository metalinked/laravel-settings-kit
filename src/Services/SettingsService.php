<?php

namespace Metalinked\LaravelSettingsKit\Services;

use Illuminate\Support\Facades\Cache;
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
        $cacheKey = $this->getCacheKey($key, $userId);

        if ($this->cacheEnabled) {
            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($key, $userId) {
                return $this->getFromDatabase($key, $userId);
            });
        }

        return $this->getFromDatabase($key, $userId);
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value, int $userId = null, bool $autoCreate = false): void {
        $preference = $this->findPreference($key);

        if (!$preference) {
            if ($autoCreate) {
                // Auto-create a basic preference based on value type
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

        if ($userId === null) {
            // Global setting change - always modify default_value directly
            // For user customizable settings: users without custom values will see the new default
            // For global unique settings: all users see the change immediately
            $preference->update(['default_value' => $value]);
        } else {
            // User-specific value - only allowed for user customizable settings
            if (!$preference->is_user_customizable) {
                throw new \InvalidArgumentException("Cannot set user-specific value for global unique setting '{$key}'");
            }
            $preference->setUserValue($userId, $value);
        }

        // Clear cache
        $this->clearCache($key, $userId);
    }

    /**
     * Set a setting value, creating the preference if it doesn't exist.
     */
    public function setWithAutoCreate(string $key, mixed $value, int $userId = null): void {
        $this->set($key, $value, $userId, true);
    }

    /**
     * Check if a boolean setting is enabled.
     */
    public function isEnabled(string $key, int $userId = null): bool {
        $value = $this->get($key, $userId);

        return (bool) $value;
    }

    /**
     * Get the translated label for a setting.
     */
    public function label(string $key, string $locale = null): string {
        $preference = $this->findPreference($key);

        if (!$preference) {
            return $key;
        }

        return $preference->getLabel($locale);
    }

    /**
     * Get the translated description for a setting.
     */
    public function description(string $key, string $locale = null): string {
        $preference = $this->findPreference($key);

        if (!$preference) {
            return '';
        }

        return $preference->getDescription($locale);
    }

    /**
     * Get all settings for a role with optional user values.
     */
    public function all(string $role = null, int $userId = null): array {
        $query = Preference::query();

        if ($role !== null) {
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)->orWhereNull('role');
            });
        } else {
            $query->whereNull('role');
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
     * Remove a setting value (reset to default).
     */
    public function forget(string $key, int $userId = null): void {
        if ($userId === null) {
            // For global settings, we need to restore to the original default
            // We don't modify the preference's default_value, we just remove any global override
            // This means we need to delete any global user_preferences with user_id = null
            UserPreference::whereHas('preference', function ($query) use ($key) {
                $query->where('key', $key);
            })->whereNull('user_id')->delete();
        } else {
            // Remove user-specific value
            UserPreference::whereHas('preference', function ($query) use ($key) {
                $query->where('key', $key);
            })->where('user_id', $userId)->delete();
        }

        // Clear cache
        $this->clearCache($key, $userId);
    }

    /**
     * Get all available categories.
     */
    public function getCategories(): array {
        return Preference::distinct('category')
            ->whereNotNull('category')
            ->pluck('category')
            ->toArray();
    }

    /**
     * Get preferences by category.
     */
    public function getByCategory(string $category, int $userId = null): array {
        $preferences = Preference::forCategory($category)
            ->with(['contents', 'userPreferences' => function ($q) use ($userId) {
                if ($userId) {
                    $q->where('user_id', $userId);
                }
            }])
            ->get();

        $result = [];

        foreach ($preferences as $preference) {
            /** @var \Metalinked\LaravelSettingsKit\Models\Preference $preference */
            $value = $userId ? $preference->getUserValue($userId) : $preference->getDefaultValue();

            $result[$preference->key] = [
                'value' => $value,
                'type' => $preference->type,
                'required' => $preference->required,
                'options' => $preference->options,
                'label' => $preference->getLabel(),
                'description' => $preference->getDescription(),
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
     * Alias for exists() method for better readability.
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
     * Create a preference with translations if it doesn't exist.
     */
    public function createWithTranslations(string $key, array $preferenceData, array $translations = []): ?Preference {
        if ($this->exists($key)) {
            return null;
        }

        $preference = $this->create(array_merge(['key' => $key], $preferenceData));

        // Add translations if provided
        foreach ($translations as $locale => $content) {
            if (is_array($content) && isset($content['title'])) {
                \Metalinked\LaravelSettingsKit\Models\PreferenceContent::create([
                    'preference_id' => $preference->id,
                    'lang' => $locale,
                    'title' => $content['title'],
                    'text' => $content['description'] ?? $content['text'] ?? '',
                ]);
            }
        }

        return $preference;
    }

    /**
     * Add or update translations for a preference.
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
                        'lang' => $locale,
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
     * Get all settings with their labels and descriptions for a specific locale.
     */
    public function allWithTranslations(string $locale = null, string $role = null, int $userId = null): array {
        $query = Preference::query();

        if ($role !== null) {
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)->orWhereNull('role');
            });
        } else {
            $query->whereNull('role');
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

        // For global values, first check if there's a global override (user_id = null)
        $globalOverride = $preference->getUserValue(null);
        if ($globalOverride !== null) {
            return $globalOverride;
        }

        // Otherwise return the original default value
        return $preference->getDefaultValue();
    }

    /**
     * Find a preference by key.
     */
    protected function findPreference(string $key): ?Preference {
        return Preference::where('key', $key)->first();
    }

    /**
     * Generate cache key.
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

        // Clear specific cache
        Cache::forget($this->getCacheKey($key, $userId));

        // If setting global value, also clear user caches
        if ($userId === null) {
            $userIds = UserPreference::whereHas('preference', function ($query) use ($key) {
                $query->where('key', $key);
            })->pluck('user_id');

            foreach ($userIds as $uid) {
                Cache::forget($this->getCacheKey($key, $uid));
            }
        }
    }

    /**
     * Clear all cache.
     */
    public function clearAllCache(): void {
        if ($this->cacheEnabled) {
            Cache::tags([$this->cachePrefix])->flush();
        }
    }

    /**
     * Prepare a value for storage.
     */
    protected function prepareValue(Preference $preference, mixed $value): string {
        return match ($preference->type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
