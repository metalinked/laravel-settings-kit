# Laravel Settings Kit

[![Tests](https://img.shields.io/github/actions/workflow/status/metalinked/laravel-settings-kit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metalinked/laravel-settings-kit/actions/workflows/tests.yml)
[![GitHub Release](https://img.shields.io/github/v/release/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![License](https://img.shields.io/packagist/l/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/blob/main/LICENSE.md)

A Laravel package for managing global and user-specific settings with multilingual support, built-in caching, and a full REST API.

## Features

- **Two-level settings.** Global defaults with per-user overrides. Users who haven't customised a setting automatically inherit the global default, with no extra queries.
- **Type casting.** Values are stored as strings and automatically cast to `boolean`, `integer`, `json`, `select`, or `string` on retrieval.
- **Built-in cache.** Every read is cached. Changing a global default is immediately visible to all users who haven't customised it.
- **Batch operations.** Update multiple settings in a single call. Retrieve all settings for a user, or only the ones they've personalised.
- **Multilingual labels.** Attach translated titles and descriptions to settings, with locale fallback.
- **Role-based visibility.** Tag settings with a role to control which users see them.
- **REST API.** Full API for SPAs, mobile apps, and headless applications, with token, Sanctum, and Passport authentication.
- **Events.** A `SettingUpdated` event is dispatched on every write and reset, for audit logs and side effects.
- **Blade directive.** `@setting('key')` renders a setting value directly in templates.
- **Artisan commands.** `settings:list`, `settings:export`, `settings:import`, `settings:clear-cache`.

## Requirements

PHP 8.1 or higher, Laravel 9 or higher.

## Installation

```bash
composer require metalinked/laravel-settings-kit

php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="migrations"
php artisan migrate
```

Publish the config file (optional):

```bash
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="config"
```

## Basic Usage

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Read a global setting
$value = Settings::get('site_name');

// Read a user-specific setting (falls back to the global default if not customised)
$theme = Settings::get('theme', $userId);

// Write a global setting (the preference must exist in the database)
Settings::set('site_name', 'My App');

// Write a user-specific setting
Settings::set('theme', 'dark', $userId);

// Write and auto-create the preference if it does not exist yet
Settings::setWithAutoCreate('new_feature_enabled', true);

// Get or auto-create with a default value
$perPage = Settings::remember('items_per_page', 20);

// Check a boolean setting
if (Settings::isEnabled('maintenance_mode')) {
    abort(503);
}

// Read multiple settings in a single query
$settings = Settings::getMultiple(['theme', 'language', 'timezone'], $userId);

// Update multiple settings in a single call
Settings::setMultiple(['theme' => 'dark', 'language' => 'ca'], $userId);

// Reset a user's customisation, reverting to the global default
Settings::forget('theme', $userId);

// Reset all of a user's customisations at once
$count = Settings::forgetAll($userId);

// Permanently delete a preference and all its user customisations
Settings::delete('old_feature_flag');
```

### Auto-creation and type detection

`setWithAutoCreate()` creates the preference automatically if it does not exist, detecting the type from the PHP value:

```php
Settings::setWithAutoCreate('maintenance_mode', false); // boolean
Settings::setWithAutoCreate('max_users', 100);          // integer
Settings::setWithAutoCreate('theme_config', ['dark' => true]); // json
Settings::setWithAutoCreate('site_name', 'My App');     // string
```

### Predefined settings (recommended)

For production use, create settings upfront via a seeder so you have full control over type, defaults, and constraints:

```php
use Metalinked\LaravelSettingsKit\Models\Preference;

Preference::firstOrCreate(['key' => 'maintenance_mode'], [
    'type' => 'boolean',
    'default_value' => '0',
    'category' => 'system',
    'is_user_customizable' => false,
]);

Preference::firstOrCreate(['key' => 'theme'], [
    'type' => 'select',
    'default_value' => 'light',
    'options' => ['light', 'dark', 'auto'],
    'category' => 'appearance',
    'is_user_customizable' => true,
]);
```

Values for `select` settings are validated on write: passing a value not in `options` throws an `InvalidArgumentException`.

## Global vs. User Settings

The package distinguishes two types of settings based on the `is_user_customizable` column.

**Global unique settings** (`is_user_customizable = false`) apply to the entire application. Calling `Settings::set('maintenance_mode', true)` updates `default_value` directly and affects everyone immediately.

**User-customisable settings** (`is_user_customizable = true`) have a global default that individual users can override. The global default lives in `preferences.default_value`. Per-user overrides are stored in `user_preferences` only when the user actually changes the value, keeping the table lean.

```php
// Change the global default (affects all users who have not customised)
Settings::set('theme', 'dark');

// User 123 sets a personal preference (stored in user_preferences)
Settings::set('theme', 'custom', 123);

Settings::get('theme');       // 'dark'  (global default)
Settings::get('theme', 123);  // 'custom' (user override)
Settings::get('theme', 456);  // 'dark'  (inherits global default)
```

### Loading all settings for a user

`allForUser()` is designed for app boot: one query fetches all customisable settings with resolved values and an `is_overridden` flag showing whether the user has personalised each one.

```php
$settings = Settings::allForUser($userId, locale: 'ca');
// [
//   'theme' => ['value' => 'dark', 'is_overridden' => true,  'type' => 'select', ...],
//   'language' => ['value' => 'en',   'is_overridden' => false, 'type' => 'string', ...],
// ]
```

To retrieve only the settings a user has explicitly overridden:

```php
$overrides = Settings::getUserOverrides($userId);
// ['theme' => 'dark']  (only the keys with a personal override)
```

## Multilingual Labels

```php
Settings::createWithTranslations('notification_email', [
    'type' => 'boolean',
    'default_value' => '1',
    'category' => 'notifications',
], [
    'en' => ['title' => 'Email Notifications', 'text' => 'Receive notifications via email'],
    'es' => ['title' => 'Notificaciones por Email', 'text' => 'Recibir notificaciones por correo'],
    'ca' => ['title' => 'Notificacions per Email', 'text' => 'Rebre notificacions per correu'],
]);

// Retrieve translated labels (falls back to the configured fallback_locale if missing)
Settings::label('notification_email', 'ca');       // 'Notificacions per Email'
Settings::description('notification_email', 'fr'); // fallback to 'en'

// Add translations to an existing setting
Settings::addTranslations('theme', [
    'en' => ['title' => 'Theme', 'text' => 'Choose your preferred theme'],
    'es' => ['title' => 'Tema', 'text' => 'Elige tu tema preferido'],
]);

// Get all settings with translated labels for a locale
$settings = Settings::allWithTranslations('ca');
```

## Events

A `SettingUpdated` event is dispatched after every `set()` and `forget()` call:

```php
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;

Event::listen(SettingUpdated::class, function (SettingUpdated $event) {
    // $event->key, $event->value (null on forget/reset), $event->userId
    Log::info("Setting '{$event->key}' changed", ['value' => $event->value, 'user' => $event->userId]);
});
```

## Blade Directive

```blade
<title>@setting('site_name')</title>
<body class="theme-@setting('theme', auth()->id())">
```

## Artisan Commands

```bash
# List all settings
php artisan settings:list
php artisan settings:list --category=system
php artisan settings:list --type=boolean

# Export to JSON
php artisan settings:export
php artisan settings:export --file=settings.json --category=system

# Import from JSON
php artisan settings:import settings.json
php artisan settings:import settings.json --force      # overwrite existing
php artisan settings:import settings.json --dry-run    # preview only

# Clear the settings cache (works with any cache driver)
php artisan settings:clear-cache
```

## REST API

Enable the API in your `.env`:

```env
SETTINGS_KIT_API_ENABLED=true
SETTINGS_KIT_API_AUTH=token
SETTINGS_KIT_API_TOKEN=your-secure-token
```

For local development, you can bypass authentication entirely:

```env
SETTINGS_KIT_API_ENABLED=true
SETTINGS_KIT_API_DISABLE_AUTH_DEV=true
```

### Endpoints

```
GET    /api/settings-kit                    List all settings
GET    /api/settings-kit/categories         List categories
POST   /api/settings-kit/preferences        Create a preference

GET    /api/settings-kit/global/{key}       Get a global setting value
POST   /api/settings-kit/global/{key}       Update a global setting value
PUT    /api/settings-kit/global/{key}       Update a global setting value
DELETE /api/settings-kit/global/{key}       Permanently delete a preference

GET    /api/settings-kit/user?user_id=123         All customisable settings for a user (resolved values)
POST   /api/settings-kit/user/batch               Update multiple user settings in one request
DELETE /api/settings-kit/user?user_id=123         Reset all overrides for a user to global defaults

GET    /api/settings-kit/user/{key}?user_id=123   Get a user setting
POST   /api/settings-kit/user/{key}?user_id=123   Update a user setting
PUT    /api/settings-kit/user/{key}?user_id=123   Update a user setting
DELETE /api/settings-kit/user/{key}?user_id=123   Reset a user setting to the global default
```

Query parameters: `locale` (for translated labels), `role`, `category`, `user_id`.

### Authentication

| Mode | Config | Use case |
|------|--------|----------|
| Static token | `SETTINGS_KIT_API_AUTH=token`, `SETTINGS_KIT_API_TOKEN=your-token` | Server-to-server integrations (full access) |
| Laravel Sanctum | `SETTINGS_KIT_API_AUTH=sanctum` | User-facing SPAs |
| Laravel Passport | `SETTINGS_KIT_API_AUTH=passport` | OAuth applications |
| Dev bypass | `SETTINGS_KIT_API_DISABLE_AUTH_DEV=true` | Local development only |

> **Note:** Token mode grants full access to all users' settings. Use Sanctum or Passport for user-facing APIs where each user should only access their own settings.

### Batch update example

```json
POST /api/settings-kit/user/batch
{
    "user_id": 123,
    "settings": {
        "theme": "dark",
        "language": "ca",
        "notifications": true
    }
}
```

### Response format

All responses include a `success` boolean:

```json
{
    "success": true,
    "data": {
        "theme": {
            "value": "dark",
            "is_overridden": true,
            "type": "select",
            "category": "appearance",
            "options": ["light", "dark", "auto"],
            "key": "theme"
        }
    },
    "meta": { "count": 1, "user_id": 123 }
}
```

Error responses:

```json
{ "success": false, "error": "Setting not found" }
```

## Configuration

```env
# Cache (works with any driver: file, database, redis, etc.)
SETTINGS_KIT_CACHE_ENABLED=true
SETTINGS_KIT_CACHE_TTL=3600

# Locale fallback when a translation is missing
SETTINGS_KIT_FALLBACK_LOCALE=en

# API
SETTINGS_KIT_API_ENABLED=false
SETTINGS_KIT_API_PREFIX=api/settings-kit
SETTINGS_KIT_API_AUTH=token
SETTINGS_KIT_API_TOKEN=
SETTINGS_KIT_API_DISABLE_AUTH_DEV=false
SETTINGS_KIT_API_AUTO_CREATE=false
```

Full config reference after publishing (`config/settings-kit.php`):

```php
return [
    'cache' => [
        'enabled' => env('SETTINGS_KIT_CACHE_ENABLED', true),
        'ttl'     => env('SETTINGS_KIT_CACHE_TTL', 3600),
        'prefix'  => env('SETTINGS_KIT_CACHE_PREFIX', 'settings_kit'),
    ],
    'tables' => [
        // Warning: 'preferences' is a common table name. Rename if your app
        // already has a table with this name before running migrations.
        'preferences'         => 'preferences',
        'preference_contents' => 'preference_contents',
        'user_preferences'    => 'user_preferences',
    ],
    'fallback_locale' => env('SETTINGS_KIT_FALLBACK_LOCALE', 'en'),
    'user_model'      => 'App\Models\User',
    'api' => [
        'enabled'                    => env('SETTINGS_KIT_API_ENABLED', false),
        'prefix'                     => env('SETTINGS_KIT_API_PREFIX', 'api/settings-kit'),
        'auth_mode'                  => env('SETTINGS_KIT_API_AUTH', 'token'),
        'token'                      => env('SETTINGS_KIT_API_TOKEN'),
        'disable_auth_in_development' => env('SETTINGS_KIT_API_DISABLE_AUTH_DEV', false),
        'auto_create_missing_settings' => env('SETTINGS_KIT_API_AUTO_CREATE', false),
    ],
];
```

## Facade Reference

| Method | Description |
|--------|-------------|
| `get(key, userId?)` | Get a setting value |
| `getMultiple(keys[], userId?)` | Get multiple settings in one query |
| `remember(key, default, userId?)` | Get a value, auto-creating it with the default if missing |
| `set(key, value, userId?, autoCreate?)` | Set a value |
| `setMultiple(keyValues[], userId?)` | Set multiple values in one call |
| `setWithAutoCreate(key, value, userId?)` | Set a value, creating the preference if needed |
| `isEnabled(key, userId?)` | Boolean check |
| `forget(key, userId)` | Remove a user's override, reverting to the global default |
| `forgetAll(userId)` | Remove all overrides for a user, returns count |
| `delete(key)` | Permanently delete a preference and all its data |
| `count(category?, role?)` | Count preferences |
| `has(key)` / `exists(key)` | Check existence |
| `all(role?, userId?, category?)` | All settings as array |
| `allWithTranslations(locale?, role?, userId?, category?)` | All settings with translated labels |
| `allForUser(userId, locale?, category?)` | All customisable settings for a user, with `is_overridden` flag |
| `getUserOverrides(userId)` | Only the settings a user has explicitly overridden |
| `getByCategory(category, userId?, role?)` | Settings for a category |
| `getCategories()` | Available category names |
| `label(key, locale?)` | Translated label |
| `description(key, locale?)` | Translated description |
| `create(data[])` | Create a preference |
| `createIfNotExists(key, data[])` | Create only if absent |
| `createWithTranslations(key, data[], translations[])` | Create with i18n content |
| `addTranslations(key, translations[])` | Add or update translations |
| `clearAllCache()` | Clear all cached values (works with any driver) |

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License. See [LICENSE.md](LICENSE.md) for details.
