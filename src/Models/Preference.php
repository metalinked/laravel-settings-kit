<?php

namespace Metalinked\LaravelSettingsKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

/**
 * @method static Builder forRole(?string $role)
 * @method static Builder forCategory(string $category)
 * @method mixed getDefaultValue()
 * @method mixed getUserValue(int $userId)
 * @method string|null getLabel(string $lang = null)
 * @method string|null getDescription(string $lang = null)
 */
class Preference extends Model {
    protected $fillable = [
        'role',
        'category',
        'type',
        'required',
        'key',
        'default_value',
        'options',
        'is_user_customizable',
    ];

    protected $casts = [
        'required' => 'boolean',
        'is_user_customizable' => 'boolean',
        'options' => 'array',
    ];

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
        $this->table = config('settings-kit.tables.preferences', 'preferences');
    }

    /**
     * Get the preference contents (translations).
     */
    public function contents(): HasMany {
        return $this->hasMany(PreferenceContent::class);
    }

    /**
     * Get the user preferences.
     */
    public function userPreferences(): HasMany {
        return $this->hasMany(UserPreference::class);
    }

    /**
     * Get the translated content for the current or specified locale.
     */
    public function getTranslatedContent(string $locale = null): ?PreferenceContent {
        $locale ??= App::getLocale();

        $content = $this->contents()->where('lang', $locale)->first();

        if (!$content) {
            $fallbackLocale = config('settings-kit.fallback_locale', 'en');
            $content = $this->contents()->where('lang', $fallbackLocale)->first();
        }

        return $content instanceof PreferenceContent ? $content : null;
    }

    /**
     * Get the label for the current or specified locale.
     */
    public function getLabel(string $locale = null): string {
        $content = $this->getTranslatedContent($locale);

        return $content ? $content->title : $this->key;
    }

    /**
     * Get the description for the current or specified locale.
     */
    public function getDescription(string $locale = null): string {
        $content = $this->getTranslatedContent($locale);

        return $content ? $content->text : '';
    }

    /**
     * Get the user-specific value for this preference.
     */
    public function getUserValue(?int $userId): mixed {
        $userPreference = $this->userPreferences()->where('user_id', $userId)->first();

        if ($userPreference) {
            return $this->castValue($userPreference->value);
        }

        // Only return default if this is not a global override lookup (userId === null)
        if ($userId !== null) {
            return $this->getDefaultValue();
        }

        return null; // No global override found
    }

    /**
     * Set the user-specific value for this preference.
     */
    public function setUserValue(?int $userId, mixed $value): UserPreference {
        /** @var UserPreference $userPreference */
        $userPreference = $this->userPreferences()->updateOrCreate(
            ['user_id' => $userId],
            ['value' => $this->prepareValue($value)]
        );

        return $userPreference;
    }

    /**
     * Get the default value with proper casting.
     */
    public function getDefaultValue(): mixed {
        return $this->castValue($this->default_value);
    }

    /**
     * Cast a value to the appropriate type.
     */
    protected function castValue(mixed $value): mixed {
        return match ($this->type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'select' => $value,
            default => (string) $value,
        };
    }

    /**
     * Prepare a value for storage.
     */
    public function prepareValue(mixed $value): string {
        return match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Scope to filter by role.
     */
    public function scopeForRole(Builder $query, string $role = null): void {
        if ($role === null) {
            $query->whereNull('role');
        } else {
            $query->where('role', $role);
        }
    }

    /**
     * Scope to filter by category.
     */
    public function scopeForCategory(Builder $query, string $category): void {
        $query->where('category', $category);
    }
}
