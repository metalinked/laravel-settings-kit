# Laravel Settings Kit

[![Tests](https://img.shields.io/github/actions/workflow/status/metalinked/laravel-settings-kit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metalinked/laravel-settings-kit/actions/workflows/tests.yml)
[![GitHub Release](https://img.shields.io/github/v/release/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![License](https://img.shields.io/packagist/l/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/blob/main/LICENSE.md)

A comprehensive Laravel package for managing global and user-specific settings with role-based permissions, multi-language support, auto-creation capabilities, and a complete REST API for headless applications.

## 🚀 Quick Start

Install the package and get started in minutes:

```bash
# Install the package
composer require metalinked/laravel-settings-kit

# Publish and run migrations
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="migrations"
php artisan migrate
```

**Basic Usage:**
```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Get settings (with auto-creation if they don't exist)
$siteName = Settings::get('site_name') ?? 'My App';
$userTheme = Settings::get('theme', $userId) ?? 'light';

// Set settings (auto-creates if needed)
Settings::set('site_name', 'My Awesome App');           // Global setting
Settings::set('theme', 'dark', $userId);                // User setting

// Check boolean settings
if (Settings::isEnabled('maintenance_mode')) {
    // Show maintenance page
}
```

**That's it!** The package automatically creates settings as you use them.

## 📚 Table of Contents

- [🚀 Quick Start](#-quick-start)
- [✨ Features](#-features)
- [📦 Installation](#-installation)
- [🎯 Basic Usage](#-basic-usage)
- [📊 Types of Settings](#-types-of-settings)
- [🌍 Multilingual Support](#-multilingual-support)
- [🔌 REST API](#-rest-api)
- [🚀 Advanced Features](#-advanced-features)
- [🔧 Configuration](#-configuration)
- [🔧 Troubleshooting](#-troubleshooting)
- [🧪 Testing](#-testing)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

## ✨ Features

- 🌍 **Global and User-specific Settings** - Manage both application-wide and individual user preferences
- 👥 **Role-based Permissions** - Control which settings are visible/editable by user roles
- 🌐 **Multi-language Support** - Full multilingual support with automatic fallbacks
- 📊 **Multiple Data Types** - Support for string, boolean, integer, JSON, and select options
- 🚀 **Auto-Creation** - Automatically create settings on-the-fly with intelligent type detection
- 🔌 **REST API** - Complete API for headless applications and JavaScript frameworks
- 🔐 **Flexible Authentication** - Support for token, Sanctum, and Passport authentication
- ⚡ **Cache Support** - Built-in caching to reduce database queries
- 💾 **Database Agnostic** - Works with any Laravel-supported database

## 📦 Installation

### Requirements
- PHP 8.1 or higher
- Laravel 10.0 or higher

### Step 1: Install Package

```bash
composer require metalinked/laravel-settings-kit
```

### Step 2: Run Migrations

```bash
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="migrations"
php artisan migrate
```

### Step 3: Publish Config (Optional)

```bash
php artisan vendor:publish --provider="Metalinked\LaravelSettingsKit\SettingsKitServiceProvider" --tag="config"
```

## 🎯 Basic Usage

### Simple Settings Management

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Get a setting (returns null if not found)
$value = Settings::get('app_name');

// Set a setting (auto-creates if it doesn't exist)
Settings::set('app_name', 'My Application');

// Get user-specific setting
$userTheme = Settings::get('theme', $userId);

// Set user-specific setting
Settings::set('theme', 'dark', $userId);

// Check if setting is enabled (boolean check)
if (Settings::isEnabled('maintenance_mode')) {
    abort(503, 'Under maintenance');
}

// Get all settings
$allSettings = Settings::all();

// Get settings by category
$systemSettings = Settings::all(category: 'system');
```

### Auto-Creation with Type Detection

The package automatically detects data types when creating settings:

```php
Settings::set('maintenance_mode', false);              // Creates boolean
Settings::set('max_users', 100);                       // Creates integer  
Settings::set('app_name', 'My App');                   // Creates string
Settings::set('config', ['key' => 'value']);           // Creates JSON
```

## 📊 Types of Settings

The package supports two distinct types of settings:

### Global Unique Settings
Settings that apply to the entire application. When you change them, you're modifying the global default value.

```php
// Examples: maintenance_mode, site_name, max_upload_size
Settings::set('maintenance_mode', true);    // Affects entire application
Settings::set('site_name', 'My Site');      // Changes site name for everyone
```

### User Customizable Settings  
Settings with global defaults that individual users can override.

```php
// Global default (affects all users who haven't customized)
Settings::set('theme', 'light');            // Sets default theme to 'light'

// User customization (only affects specific user)
Settings::set('theme', 'dark', 123);        // User 123 prefers dark theme

// Results:
Settings::get('theme');                      // Returns 'light' (global default)
Settings::get('theme', 123);                // Returns 'dark' (user's preference)
Settings::get('theme', 456);                // Returns 'light' (inherits default)
```

### Creating Predefined Settings

For better control, create settings upfront using a seeder:

```php
// database/seeders/SettingsSeeder.php
use Metalinked\LaravelSettingsKit\Models\Preference;

public function run(): void {
    $settings = [
        // Global unique settings
        [
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'system',
            'is_user_customizable' => false,
        ],
        [
            'key' => 'site_name', 
            'type' => 'string',
            'default_value' => 'My Application',
            'category' => 'general',
            'is_user_customizable' => false,
        ],
        // User customizable settings
        [
            'key' => 'theme',
            'type' => 'select',
            'default_value' => 'light',
            'category' => 'appearance',
            'options' => ['light', 'dark', 'auto'],
            'is_user_customizable' => true,
        ],
        [
            'key' => 'notifications_enabled',
            'type' => 'boolean', 
            'default_value' => '1',
            'category' => 'preferences',
            'is_user_customizable' => true,
        ],
    ];

    foreach ($settings as $setting) {
        Preference::firstOrCreate(['key' => $setting['key']], $setting);
    }
}
```

### Using Settings

```php
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
Settings::createIfNotExists('welcome_message', [
    'type' => 'string',
    'default_value' => 'Welcome to our app!',
    'category' => 'general'
]);
```

## 🌍 Multilingual Support

Add translations to make your settings interface multilingual:

### Adding Translations

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

// Create setting with translations
Settings::createWithTranslations('notification_email', [
    'type' => 'boolean',
    'default_value' => '1',
    'category' => 'notifications',
], [
    'en' => [
        'title' => 'Email Notifications',
        'description' => 'Receive notifications via email'
    ],
    'es' => [
        'title' => 'Notificaciones por Email', 
        'description' => 'Recibir notificaciones por correo electrónico'
    ],
    'ca' => [
        'title' => 'Notificacions per Email',
        'description' => 'Rebre notificacions per correu electrònic'
    ]
]);
```

### Using Translations

```php
// Get translated labels and descriptions
$label = Settings::label('notification_email', 'es');          // "Notificaciones por Email"
$description = Settings::description('notification_email', 'ca'); // "Rebre notificacions per correu electrònic"

// Auto-fallback to English if translation missing
$label = Settings::label('notification_email', 'fr');          // "Email Notifications" (fallback)
```

### Bulk Translation Management

```php
// Add translations to existing settings
Settings::addTranslations('theme', [
    'en' => ['title' => 'Theme', 'description' => 'Choose your preferred theme'],
    'es' => ['title' => 'Tema', 'description' => 'Elige tu tema preferido'],
    'ca' => ['title' => 'Tema', 'description' => 'Tria el teu tema preferit']
]);

// Add translations with more data
Settings::addTranslations('newsletter_signup', [
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

## 🔌 REST API

The package provides a complete REST API for managing settings, perfect for headless Laravel applications, mobile apps, or frontend JavaScript frameworks.

### API Configuration

The API supports both development and production environments with different setup requirements.

#### 🛠️ Development Setup (Quick Start)

For local development and testing, you can bypass authentication entirely:

```env
# Enable API
SETTINGS_KIT_API_ENABLED=true

# Bypass authentication in local/testing environments
SETTINGS_KIT_API_DISABLE_AUTH_DEV=true

# Auto-create missing settings (recommended for development)
SETTINGS_KIT_API_AUTO_CREATE=true

# Optional: Custom API prefix
SETTINGS_KIT_API_PREFIX=api/settings-kit
```

With this setup, you can immediately use the API without any authentication:

```bash
# Test endpoint
curl http://localhost/api/settings-kit

# Get specific setting
curl http://localhost/api/settings-kit/global/site_name
```

### Production Setup

For production environments, configure proper authentication:

```env
# Enable API
SETTINGS_KIT_API_ENABLED=true

# Development (no authentication)
SETTINGS_KIT_API_DISABLE_AUTH_DEV=true

# Production (require authentication)
SETTINGS_KIT_API_DISABLE_AUTH_DEV=false
SETTINGS_KIT_API_AUTH=token
SETTINGS_KIT_API_TOKEN=your-secure-token
```

### Global Settings Endpoints

```bash
# Get all global settings
GET /api/settings-kit/global

# Get specific global setting
GET /api/settings-kit/global/site_name

# Set global setting
POST /api/settings-kit/global/site_name
{
    "value": "My New Site Name"
}

# Update global setting
PUT /api/settings-kit/global/maintenance_mode
{
    "value": true
}

# Reset global setting to default
DELETE /api/settings-kit/global/site_name
```

### User Settings Endpoints

```bash
# Get user's settings
GET /api/settings-kit/user?user_id=123

# Get specific user setting
GET /api/settings-kit/user/theme?user_id=123

# Set user setting
POST /api/settings-kit/user/theme?user_id=123
{
    "value": "dark"
}

# Reset user setting (falls back to global default)
DELETE /api/settings-kit/user/theme?user_id=123
```

### API Response Format

```json
{
    "success": true,
    "data": {
        "theme": {
            "value": "dark",
            "type": "select",
            "category": "appearance",
            "required": false,
            "options": ["light", "dark", "auto"],
            "label": "Theme",
            "description": "Choose your preferred theme",
            "key": "theme"
        }
    },
    "meta": {
        "count": 1,
        "locale": "en",
        "role": null,
        "user_id": 123,
        "category": "appearance"
    }
}
```

### Authentication Examples

```bash
# Development (no token required)
curl http://localhost/api/settings-kit/global/site_name

# Production with token
curl -H "Authorization: Bearer your-token" \
     https://yourapp.com/api/settings-kit/global/site_name

# With Sanctum
curl -H "Authorization: Bearer sanctum-token" \
     https://yourapp.com/api/settings-kit/user/theme?user_id=123
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

#### Core Endpoints
- **GET** `/api/settings-kit` - Get all settings with optional filtering
- **GET** `/api/settings-kit/categories` - Get available categories  
- **POST** `/api/settings-kit/preferences` - Create new preference

#### Global Settings (System-wide)
- **GET** `/api/settings-kit/global/{key}` - Get specific global setting
- **POST** `/api/settings-kit/global/{key}` - Create/update global setting
- **PUT** `/api/settings-kit/global/{key}` - Update global setting
- **DELETE** `/api/settings-kit/global/{key}` - Reset global setting to default

#### User Settings (User-specific)
- **GET** `/api/settings-kit/user/{key}` - Get specific user setting
- **POST** `/api/settings-kit/user/{key}` - Create/update user setting
- **PUT** `/api/settings-kit/user/{key}` - Update user setting
- **DELETE** `/api/settings-kit/user/{key}` - Reset user setting to default

#### Legacy Endpoints (Backwards Compatibility)
- **GET** `/api/settings-kit/{key}` - Get specific setting (auto-detects global/user)
- **POST** `/api/settings-kit/{key}` - Create/update setting value
- **PUT** `/api/settings-kit/{key}` - Update setting value
- **DELETE** `/api/settings-kit/{key}` - Reset setting to default

**Query Parameters:**
- `locale` - Language for translations (e.g., `ca`, `es`, `en`)
- `user_id` - Specify user ID (required for user endpoints)
- `category` - Filter by category
- `role` - Filter by role

**Example Usage:**
```bash
# Get all settings with Catalan translations
GET /api/settings-kit?locale=ca

# Get specific global setting
GET /api/settings-kit/global/site_name

# Get user-specific setting
GET /api/settings-kit/user/theme?user_id=123

# Update global setting
POST /api/settings-kit/global/maintenance_mode
{"value": true}

# Update user setting
POST /api/settings-kit/user/notifications?user_id=123
{"value": false}

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

**?? Complete API Documentation:** See `examples/API_USAGE.md` for detailed examples, error handling, and JavaScript integration examples.

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
- **🧹 Clean Data**: `user_preferences` only contains actual user customizations
- **💡 Simple Logic**: Global changes update the default, user changes create personal preferences
- **🔒 Data Integrity**: Clear separation between global and personal settings

## 🔧 Data Types

The package supports the following data types:

- `string` - Text values
- `boolean` - True/false values
- `integer` - Numeric values
- `json` - JSON objects/arrays
- `select` - Predefined options (stored as JSON in options field)

## 🚀 Advanced Features

### Data Architecture

The package uses an efficient architecture that minimizes database queries:
- **Global Settings**: Stored in `preferences.default_value` - one row per setting
- **User Customizations**: Only stored in `user_preferences` when users actually customize settings
- **Multilingual**: Translations stored in `preference_contents` with fallback to default locale

### Controller Examples

#### User Settings Panel
```php
class UserSettingsController extends Controller {
    public function index() {
        $userId = auth()->id();
        $settings = [
            'email_notifications' => Settings::get('email_notifications', $userId),
            'theme' => Settings::get('theme', $userId),
            'language' => Settings::get('language', $userId),
        ];

        return view('settings.user', compact('settings'));
    }

    public function update(Request $request) {
        $userId = auth()->id();
        
        foreach ($request->only(['email_notifications', 'theme', 'language']) as $key => $value) {
            Settings::set($key, $value, $userId);
        }

        return redirect()->back()->with('success', 'Settings updated!');
    }
}
```

#### Admin Global Settings
```php
class AdminSettingsController extends Controller {
    public function update(Request $request) {
        // Global settings (affects all users)
        Settings::set('maintenance_mode', $request->has('maintenance_mode'));
        Settings::set('max_users', $request->input('max_users', 1000));
        
        // Auto-create settings
        Settings::setWithAutoCreate('new_feature_flag', $request->has('new_feature'));
        
        return redirect()->back()->with('success', 'Global settings updated!');
    }
}
```

### Advanced Data Types

#### JSON Settings
```php
// Store complex data structures
Settings::set('app_config', [
    'features' => ['darkMode', 'notifications'],
    'limits' => ['users' => 1000, 'storage' => '10GB'],
    'integrations' => ['stripe' => true, 'paypal' => false]
]);

$config = Settings::get('app_config'); // Returns array
```

#### Select Options
```php
// Create setting with predefined options
Preference::create([
    'key' => 'notification_frequency',
    'default_value' => 'daily',
    'data_type' => 'select',
    'options' => json_encode(['hourly', 'daily', 'weekly', 'never'])
]);
```

### Bulk Operations

```php
// Set multiple settings at once
$bulkSettings = [
    'app_name' => 'My Application',
    'maintenance_mode' => false,
    'max_file_size' => 2048,
    'allowed_domains' => ['example.com', 'app.com']
];

foreach ($bulkSettings as $key => $value) {
    Settings::setWithAutoCreate($key, $value);
}
```

### Integration Examples

#### Laravel Livewire
```php
class SettingsComponent extends Component {
    public $theme;
    public $notifications;
    
    public function mount() {
        $userId = auth()->id();
        $this->theme = Settings::get('theme', $userId);
        $this->notifications = Settings::get('email_notifications', $userId);
    }
    
    public function save() {
        $userId = auth()->id();
        Settings::set('theme', $this->theme, $userId);
        Settings::set('email_notifications', $this->notifications, $userId);
        
        $this->emit('settingsSaved');
    }
}
```

#### Vue.js Frontend
```javascript
// API integration example
export class SettingsAPI {
    async getUserSettings(userId) {
        const response = await fetch(`/api/settings-kit/user?user_id=${userId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        return response.json();
    }
    
    async updateSetting(key, value, userId = null) {
        const endpoint = userId ? `/api/settings-kit/user/${key}` : `/api/settings-kit/global/${key}`;
        const body = userId ? { value, user_id: userId } : { value };
        
        return fetch(endpoint, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(body)
        });
    }
}
```

## 🔧 Configuration

### Environment Variables

The package can be configured via your `.env` file:

```env
# API Configuration
SETTINGS_KIT_API_ENABLED=true
SETTINGS_KIT_API_DISABLE_AUTH_DEV=false
SETTINGS_KIT_API_AUTH=token
SETTINGS_KIT_API_TOKEN=your-secure-api-token
SETTINGS_KIT_API_AUTO_CREATE=false

# Caching
SETTINGS_KIT_CACHE_ENABLED=true
SETTINGS_KIT_CACHE_TTL=3600

# Routes
SETTINGS_KIT_ROUTES_PREFIX=api/settings-kit
SETTINGS_KIT_ROUTES_MIDDLEWARE=api,auth:sanctum

# User Model
SETTINGS_KIT_USER_MODEL=App\Models\User
```

### Configuration File

Publish the configuration file to customize advanced settings:

```bash
php artisan vendor:publish --tag=settings-kit-config
```

This creates `config/settings-kit.php` with the following options:

```php
return [
    'api' => [
        'enabled' => env('SETTINGS_KIT_API_ENABLED', true),
        'auth' => env('SETTINGS_KIT_API_AUTH', 'token'),
        'token' => env('SETTINGS_KIT_API_TOKEN', null),
        'auto_create' => env('SETTINGS_KIT_API_AUTO_CREATE', false),
        'disable_auth_dev' => env('SETTINGS_KIT_API_DISABLE_AUTH_DEV', false),
    ],
    
    'cache' => [
        'enabled' => env('SETTINGS_KIT_CACHE_ENABLED', true),
        'ttl' => env('SETTINGS_KIT_CACHE_TTL', 3600),
        'prefix' => 'settings_kit',
    ],
    
    'routes' => [
        'prefix' => env('SETTINGS_KIT_ROUTES_PREFIX', 'api/settings-kit'),
        'middleware' => ['api', 'auth:sanctum'],
    ],
    
    'models' => [
        'user' => env('SETTINGS_KIT_USER_MODEL', 'App\Models\User'),
    ],
];
```

### Authentication Options

#### 1. Token Authentication (Recommended for APIs)
```env
SETTINGS_KIT_API_AUTH=token
SETTINGS_KIT_API_TOKEN=your-secure-token
```

#### 2. Sanctum Authentication (For SPAs)
```env
SETTINGS_KIT_API_AUTH=sanctum
```

#### 3. Passport Authentication (For OAuth2)
```env
SETTINGS_KIT_API_AUTH=passport
```

#### 4. Development Bypass (Local/Testing Only)
```env
APP_ENV=local
SETTINGS_KIT_API_DISABLE_AUTH_DEV=true
```

### Custom Middleware

You can customize the middleware stack:

```php
// In config/settings-kit.php
'routes' => [
    'middleware' => ['api', 'auth:sanctum', 'custom.middleware'],
],
```

### Caching Configuration

```env
# Redis caching (recommended for production)
CACHE_DRIVER=redis
SETTINGS_KIT_CACHE_ENABLED=true
SETTINGS_KIT_CACHE_TTL=3600  # 1 hour

# File caching (development)
CACHE_DRIVER=file
SETTINGS_KIT_CACHE_ENABLED=true
SETTINGS_KIT_CACHE_TTL=1800  # 30 minutes

# Disable caching (not recommended)
SETTINGS_KIT_CACHE_ENABLED=false
```

## 🔧 Troubleshooting

### Common Issues

#### 🔐 Authentication Problems

**Symptom**: Getting 401/403 errors when accessing API endpoints

**Solutions**:
```env
# For development/testing (bypass authentication)
APP_ENV=local
SETTINGS_KIT_API_DISABLE_AUTH_DEV=true

# For production (proper token auth)
SETTINGS_KIT_API_ENABLED=true
SETTINGS_KIT_API_AUTH=token
SETTINGS_KIT_API_TOKEN=your-secure-token

# For Sanctum authentication
SETTINGS_KIT_API_AUTH=sanctum
```

#### 💾 Settings Not Persisting

**Symptom**: Settings appear to save but revert after refresh

**Debugging Steps**:
```bash
# 1. Check database connection
php artisan tinker
>>> \Metalinked\LaravelSettingsKit\Models\Preference::count()

# 2. Verify migrations
php artisan migrate:status

# 3. Clear cache
php artisan cache:clear
php artisan config:clear

# 4. Check file permissions (if using file cache)
ls -la storage/framework/cache/
```

**Common Causes**:
- Database migration not run
- Cache driver misconfiguration
- File permission issues
- Wrong user model configuration

#### 🤖 Auto-Creation Not Working

**Symptom**: New settings aren't created automatically

**Check Configuration**:
```env
# Global auto-creation (affects all API requests)
SETTINGS_KIT_API_AUTO_CREATE=true
```

**Manual Auto-Creation**:
```php
// Use auto-create methods
Settings::setWithAutoCreate('new_setting', 'value');

// Or with parameter
Settings::set('new_setting', 'value', $userId, true);
```

#### 🐌 Performance Issues

**Symptom**: Slow settings retrieval or high database queries

**Optimization**:
```env
# Enable caching
SETTINGS_KIT_CACHE_ENABLED=true
SETTINGS_KIT_CACHE_TTL=3600

# Use Redis for better performance
CACHE_DRIVER=redis
```

**Code Optimization**:
```php
// Avoid N+1 queries - batch load settings
$userSettings = collect(['theme', 'language', 'notifications'])
    ->mapWithKeys(fn($key) => [$key => Settings::get($key, $userId)]);
```

#### 🌍 Multilingual Issues

**Symptom**: Translations not loading or fallback not working

**Debugging**:
```php
// Check available locales
Settings::addTranslations('setting_key', [
    'en' => ['label' => 'English Label'],
    'es' => ['label' => 'Spanish Label'],
]);

// Test specific locale
Settings::label('setting_key', 'es');
```

#### 🔄 Migration Problems

**Symptom**: Migration fails or foreign key constraints

**Solutions**:
```bash
# Check migration order
php artisan migrate:status

# Rollback and re-run if needed
php artisan migrate:rollback --step=3
php artisan migrate

# Fix foreign key issues
php artisan migrate:fresh --seed
```

### Environment-Specific Issues

#### Development Environment
```env
APP_ENV=local
APP_DEBUG=true
SETTINGS_KIT_API_DISABLE_AUTH_DEV=true
CACHE_DRIVER=file
```

#### Production Environment
```env
APP_ENV=production
APP_DEBUG=false
SETTINGS_KIT_API_DISABLE_AUTH_DEV=false
SETTINGS_KIT_API_TOKEN=secure-production-token
CACHE_DRIVER=redis
```

### Debug Commands

```bash
# Check package status
php artisan route:list | grep settings-kit

# Test API endpoints
curl -H "Authorization: Bearer your-token" \
     http://localhost/api/settings-kit

# Verify database structure
php artisan tinker
>>> Schema::hasTable('preferences')
>>> Schema::hasTable('preference_contents')
>>> Schema::hasTable('user_preferences')
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
