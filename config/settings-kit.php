<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Setting definitions
    |--------------------------------------------------------------------------
    |
    | Declare your settings here (or with Settings::define() in a service
    | provider). Definitions live in code; the database only stores values
    | that differ from the default.
    |
    | Short form — the type is inferred from the default:
    |     'site_name' => 'My App',
    |
    | Full form:
    |     'appearance.theme' => [
    |         'type' => 'select',            // string, boolean, integer, float, array, select, enum
    |         'default' => 'light',
    |         'options' => ['light', 'dark', 'auto'],
    |         'overridable' => true,         // users (or any model) may override it
    |         'rules' => ['required'],       // extra Laravel validation rules
    |     ],
    |
    */
    'settings' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict mode
    |--------------------------------------------------------------------------
    |
    | When enabled, reading or writing a key that has not been defined throws
    | an UndefinedSettingException. When disabled, undefined keys behave like
    | a simple untyped key-value store.
    |
    */
    'strict' => env('SETTINGS_KIT_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Database table
    |--------------------------------------------------------------------------
    */
    'table' => 'settings_kit_values',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | All stored values of a scope (global, or one model) are loaded with a
    | single query and cached together. A null TTL caches forever; entries
    | are invalidated on every write.
    |
    */
    'cache' => [
        'enabled' => env('SETTINGS_KIT_CACHE_ENABLED', true),
        'store' => env('SETTINGS_KIT_CACHE_STORE'),
        'ttl' => null,
        'prefix' => 'settings_kit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Translations
    |--------------------------------------------------------------------------
    |
    | Labels, descriptions and option labels are looked up in this language
    | file, e.g. lang/en/settings.php:
    |
    |     'appearance' => ['theme' => [
    |         'label' => 'Theme',
    |         'description' => 'Colour scheme of the interface',
    |         'options' => ['dark' => 'Dark'],
    |     ]],
    |
    | Missing translations fall back to a readable version of the key.
    |
    */
    'translations' => 'settings',

    /*
    |--------------------------------------------------------------------------
    | HTTP endpoints
    |--------------------------------------------------------------------------
    |
    | Only used when you register the routes with Settings::routes(). The
    | ability is checked with Gate::authorize() before reading or changing
    | global settings; define it in your AuthServiceProvider. Until you do,
    | global endpoints answer 403.
    |
    */
    'http' => [
        'global_ability' => 'manage-settings',
    ],
];
