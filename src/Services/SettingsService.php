<?php

namespace Metalinked\LaravelSettingsKit\Services;

use Illuminate\Support\Facades\Cache;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\UserPreference;

class SettingsService
{
    protected bool $cacheEnabled;
    protected int $cacheTtl;
    protected string $cachePrefix;

    public function __construct()
    {
        $this->cacheEnabled = config('settings-kit.cache.enabled', true);
        $this->cacheTtl = config('settings-kit.cache.ttl', 3600);
        $this->cachePrefix = config('settings-kit.cache.prefix', 'settings_kit');
    }

    /**
     * Get a setting value.
     */
    public function get(string $key, int $userId = null): mixed
    {
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
    public function set(string $key, mixed $value, int $userId = null): void
    {
        $preference = $this->findPreference($key);

        if (!$preference) {
            throw new \InvalidArgumentException("Preference with key '{$key}' not found.");
        }

        if ($userId === null) {
            // Set global default value
            $preparedValue = match ($preference->type) {
                'boolean' => $value ? '1' : '0',
                'integer' => (string) $value,
                'json' => json_encode($value),
                default => (string) $value,
            };
            $preference->update(['default_value' => $preparedValue]);
        } else {
            // Set user-specific value
            $preference->setUserValue($userId, $value);
        }

        // Clear cache
        $this->clearCache($key, $userId);
    }

    /**
     * Check if a boolean setting is enabled.
     */
    public function isEnabled(string $key, int $userId = null): bool
    {
        $value = $this->get($key, $userId);
        return (bool) $value;
    }

    /**
     * Get the translated label for a setting.
     */
    public function label(string $key, string $locale = null): string
    {
        $preference = $this->findPreference($key);
        
        if (!$preference) {
            return $key;
        }

        return $preference->getLabel($locale);
    }

    /**
     * Get the translated description for a setting.
     */
    public function description(string $key, string $locale = null): string
    {
        $preference = $this->findPreference($key);
        
        if (!$preference) {
            return '';
        }

        return $preference->getDescription($locale);
    }

    /**
     * Get all settings for a role with optional user values.
     */
    public function all(string $role = null, int $userId = null): array
    {
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
    public function forget(string $key, int $userId = null): void
    {
        if ($userId === null) {
            // Reset global default to null or remove preference entirely
            $preference = $this->findPreference($key);
            if ($preference) {
                $preference->update(['default_value' => null]);
            }
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
    public function getCategories(): array
    {
        return Preference::distinct('category')
            ->whereNotNull('category')
            ->pluck('category')
            ->toArray();
    }

    /**
     * Get preferences by category.
     */
    public function getByCategory(string $category, int $userId = null): array
    {
        $preferences = Preference::forCategory($category)
            ->with(['contents', 'userPreferences' => function ($q) use ($userId) {
                if ($userId) {
                    $q->where('user_id', $userId);
                }
            }])
            ->get();

        $result = [];

        foreach ($preferences as $preference) {
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
    public function exists(string $key): bool
    {
        return Preference::where('key', $key)->exists();
    }

    /**
     * Create a new preference.
     */
    public function create(array $data): Preference
    {
        return Preference::create($data);
    }

    /**
     * Get setting value from database.
     */
    protected function getFromDatabase(string $key, int $userId = null): mixed
    {
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
    protected function findPreference(string $key): ?Preference
    {
        return Preference::where('key', $key)->first();
    }

    /**
     * Generate cache key.
     */
    protected function getCacheKey(string $key, int $userId = null): string
    {
        $suffix = $userId ? "user_{$userId}" : 'global';
        return "{$this->cachePrefix}:{$key}:{$suffix}";
    }

    /**
     * Clear cache for a specific setting.
     */
    protected function clearCache(string $key, int $userId = null): void
    {
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
    public function clearAllCache(): void
    {
        if ($this->cacheEnabled) {
            Cache::tags([$this->cachePrefix])->flush();
        }
    }

    /**
     * Prepare a value for storage.
     */
    protected function prepareValue(Preference $preference, mixed $value): string
    {
        return match ($preference->type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
