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
 * @method mixed getUserValue(?int $userId)
 * @method string getLabel(string $locale = null)
 * @method string getDescription(string $locale = null)
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

    public function contents(): HasMany {
        return $this->hasMany(PreferenceContent::class);
    }

    public function userPreferences(): HasMany {
        return $this->hasMany(UserPreference::class);
    }

    /**
     * Get the translated content for a locale, falling back to the configured fallback locale.
     * Uses the already-loaded `contents` collection when available to avoid N+1 queries.
     */
    public function getTranslatedContent(string $locale = null): ?PreferenceContent {
        $locale ??= App::getLocale();
        $fallback = config('settings-kit.fallback_locale', 'en');

        if ($this->relationLoaded('contents')) {
            $content = $this->contents->firstWhere('locale', $locale)
                ?? $this->contents->firstWhere('locale', $fallback);

            return $content instanceof PreferenceContent ? $content : null;
        }

        $content = $this->contents()->where('locale', $locale)->first()
            ?? $this->contents()->where('locale', $fallback)->first();

        return $content instanceof PreferenceContent ? $content : null;
    }

    public function getLabel(string $locale = null): string {
        $content = $this->getTranslatedContent($locale);

        return $content ? $content->title : $this->key;
    }

    public function getDescription(string $locale = null): string {
        $content = $this->getTranslatedContent($locale);

        return $content ? $content->text : '';
    }

    /**
     * Get the user-specific value, falling back to the global default when no override exists.
     */
    public function getUserValue(?int $userId): mixed {
        if ($userId === null) {
            return null;
        }

        $userPreference = $this->relationLoaded('userPreferences')
            ? $this->userPreferences->firstWhere('user_id', $userId)
            : $this->userPreferences()->where('user_id', $userId)->first();

        return $userPreference
            ? $this->castValue($userPreference->value)
            : $this->getDefaultValue();
    }

    public function setUserValue(?int $userId, mixed $value): UserPreference {
        /** @var UserPreference $userPreference */
        $userPreference = $this->userPreferences()->updateOrCreate(
            ['user_id' => $userId],
            ['value' => $this->prepareValue($value)]
        );

        return $userPreference;
    }

    public function getDefaultValue(): mixed {
        return $this->castValue($this->default_value);
    }

    public function castValue(mixed $value): mixed {
        return match ($this->type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'select' => $value,
            default => (string) $value,
        };
    }

    public function prepareValue(mixed $value): string {
        return match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    public function scopeForRole(Builder $query, string $role = null): void {
        if ($role === null) {
            $query->whereNull('role');
        } else {
            $query->where('role', $role);
        }
    }

    public function scopeForCategory(Builder $query, string $category): void {
        $query->where('category', $category);
    }
}
