# Laravel Settings Kit

[![Tests](https://img.shields.io/github/actions/workflow/status/metalinked/laravel-settings-kit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metalinked/laravel-settings-kit/actions/workflows/tests.yml)
[![GitHub Release](https://img.shields.io/github/v/release/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/releases)
[![Latest Stable Version](https://img.shields.io/packagist/v/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![Total Downloads](https://img.shields.io/packagist/dt/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![License](https://img.shields.io/packagist/l/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/blob/main/LICENSE.md)

A comprehensive Laravel package for managing global and user-specific settings with role-based permissions and multi-language support.

## Table of Contents

- [🚀 Features](#features)
- [📋 Requirements](#requirements)
- [⚙️ Installation](#installation)
- [🛠️ Creating Settings](#creating-settings)
  - [Option 1: Using a Seeder (Recommended)](#option-1-using-a-seeder-recommended)
- [⚡ Quick Start](#quick-start)
  - [Basic Usage](#basic-usage)
  - [Creating Settings](#creating-settings-1)
  - [Option 2: Manual Creation in Code](#option-2-manual-creation-in-code)
  - [Option 3: Controller Example](#option-3-controller-example)
- [🌍 Adding Translations](#adding-translations)
  - [Creating Settings with Translations](#creating-settings-with-translations)
  - [Using Translations in Your Interface](#using-translations-in-your-interface)
- [🎨 Multilingual Interface Examples](#multilingual-interface-examples)
- [📚 API Reference](#api-reference)
  - [Settings Facade](#settings-facade)
- [🔧 Data Types](#data-types)
- [💡 Advanced Examples](#advanced-examples)
  - [User Settings Interface](#user-settings-interface)
  - [Admin Global Settings](#admin-global-settings)
  - [Middleware Usage](#middleware-usage)
  - [Creating Settings with Seeder](#creating-settings-with-seeder)
- [🧪 Testing](#testing)
- [🤝 Contributing](#contributing)
- [🔒 Security](#security)
- [📄 License](#license)

## Features

- 🔧 **Global and User-specific Settings** - Manage both application-wide and individual user preferences
- 👥 **Role-based Permissions** - Control which settings are visible/editable by user roles
- 🌍 **Multi-language Support** - Full multilingual support with automatic fallbacks and translation management
- 🚀 **Multiple Data Types** - Support for string, boolean, integer, JSON, and select options
- ⚡ **Cache Support** - Built-in caching to reduce database queries
- 🎨 **Clean API** - Simple and intuitive facade interface with auto-creation capabilities
- 📦 **Easy Integration** - Seamless Laravel integration with service provider auto-discovery
- 🔄 **Auto-Creation** - Automatically create settings on-the-fly with type detection

## Requirements

- **PHP:** 8.1, 8.2, or 8.3
- **Laravel:** 9.x, 10.x, 11.x, or 12.x
- **Database:** MySQL, PostgreSQL, SQLite, or SQL Server

## Installation

Install the package via Composer:

```bash
composer require metalinked/laravel-settings-kit
```

Publish and run the migrations:

```bash
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="migrations"
php artisan migrate
```

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="config"
```

## Creating Settings

**Important:** Settings must be created as `Preference` records before they can be used. You have several options:

### Option 1: Using a Seeder (Recommended)

Create a seeder to define your application's settings:

```bash
php artisan make:seeder SettingsSeeder
```

See the complete example in `examples/SettingsSeeder.php` included with the package. It includes settings for notifications, privacy, appearance, and admin controls with full multilingual support.

## Quick Start

### Basic Usage

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Check if a setting exists
if (Settings::has('allow_comments')) {
    // Get the setting value
    $value = Settings::get('allow_comments');
}

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

### Creating Settings

```php
use Metalinked\LaravelSettingsKit\Models\Preference;

// Create a global boolean setting
Preference::create([
    'key' => 'allow_comments',
    'type' => 'boolean',
    'default_value' => true,
    'category' => 'general',
    'role' => null, // Global setting
]);

// Create a user-specific setting for admins only
Preference::create([
    'key' => 'admin_notifications',
    'type' => 'boolean',
    'default_value' => true,
    'category' => 'notifications',
    'role' => 'admin',
]);

// Create setting only if it doesn't exist
Settings::createIfNotExists('new_feature', [
    'type' => 'boolean',
    'default_value' => false,
    'category' => 'features'
]);
```

### Option 2: Manual Creation in Code

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Create settings programmatically
if (!Settings::has('maintenance_mode')) {
    Settings::createIfNotExists('maintenance_mode', [
        'type' => 'boolean',
        'default_value' => false,
        'category' => 'system'
    ]);
}

// Or use auto-creation when setting values
Settings::setWithAutoCreate('admin_notify_new_users', false);
```

### Option 3: Controller Example

Here's a practical example for a settings controller:

```php
public function show()
{
    $settings = [
        'maintenance_mode' => (bool) Settings::get('maintenance_mode', false),
        'admin_notify_new_users' => (bool) Settings::get('admin_notify_new_users', false),
    ];
    return view('settings-test', compact('settings'));
}

public function save(Request $request)
{
    // Create preferences if they don't exist, then set values
    Settings::setWithAutoCreate('maintenance_mode', $request->has('maintenance_mode'));
    Settings::setWithAutoCreate('admin_notify_new_users', $request->has('admin_notify_new_users'));
    
    return redirect()->route('settings.test')->with('success', 'Configuration saved!');
}
```

**💡 Pro Tip:** Check out `examples/SettingsControllerExample.php` for more advanced patterns including validation, error handling, and bulk initialization.

### Adding Translations

The package includes a powerful multilingual system that allows you to provide labels and descriptions for your settings in multiple languages:

```php
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;

$preference = Preference::where('key', 'allow_comments')->first();

// Add English translation
PreferenceContent::create([
    'preference_id' => $preference->id,
    'lang' => 'en',
    'title' => 'Allow Comments',
    'text' => 'Enable or disable comments on posts',
]);

// Add Spanish translation
PreferenceContent::create([
    'preference_id' => $preference->id,
    'lang' => 'es',
    'title' => 'Permitir Comentarios',
    'text' => 'Activar o desactivar comentarios en las publicaciones',
]);
```

### Creating Settings with Translations

You can create settings with translations in one step:

```php
Settings::createWithTranslations('maintenance_mode', [
    'type' => 'boolean',
    'default_value' => '0',
    'category' => 'system',
], [
    'en' => [
        'title' => 'Maintenance Mode',
        'description' => 'Enable maintenance mode for system updates'
    ],
    'es' => [
        'title' => 'Modo Mantenimiento', 
        'description' => 'Activar modo de mantenimiento para actualizaciones del sistema'
    ],
    'ca' => [
        'title' => 'Mode Manteniment',
        'description' => 'Activar el mode de manteniment per a actualitzacions del sistema'
    ]
]);

// Add translations to existing settings
Settings::addTranslations('maintenance_mode', [
    'fr' => [
        'title' => 'Mode Maintenance',
        'description' => 'Activer le mode maintenance pour les mises à jour système'
    ]
]);
```

### Using Translations in Your Interface

```php
// Get translated labels and descriptions
$label = Settings::label('maintenance_mode', 'es'); // Returns: "Modo Mantenimiento"
$description = Settings::description('maintenance_mode', 'es'); // Returns: "Activar modo de mantenimiento..."

// Get all settings with translations for current locale
$settingsWithTranslations = Settings::allWithTranslations(app()->getLocale());

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

## Multilingual Interface Examples

The package includes practical examples for creating multilingual settings interfaces:

- **`examples/MultilingualSettingsController.php`** - Complete controller with language switching
- **`examples/views/admin-multilingual-settings.blade.php`** - Admin interface with language selector
- **`examples/views/user-multilingual-settings.blade.php`** - User interface with live toggles and translations

## API Reference

### Settings Facade

#### `get(string $key, int $userId = null)`
Get a setting value. Returns user-specific value if `$userId` is provided and exists, otherwise returns global default.

#### `set(string $key, mixed $value, int $userId = null, bool $autoCreate = false)`
Set a setting value. If `$userId` is provided, sets user-specific value, otherwise sets global default. Set `$autoCreate` to true to create the preference automatically if it doesn't exist.

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

#### `all(string $role = null, int $userId = null)`
Get all settings, optionally filtered by role and with user values.

#### `allWithTranslations(string $locale = null, string $role = null, int $userId = null)`
Get all settings with their translated labels and descriptions for a specific locale.

#### `createIfNotExists(string $key, array $data)`
Create a preference only if it doesn't already exist.

#### `createWithTranslations(string $key, array $preferenceData, array $translations = [])`
Create a preference with translations in multiple languages.

#### `addTranslations(string $key, array $translations)`
Add or update translations for an existing preference.

#### `forget(string $key, int $userId = null)`
Remove a setting value (resets to default).

## Data Types

The package supports the following data types:

- `string` - Text values
- `boolean` - True/false values
- `integer` - Numeric values
- `json` - JSON objects/arrays
- `select` - Predefined options (stored as JSON in options field)

## Advanced Examples

### User Settings Interface

```php
// Controller for user settings
class UserSettingsController extends Controller 
{
    public function show(Request $request)
    {
        $user = $request->user();
        $categories = Settings::getCategories();
        $userSettings = [];
        
        foreach ($categories as $category) {
            $categorySettings = Settings::getByCategory($category, $user->id);
            if (!empty($categorySettings)) {
                $userSettings[$category] = $categorySettings;
            }
        }
        
        return view('settings.user', compact('userSettings'));
    }
    
    public function update(Request $request)
    {
        $user = $request->user();
        $settings = $request->get('settings', []);
        
        foreach ($settings as $key => $value) {
            if (Settings::exists($key)) {
                Settings::set($key, $value, $user->id);
            }
        }
        
        return redirect()->back()->with('success', 'Settings updated!');
    }
}
```

### Admin Global Settings

```php
// Admin can modify global settings
class AdminSettingsController extends Controller
{
    public function updateGlobal(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }
        
        $settings = $request->get('global_settings', []);
        
        foreach ($settings as $key => $value) {
            Settings::set($key, $value); // No user_id = global setting
        }
        
        return redirect()->back()->with('success', 'Global settings updated!');
    }
}
```

### Middleware Usage

```php
// Check if a feature is enabled
class CheckFeatureEnabled
{
    public function handle($request, Closure $next, $feature)
    {
        if (!Settings::isEnabled($feature)) {
            abort(404, 'Feature disabled');
        }
        
        return $next($request);
    }
}

// Usage in routes
Route::get('/contact', [ContactController::class, 'show'])
     ->middleware('feature:contact_form_enabled');
```

### Creating Settings with Seeder

```php
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;

// Create a setting with translations
$preference = Preference::create([
    'key' => 'email_notifications',
    'type' => 'boolean',
    'default_value' => '1',
    'category' => 'notifications',
    'role' => null,
]);

// Add translations
PreferenceContent::create([
    'preference_id' => $preference->id,
    'lang' => 'en',
    'title' => 'Email Notifications',
    'text' => 'Receive important notifications via email',
]);

PreferenceContent::create([
    'preference_id' => $preference->id,
    'lang' => 'es',
    'title' => 'Notificaciones por Email',
    'text' => 'Recibir notificaciones importantes por correo',
]);

// Or use the Settings facade for a cleaner approach:
Settings::createWithTranslations('email_notifications', [
    'type' => 'boolean',
    'default_value' => '1',
    'category' => 'notifications',
], [
    'en' => ['title' => 'Email Notifications', 'description' => 'Receive important notifications via email'],
    'es' => ['title' => 'Notificaciones por Email', 'description' => 'Recibir notificaciones importantes por correo'],
    'ca' => ['title' => 'Notificacions per Email', 'description' => 'Rebre notificacions importants per correu electrònic']
]);
```

The package comes with sensible defaults, but you can customize behavior by publishing the config file:

```php
return [
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'settings_kit',
    ],
    'tables' => [
        'preferences' => 'preferences',
        'preference_contents' => 'preference_contents',
        'user_preferences' => 'user_preferences',
    ],
];
```

## Testing

Run the tests with:

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email info@metalinked.net instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
