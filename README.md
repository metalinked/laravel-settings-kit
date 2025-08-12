# Laravel Settings Kit

[![Tests](https://img.shields.io/github/actions/workflow/status/metalinked/laravel-settings-kit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metalinked/laravel-settings-kit/actions/workflows/tests.yml)
[![GitHub Release](https://img.shields.io/github/v/release/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![License](https://img.shields.io/packagist/l/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/blob/main/LICENSE.md)

A comprehensive Laravel package for managing global and user-specific settings with role-based permissions, multi-language support, auto-creation capabilities, and a complete REST API for headless applications.

## Table of Contents

- [🚀 Features](#user-content--features)
- [📋 Requirements](#user-content--requirements)
- [⚙️ Installation](#user-content--installation)
- [🛠️ Creating Settings](#user-content--creating-settings)
- [⚡ Quick Start](#user-content--quick-start)
- [🌍 Adding Translations](#user-content--adding-translations)
- [🚀 REST API](#user-content--rest-api)
- [🎨 Multilingual Interface Examples](#user-content--multilingual-interface-examples)
- [📚 API Reference](#user-content--api-reference)
- [🔄 Global Overrides vs Default Values](#user-content--global-overrides-vs-default-values)
- [🔧 Data Types](#user-content--data-types)
- [💡 Advanced Examples](#user-content--advanced-examples)
- [🧪 Testing](#user-content--testing)
- [🤝 Contributing](#user-content--contributing)
- [🔒 Security](#user-content--security)
- [📄 License](#user-content--license)

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

The package supports two types of settings:

- **🌐 Global Unique Settings**: System-wide configurations that apply to everyone (e.g., `maintenance_mode`, `site_name`)
- **👤 User Customizable Settings**: Settings with defaults that users can personalize (e.g., `theme`, `notifications_enabled`)

### Using a Seeder (Recommended)

Create a seeder to define your settings:

```php
// database/seeders/SettingsSeeder.php
use Metalinked\LaravelSettingsKit\Models\Preference;

public function run(): void
{
    $settings = [
        // Global unique setting - when changed, modifies the default value directly
        [
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'system',
            'is_user_customizable' => false, // Global unique
        ],
        [
            'key' => 'site_name',
            'type' => 'string',
            'default_value' => 'My Application',
            'category' => 'general',
            'is_user_customizable' => false, // Global unique
        ],
        // User customizable settings - users can override the default
        [
            'key' => 'theme',
            'type' => 'select',
            'default_value' => 'light',
            'category' => 'appearance',
            'options' => ['light', 'dark'],
            'is_user_customizable' => true, // Users can customize
        ],
        [
            'key' => 'notifications_enabled',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'preferences',
            'is_user_customizable' => true, // Users can customize
        ],
        [
            'key' => 'max_upload_size',
            'type' => 'integer',
            'default_value' => '2048',
            'category' => 'files',
            'is_user_customizable' => false, // Global unique
        ]
    ];

    foreach ($settings as $setting) {
        Preference::firstOrCreate(['key' => $setting['key']], $setting);
    }
}
```

## ⚡ Quick Start

### Understanding Setting Types

**🌐 Global Unique Settings:**
```php
// When set without user ID, modifies the default value directly
Settings::set('maintenance_mode', true);  // All users see maintenance mode
Settings::set('site_name', 'New Name');   // Changes the site name for everyone

// These settings cannot be personalized by users
```

**👤 User Customizable Settings:**
```php
// Default value that users inherit
Settings::get('theme');           // Returns 'light' (default)
Settings::get('theme', 123);      // Returns 'light' (user hasn't customized yet)

// Change global default (affects all users who haven't customized)
Settings::set('theme', 'dark');        // Modifies default_value to 'dark'
Settings::get('theme');                 // Returns 'dark' (new default)
Settings::get('theme', 456);            // Returns 'dark' (new default for all users)

// User personalizes the setting (only then is UserPreference created)
Settings::set('theme', 'custom', 123);  // Creates UserPreference for user 123
Settings::get('theme', 123);             // Returns 'custom' (user's preference)
Settings::get('theme', 456);             // Returns 'dark' (still global default)
```

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

# Auto-create missing settings via API (default: false)
SETTINGS_KIT_API_AUTO_CREATE=true
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

### Auto-Creation via API

The API can automatically create missing settings when they're accessed. This is perfect for API-first applications:

**Global Configuration (affects all API requests):**
```env
# Enable auto-creation for all API requests
SETTINGS_KIT_API_AUTO_CREATE=true
```

**Per-request Control:**
```bash
# Create setting on-the-fly with auto_create parameter
POST /api/settings-kit/new_setting
{
    "value": "some_value",
    "auto_create": true
}
```

**Behavior:**
- **Config enabled + no parameter**: Auto-creates missing settings
- **Config disabled + `auto_create=true`**: Auto-creates missing settings  
- **Config disabled + no parameter**: Returns 404 for missing settings

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

# Set global setting (auto-created if SETTINGS_KIT_API_AUTO_CREATE=true)
POST /api/settings-kit/maintenance_mode
{
    "value": true
}

# Set global setting with explicit auto-creation
POST /api/settings-kit/new_feature_enabled
{
    "value": true,
    "auto_create": true
}

# Set user-specific setting (requires is_user_customizable=true)
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
- If `$userId` is provided: Sets user-specific value (only for `is_user_customizable = true` settings)
- If `$userId` is null: 
  - For global unique settings (`is_user_customizable = false`): Modifies `default_value` directly
  - For user customizable settings (`is_user_customizable = true`): Modifies `default_value` directly (affects all users who haven't customized)
- Set `$autoCreate` to true to create the preference automatically if it doesn't exist

```php
// Global setting change (modifies default_value directly)
Settings::set('maintenance_mode', true); 

// User-specific setting (only for customizable settings)
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
Reset a setting:
- For **user settings**: Removes the user's custom value, reverting to the global default
- For **global unique settings** (`is_user_customizable = false`): Not applicable - these settings don't have user-specific values
- For **user customizable settings** (`is_user_customizable = true`): Only removes user-specific customizations

```php
// Example: Reset user's custom theme back to global default
Settings::forget('theme', $userId);

// Note: Global unique settings cannot be "reset" as they modify default_value directly
```

## 🔄 Global Overrides vs Default Values

The package uses a simple and efficient approach based on the setting type:

### 🌐 Global Unique Settings (`is_user_customizable = false`)

These settings modify the default value directly and apply to everyone:

```php
// Create a global unique setting
Preference::create([
    'key' => 'maintenance_mode', 
    'default_value' => '0',
    'is_user_customizable' => false
]);

Settings::set('maintenance_mode', '1');  // Modifies default_value directly
Settings::get('maintenance_mode');       // Returns '1' for everyone
```

### 👤 User Customizable Settings (`is_user_customizable = true`)

These settings allow users to personalize their experience:

- **Global changes**: Modify the `default_value` directly - affects all users who haven't customized
- **User customization**: Creates entries in `user_preferences` table only when needed

### Practical Example
```php
// 1. Create user customizable preference
Preference::create([
    'key' => 'theme', 
    'default_value' => 'light',
    'is_user_customizable' => true
]);

// 2. Change global default (modifies default_value to 'dark')
Settings::set('theme', 'dark');  // All users without custom preferences see 'dark'

// 3. User personalizes (creates UserPreference entry)
Settings::set('theme', 'custom', 123); // Only user 123 gets 'custom'

// 4. Results:
Settings::get('theme');        // 'dark' (global default)
Settings::get('theme', 123);   // 'custom' (user's preference)
Settings::get('theme', 456);   // 'dark' (global default)
```

### Benefits of This Architecture

- **⚡ Performance**: Users read directly from `preferences` table unless they have custom values
- **�️ Clean Data**: `user_preferences` only contains actual user customizations
- **💡 Simple Logic**: Global changes update the default, user changes create personal preferences
- **🔒 Data Integrity**: Clear separation between global and personal settings

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
        // Set global settings (modifies default values directly)
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
