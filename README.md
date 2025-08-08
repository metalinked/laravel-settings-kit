# Laravel Settings Kit

[![Tests](https://img.shields.io/github/actions/workflow/status/metalinked/laravel-settings-kit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metalinked/laravel-settings-kit/actions/workflows/tests.yml)
[![GitHub Release](https://img.shields.io/github/v/release/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/releases)
[![Latest Stable Version](https://img.shields.io/packagist/v/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![Total Downloads](https://img.shields.io/packagist/dt/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![License](https://img.shields.io/packagist/l/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/blob/main/LICENSE.md)

A comprehensive Laravel package for managing global and user-specific settings with role-based permissions, multi-language support, auto-creation capabilities, and a complete REST API for headless applications.

## Table of Contents

- [🚀 Features](#features)
- [📋 Requirements](#requirements)
- [⚙️ Installation](#installation)
- [🛠️ Creating Settings](#creating-settings)
- [⚡ Quick Start](#quick-start)
- [🌍 Adding Translations](#adding-translations)
- [🚀 REST API](#rest-api)
- [🎨 Multilingual Interface Examples](#multilingual-interface-examples)
- [📚 API Reference](#api-reference)
- [🔄 Global Overrides vs Default Values](#global-overrides-vs-default-values)
- [🔧 Data Types](#data-types)
- [💡 Advanced Examples](#advanced-examples)
- [🧪 Testing](#testing)
- [🤝 Contributing](#contributing)
- [🔒 Security](#security)
- [📄 License](#license)

## 🚀 Features

- 🔧 **Global and User-specific Settings** - Manage both application-wide and individual user preferences
- 👥 **Role-based Permissions** - Control which settings are visible/editable by user roles
- 🌍 **Multi-language Support** - Full multilingual support with automatic fallbacks and translation management
- 🚀 **Multiple Data Types** - Support for string, boolean, integer, JSON, and select options
- ⚡ **Cache Support** - Built-in caching to reduce database queries
- 🎨 **Clean API** - Simple and intuitive facade interface with auto-creation capabilities
- 🔄 **Auto-Creation** - Automatically create settings on-the-fly with type detection
- 🌐 **REST API** - Complete API for headless applications and JavaScript frameworks
- 🛡️ **Flexible Authentication** - Support for token, Sanctum, and Passport authentication
- 💾 **Database Agnostic** - Works with any Laravel-supported database

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher

## ⚙️ Installation

Install the package via Composer:

```bash
composer require metalinked/laravel-settings-kit
```

Publish and run the migrations:

```bash
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="migrations"
php artisan migrate
```

Optionally, publish the config file:

```bash
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="config"
```

## 🛠️ Creating Settings

### Using a Seeder (Recommended)

Create a seeder to define your settings:

```php
// database/seeders/SettingsSeeder.php
use Metalinked\LaravelSettingsKit\Models\Preference;

public function run(): void
{
    $settings = [
        [
            'key' => 'site_name',
            'type' => 'string',
            'default_value' => 'My Application',
            'category' => 'general',
        ],
        [
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'content',
        ],
        [
            'key' => 'max_upload_size',
            'type' => 'integer',
            'default_value' => '2048',
            'category' => 'files',
        ]
    ];

    foreach ($settings as $setting) {
        Preference::firstOrCreate(['key' => $setting['key']], $setting);
    }
}
```

## ⚡ Quick Start

### Basic Usage

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Get a global setting (returns null if not found)
$value = Settings::get('allow_comments');

// Get a user-specific setting
$value = Settings::get('email_notifications', $userId);

// Set a global setting (throws exception if preference doesn't exist)
Settings::set('allow_comments', true);

// Set a user-specific setting
Settings::set('email_notifications', false, $userId);

// Set a setting with auto-creation (creates preference if it doesn't exist)
Settings::setWithAutoCreate('new_feature_enabled', true);

// Check if a setting is enabled (boolean)
if (Settings::isEnabled('maintenance_mode')) {
    // Application is in maintenance mode
}

// Get translated label and description
$label = Settings::label('allow_comments');
$description = Settings::description('allow_comments');
```

### Creating Settings with Auto-Detection

```php
// Automatically create settings with type detection
Settings::setWithAutoCreate('maintenance_mode', false); // Creates boolean preference
Settings::setWithAutoCreate('items_per_page', 20);      // Creates integer preference
Settings::setWithAutoCreate('theme_config', ['dark' => true]); // Creates JSON preference

// Check if a setting exists before using
if (Settings::has('feature_enabled')) {
    $value = Settings::get('feature_enabled');
}

// Create setting only if it doesn't exist
Settings::createIfNotExists('new_feature', [
    'type' => 'boolean',
    'default_value' => '0',
    'category' => 'features'
]);
```

## 🌍 Adding Translations

The package includes a powerful multilingual system that allows you to provide labels and descriptions for your settings in multiple languages:

```php
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;

$preference = Preference::where('key', 'allow_comments')->first();

// Add English translation
PreferenceContent::create([
    'preference_id' => $preference->id,
    'locale' => 'en',
    'label' => 'Allow Comments',
    'description' => 'Enable or disable comments on posts',
]);

// Add Catalan translation
PreferenceContent::create([
    'preference_id' => $preference->id,
    'locale' => 'ca',
    'label' => 'Permetre Comentaris',
    'description' => 'Activa o desactiva els comentaris a les publicacions',
]);
```

### Creating Settings with Translations

```php
// Create setting with multiple translations at once
Settings::createWithTranslations('newsletter_enabled', [
    'type' => 'boolean',
    'default_value' => '1',
    'category' => 'marketing'
], [
    'en' => [
        'label' => 'Newsletter Subscription',
        'description' => 'Enable newsletter signup form'
    ],
    'ca' => [
        'label' => 'Subscripció al Butlletí',
        'description' => 'Activa el formulari de subscripció al butlletí'
    ],
    'es' => [
        'label' => 'Suscripción al Boletín', 
        'description' => 'Habilita el formulario de suscripción al boletín'
    ]
]);

// Get all settings with translations for a specific locale
$settings = Settings::allWithTranslations('ca');

// This returns an array like:
[
    'maintenance_mode' => [
        'value' => false,
        'type' => 'boolean',
        'category' => 'system',
        'label' => 'Mode Manteniment',
        'description' => 'Activar el mode de manteniment...',
        'key' => 'maintenance_mode'
    ]
]
```

## 🚀 REST API

The package provides a complete REST API for managing settings, perfect for headless Laravel applications, mobile apps, or frontend JavaScript frameworks.

### API Configuration

Enable the API by adding these variables to your `.env` file:

```env
# Enable/disable the API
SETTINGS_KIT_API_ENABLED=true

# API route prefix (default: api/settings-kit)
SETTINGS_KIT_API_PREFIX=api/settings-kit

# Authentication mode: token, sanctum, or passport
SETTINGS_KIT_API_AUTH=token

# API token (required if using token auth)
SETTINGS_KIT_API_TOKEN=your-secure-random-token-here
```

### API Authentication

The API supports multiple authentication methods:

**Token Authentication** (Simple):
```bash
curl -H "Authorization: Bearer your-secure-random-token-here" \
     http://your-app.com/api/settings-kit
```

**Sanctum Authentication** (User-based):
```env
SETTINGS_KIT_API_AUTH=sanctum
```
```bash
curl -H "Authorization: Bearer user-sanctum-token" \
     http://your-app.com/api/settings-kit
```

**Passport Authentication** (OAuth2):
```env
SETTINGS_KIT_API_AUTH=passport
```

### API Endpoints

- **GET** `/api/settings-kit` - Get all settings with optional filtering
- **GET** `/api/settings-kit/{key}` - Get specific setting
- **POST** `/api/settings-kit/{key}` - Create/update setting value
- **PUT** `/api/settings-kit/{key}` - Update setting value
- **DELETE** `/api/settings-kit/{key}` - Reset setting to default
- **GET** `/api/settings-kit/categories` - Get available categories
- **POST** `/api/settings-kit/preferences` - Create new preference

**Query Parameters:**
- `locale` - Language for translations (e.g., `ca`, `es`, `en`)
- `user_id` - Get/set user-specific settings
- `category` - Filter by category
- `role` - Filter by role

**Example Usage:**
```bash
# Get all settings with Catalan translations
GET /api/settings-kit?locale=ca

# Get user-specific settings
GET /api/settings-kit?user_id=123&locale=en

# Set global setting with auto-creation
POST /api/settings-kit/maintenance_mode
{
    "value": true,
    "auto_create": true
}

# Set user-specific setting
POST /api/settings-kit/email_notifications
{
    "value": false,
    "user_id": 123
}
```

**📖 Complete API Documentation:** See `examples/API_USAGE.md` for detailed examples, error handling, and JavaScript integration examples.

## 🎨 Multilingual Interface Examples

The package includes practical examples for creating multilingual settings interfaces:

- **Admin Settings Panel** - `examples/admin-settings.blade.php`
- **User Preferences** - `examples/user-settings.blade.php`
- **Multilingual Admin Panel** - `examples/admin-multilingual-settings.blade.php`
- **Controller Examples** - `examples/SettingsControllerExample.php`

## 📚 API Reference

### Settings Facade

#### `get(string $key, int $userId = null)`
Get a setting value. Returns user-specific value if `$userId` is provided and exists, otherwise returns global default.

#### `set(string $key, mixed $value, int $userId = null, bool $autoCreate = false)`
Set a setting value:
- If `$userId` is provided: Sets user-specific value
- If `$userId` is null: Creates a global override (preserves original default value)
- Set `$autoCreate` to true to create the preference automatically if it doesn't exist

```php
// Global override (original default preserved)
Settings::set('maintenance_mode', true); 

// User-specific setting
Settings::set('theme', 'dark', $userId);
```

#### `setWithAutoCreate(string $key, mixed $value, int $userId = null)`
Set a setting value, creating the preference automatically if it doesn't exist.

#### `has(string $key)` / `exists(string $key)`
Check if a preference exists in the database.

#### `isEnabled(string $key, int $userId = null)`
Check if a boolean setting is enabled.

#### `label(string $key, string $locale = null)`
Get the translated label for a setting.

#### `description(string $key, string $locale = null)`
Get the translated description for a setting.

#### `create(array $data)`
Create a new preference.

#### `createIfNotExists(string $key, array $data)`
Create a preference only if it doesn't already exist.

#### `createWithTranslations(string $key, array $preferenceData, array $translations = [])`
Create a preference with translations in multiple languages.

#### `addTranslations(string $key, array $translations)`
Add or update translations for an existing preference.

#### `forget(string $key, int $userId = null)`
Reset a setting to its original default value:
- For **user settings**: Removes the user's custom value, reverting to the global value
- For **global settings**: Removes any global override, reverting to the original default value defined when the preference was created

```php
// Example: Reset user's custom theme back to global default
Settings::forget('theme', $userId);

// Example: Reset global override back to original default
Settings::create(['key' => 'site_name', 'default_value' => 'My App']); // Original default
Settings::set('site_name', 'Custom Name'); // Global override 
Settings::forget('site_name'); // Resets back to 'My App'
```

## 🔄 Global Overrides vs Default Values

The package uses a sophisticated system that separates **original default values** from **global overrides**:

### How it Works
- **Default Value**: The original value defined when creating a preference (preserved forever)
- **Global Override**: A temporary global value that can be reset back to the original default
- **User Value**: User-specific values that override both global and default values

### Value Priority (highest to lowest)
1. **User-specific value** (if user ID provided and value exists)
2. **Global override** (if exists)  
3. **Original default value** (fallback)

### Practical Example
```php
// 1. Create preference with original default
Settings::create(['key' => 'theme', 'default_value' => 'light']);

// 2. Set global override
Settings::set('theme', 'dark'); // Global override, 'light' still preserved

// 3. User can have personal setting
Settings::set('theme', 'custom', $userId); // User-specific value

// 4. Reset behaviors:
Settings::forget('theme', $userId); // User gets global override ('dark')
Settings::forget('theme');         // Global resets to original default ('light')
```

This system ensures you never lose your original configuration while allowing flexible overrides at both global and user levels.

> **⚠️ Important:** This architecture change means that `Settings::set()` without a user ID now creates a global override instead of modifying the original default value. This ensures better data integrity and allows proper reset functionality.

## 🔧 Data Types

The package supports the following data types:

- `string` - Text values
- `boolean` - True/false values
- `integer` - Numeric values
- `json` - JSON objects/arrays
- `select` - Predefined options (stored as JSON in options field)

## 💡 Advanced Examples

### User Settings Interface

```php
// Controller for user settings
class UserSettingsController extends Controller 
{
    public function index()
    {
        $userId = auth()->id();
        $settings = [
            'email_notifications' => Settings::get('email_notifications', $userId),
            'theme' => Settings::get('theme', $userId),
            'language' => Settings::get('language', $userId),
        ];

        return view('settings.user', compact('settings'));
    }

    public function update(Request $request)
    {
        $userId = auth()->id();
        
        foreach ($request->only(['email_notifications', 'theme', 'language']) as $key => $value) {
            Settings::set($key, $value, $userId);
        }

        return redirect()->back()->with('success', 'Settings updated!');
    }
}
```

### Admin Global Settings

```php
// Controller for admin global settings
class AdminSettingsController extends Controller
{
    public function update(Request $request)
    {
        // Set global overrides (preserves original defaults)
        Settings::set('maintenance_mode', $request->has('maintenance_mode'));
        Settings::set('max_users', $request->input('max_users', 1000));
        
        // Auto-create settings that might not exist
        Settings::setWithAutoCreate('new_feature_flag', $request->has('new_feature'));
        
        return redirect()->back()->with('success', 'Global settings updated!');
    }

    public function reset($key)
    {
        // Reset to original default value
        Settings::forget($key);
        
        return redirect()->back()->with('success', "Setting '{$key}' reset to default!");
    }
}
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

## 🤝 Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security related issues, please email info@metalinked.net instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
