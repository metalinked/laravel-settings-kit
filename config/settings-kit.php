<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | These options control how settings are cached to improve performance.
    |
    */
    'cache' => [
        'enabled' => env('SETTINGS_KIT_CACHE_ENABLED', true),
        'ttl' => env('SETTINGS_KIT_CACHE_TTL', 3600), // 1 hour in seconds
        'prefix' => env('SETTINGS_KIT_CACHE_PREFIX', 'settings_kit'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */
    'tables' => [
        'preferences' => 'preferences',
        'preference_contents' => 'preference_contents',
        'user_preferences' => 'user_preferences',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale to use when retrieving translated content.
    |
    */
    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale to use when a translation is not available.
    |
    */
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model to use for user-specific settings.
    |
    */
    'user_model' => 'App\Models\User',

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the REST API endpoints.
    | 
    | Development Setup:
    | - Set SETTINGS_KIT_API_ENABLED=true
    | - Set SETTINGS_KIT_API_DISABLE_AUTH_DEV=true (bypasses auth in local/testing)
    | - No token/auth setup needed for development
    | 
    | Production Setup:
    | - Set SETTINGS_KIT_API_ENABLED=true
    | - Set SETTINGS_KIT_API_DISABLE_AUTH_DEV=false (or remove)
    | - Configure appropriate auth_mode and credentials
    |
    */
    'api' => [
        'enabled' => env('SETTINGS_KIT_API_ENABLED', false),
        'prefix' => env('SETTINGS_KIT_API_PREFIX', 'api/settings-kit'),
        'auth_mode' => env('SETTINGS_KIT_API_AUTH', 'token'), // 'token', 'sanctum', 'passport'
        'token' => env('SETTINGS_KIT_API_TOKEN'),
        'disable_auth_in_development' => env('SETTINGS_KIT_API_DISABLE_AUTH_DEV', true),
        'middleware' => ['api'], // Base middleware for all API routes
        'auto_create_missing_settings' => env('SETTINGS_KIT_API_AUTO_CREATE', false), // Auto-create settings via API
    ],
];
