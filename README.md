# Laravel Settings Kit

[![Tests](https://img.shields.io/github/actions/workflow/status/metalinked/laravel-settings-kit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metalinked/laravel-settings-kit/actions/workflows/tests.yml)
[![GitHub Release](https://img.shields.io/github/v/release/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/releases)
[![Latest Stable Version](https://img.shields.io/packagist/v/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![Total Downloads](https://img.shields.io/packagist/dt/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![License](https://img.shields.io/packagist/l/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/blob/main/LICENSE.md)

A comprehensive Laravel package for managing global and user-specific settings with role-based permissions and multi-language support.

## Features

- 🔧 **Global and User-specific Settings** - Manage both application-wide and individual user preferences
- 👥 **Role-based Permissions** - Control which settings are visible/editable by user roles
- 🌍 **Multi-language Support** - Automatic translation of setting labels and descriptions
- 🚀 **Multiple Data Types** - Support for string, boolean, integer, JSON, and select options
- ⚡ **Cache Support** - Built-in caching to reduce database queries
- 🎨 **Clean API** - Simple and intuitive facade interface
- 📦 **Easy Integration** - Seamless Laravel integration with service provider auto-discovery

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

## Quick Start

### Basic Usage

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Get a global setting
$value = Settings::get('allow_comments');

// Get a user-specific setting
$value = Settings::get('email_notifications', $userId);

// Set a global setting
Settings::set('allow_comments', true);

// Set a user-specific setting
Settings::set('email_notifications', false, $userId);

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
```

### Adding Translations

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

## API Reference

### Settings Facade

#### `get(string $key, int $userId = null)`
Get a setting value. Returns user-specific value if `$userId` is provided and exists, otherwise returns global default.

#### `set(string $key, mixed $value, int $userId = null)`
Set a setting value. If `$userId` is provided, sets user-specific value, otherwise sets global default.

#### `isEnabled(string $key, int $userId = null)`
Check if a boolean setting is enabled.

#### `label(string $key, string $locale = null)`
Get the translated label for a setting.

#### `description(string $key, string $locale = null)`
Get the translated description for a setting.

#### `all(string $role = null, int $userId = null)`
Get all settings, optionally filtered by role and with user values.

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
