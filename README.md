# Laravel Settings Kit

[![Tests](https://img.shields.io/github/actions/workflow/status/metalinked/laravel-settings-kit/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/metalinked/laravel-settings-kit/actions/workflows/tests.yml)
[![GitHub Release](https://img.shields.io/github/v/release/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/metalinked/laravel-settings-kit?style=flat-square)](https://packagist.org/packages/metalinked/laravel-settings-kit)
[![License](https://img.shields.io/packagist/l/metalinked/laravel-settings-kit?style=flat-square)](https://github.com/metalinked/laravel-settings-kit/blob/main/LICENSE.md)

Typed settings for Laravel, **defined in code**, with global values and **per-user (or per-model) overrides**.

```php
// config/settings-kit.php
'settings' => [
    'site_name' => 'My App',
    'appearance.theme' => ['options' => ['light', 'dark', 'auto'], 'default' => 'light', 'overridable' => true],
],
```

```php
settings('site_name');                          // 'My App'
$user->settings()->get('appearance.theme');     // the user's choice, else the global value, else 'light'
$user->settings()->set('appearance.theme', 'dark');
$user->settings()->panel();                     // everything a preferences screen needs
```

## Why this package

- **Definitions live in code.** Types, defaults and who may override what are versioned with your app. No seeders, and environments stay in sync. The database only stores values that differ from the default.
- **Overrides for any model.** Users, teams, tenants… Each one reads its own value first, then the global value, then the default. UUID and ULID keys work.
- **Typed and validated.** `string`, `boolean`, `integer`, `float`, `array`, `select` and PHP backed `enum`s, plus any Laravel validation rule. Invalid input throws a regular `ValidationException`, keyed by setting.
- **Fast.** All values of a scope are loaded with one query, cached together and memoised for the request. Ten settings for a user cost at most two queries, and none once cached.
- **Ready for settings screens.** `panel()` returns labels, descriptions, options, current and default values and whether the value is overridden, translated through your normal language files.
- **Optional JSON endpoints** that plug into your own auth middleware and Gates.
- **Encrypted settings** for API keys and other secrets, write-only in panels and endpoints.

## When to use it

The package covers three kinds of values, all declared the same way:

| Kind | Examples | How |
|---|---|---|
| Application settings | Site name, maintenance mode, global limits, API keys | Global values: `Settings::get()` / `Settings::set()` |
| Personal preferences | Theme, language, items per page, notifications | `overridable` settings, read and written on the user |
| Team or tenant settings | Plan limits, branding, a team's default locale | `overridable` settings scoped to another model (`overridable(Team::class)`) |

A model inherits the global value until it stores its own, so the same key can be an app-wide default and a personal choice at once.

It is **not** the right tool when:

- Your customers need to create **new fields at runtime**, without a deploy. Definitions live in code on purpose; a dynamic form builder needs its own tables.
- The value is really **domain data**: something you query, filter or join on (`where('plan', 'pro')`), that has relations, or that grows per record. Use a column or a table.
- It is **infrastructure configuration** that belongs in `.env` and `config/` (database credentials, queue drivers): those are needed before the database is available.

## Requirements

PHP 8.2+ and Laravel 11, 12 or 13.

## Installation

```bash
composer require metalinked/laravel-settings-kit
php artisan migrate
```

Publish the config file to declare your settings:

```bash
php artisan vendor:publish --tag=settings-kit-config
```

## Defining settings

### In the config file

```php
// config/settings-kit.php
'settings' => [
    // Short form: the type is inferred from the default.
    'site_name' => 'My App',
    'maintenance_mode' => false,
    'items_per_page' => 20,

    // Full form.
    'appearance.theme' => [
        'type' => 'select',
        'options' => ['light', 'dark', 'auto'],
        'default' => 'light',
        'overridable' => true,
    ],
    'billing.tax_rate' => [
        'type' => 'float',
        'default' => 21.0,
        'rules' => ['min:0', 'max:100'],
    ],
    'services.mailgun_key' => [
        'type' => 'string',
        'nullable' => true,
        'encrypted' => true,
    ],
],
```

Available options: `type`, `default`, `options` (select), `enum` (enum class), `overridable` (`true` or a list of model classes), `group`, `label`, `description`, `rules`, `nullable`, `encrypted`, `meta`. Unknown options throw an exception, so typos are caught early.

### With the fluent builder

Useful for enums and for packages or modules that register their own settings. Call it from a service provider's `boot()`:

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

Settings::define('appearance.theme')->enum(Theme::class)->default(Theme::Light)->overridable();
Settings::define('locale')->select(['en' => 'English', 'ca' => 'Català'])->default('en')->overridable(User::class);
Settings::define('items_per_page')->integer()->default(20)->rules('min:5|max:100');
Settings::define('dashboard.widgets')->array()->default(['sales', 'traffic'])->overridable()->meta(['icon' => 'layout']);
```

### Keys and groups

Keys can use dots to organise them. The first segment is the default group: `appearance.theme` belongs to `appearance`, and keys without a dot belong to `general`. Use `group()` to choose another.

## Global settings

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

Settings::get('site_name');                 // typed value, or the default
Settings::set('site_name', 'Acme');         // validated, then stored
Settings::set(['site_name' => 'Acme', 'maintenance_mode' => true]); // all or nothing
Settings::enabled('maintenance_mode');      // bool shortcut
Settings::many(['site_name', 'items_per_page']);
Settings::has('site_name');                 // is a value stored (not just the default)?
Settings::forget('site_name');              // back to the default
Settings::all();                            // every setting, resolved
Settings::stored();                         // only the stored values

settings('site_name');                      // helper, same as Settings::get()
settings(['site_name' => 'Acme']);          // helper, same as Settings::set()
```

In Blade:

```blade
<title>@setting('site_name')</title>
```

## Per-user (or per-model) settings

Add the trait to any model:

```php
use Metalinked\LaravelSettingsKit\Concerns\HasSettings;

class User extends Authenticatable {
    use HasSettings;
}
```

```php
$user->settings()->get('appearance.theme');   // own value → global value → default
$user->settings()->set('appearance.theme', 'dark');
$user->settings()->set(['appearance.theme' => 'dark', 'locale' => 'ca']);
$user->settings()->has('appearance.theme');   // does the user override it?
$user->settings()->forget('appearance.theme');
$user->settings()->reset();                   // remove all of the user's overrides
$user->settings()->stored();                  // only the user's overrides
$user->settings()->all();                     // every setting, as this user sees it
```

Without the trait, or if your model already has a `settings` attribute, use `Settings::for($model)`. You can also pass a morph type and id, which avoids loading the model:

```php
Settings::for($team)->get('appearance.theme');
Settings::for('user', $userId)->get('appearance.theme');
```

Only settings marked `overridable` can be overridden. Setting any other key on a model throws `SettingNotOverridableException`. Overrides are deleted when the model is deleted; soft-deleted models keep them until they are force-deleted.

Changing a global value immediately affects every model that has not overridden it.

## Building a settings screen

`panel()` returns everything a form needs, translated to the current locale:

```php
$user->settings()->panel('appearance');
```

```php
[
    [
        'key' => 'appearance.theme',
        'group' => 'appearance',
        'type' => 'select',
        'label' => 'Theme',
        'description' => 'Colour scheme of the interface',
        'value' => 'dark',          // what the user gets now
        'default' => 'light',       // what they get if they reset it
        'filled' => true,           // whether there is any value (useful for secrets)
        'overridden' => true,       // whether this scope stores its own value
        'encrypted' => false,
        'nullable' => false,
        'options' => [['value' => 'light', 'label' => 'Light'], ['value' => 'dark', 'label' => 'Dark']],
        'meta' => [],
    ],
]
```

For a model, the panel only lists settings that model may override. `Settings::panel()` lists every setting with its global value.

The package does not ship any UI: `panel()` is the data, and your app renders it with whatever it already uses. See [docs/settings-screens.md](docs/settings-screens.md) for complete examples with Blade, Livewire and a JavaScript SPA.

### Translations

Labels, descriptions and option labels come from `lang/{locale}/settings.php`. Missing entries fall back to a readable version of the key (`font_size` becomes "Font Size").

```php
// lang/ca/settings.php
return [
    'appearance' => [
        'theme' => [
            'label' => 'Tema',
            'description' => 'Esquema de colors de la interfície',
            'options' => ['light' => 'Clar', 'dark' => 'Fosc'],
        ],
    ],
];
```

Enum options can also provide a `label()` method on the enum.

## Validation

Every write is checked against the setting's type, then against its `rules`. Input is coerced where it makes sense: `"1"`, `"true"` and `"on"` become `true`, and `"42"` becomes `42`. This means form or JSON input can be passed straight in:

```php
try {
    $user->settings()->set($request->input('settings'));
} catch (ValidationException $e) {
    // $e->errors() is keyed by setting: ['appearance.theme' => ['The appearance.theme setting must be one of: light, dark.']]
}
```

When several values are set at once, they are all validated before anything is written.

## Encrypted settings

Settings declared with `encrypted` are stored encrypted with your `APP_KEY`. They are meant for API keys and other secrets, so they are treated as **write-only** outside your own code:

- `get()` returns the decrypted value, as usual.
- `panel()` and the JSON endpoints always return `value` and `default` as `null` for them. Use `filled` to show whether a value is configured, and send a new value only when the user types one.
- The JSON endpoints ignore `null` or `""` for an encrypted setting, so a form sent back unchanged keeps the stored secret. To remove a user's secret, use `DELETE /settings/me/{key}`; global secrets are removed in code with `Settings::forget()`.
- `settings:list` shows `(encrypted)` instead of the value.
- `settings:export` skips them unless you pass `--with-secrets`. The resulting file then contains them in plain text.

`SettingUpdated` and `SettingReset` events do carry the decrypted value, so do not log them blindly. If you rotate `APP_KEY`, keep the old key in `APP_PREVIOUS_KEYS`: values that can no longer be decrypted fall back to their default.

## JSON endpoints (optional)

Register the routes inside your own group, so your auth middleware applies:

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Settings::routes();             // prefix defaults to "settings"
});
```

| Method | URI | Action |
|---|---|---|
| `GET` | `/settings?group=…` | Global panel. Requires the `manage-settings` ability. |
| `PATCH` | `/settings` | Update global values. Requires the `manage-settings` ability. |
| `GET` | `/settings/me?group=…` | The authenticated user's panel. |
| `PATCH` | `/settings/me` | Update the user's overrides. |
| `DELETE` | `/settings/me/{key?}` | Reset one override, or all of them. |

Request bodies look like `{"values": {"appearance.theme": "dark"}}`. Every response returns the updated panel as `{"data": [...]}`. Unknown or non-overridable keys and invalid values return a standard 422.

Global endpoints answer 403 until you define the ability (the name is configurable in `http.global_ability`):

```php
Gate::define('manage-settings', fn (User $user) => $user->is_admin);
```

## Events

- `SettingUpdated` (`key`, `value`, `previous`, `scope`, `settable`) is dispatched after a value is stored.
- `SettingReset` (`key`, `previous`, `scope`, `settable`) is dispatched after a stored value is removed.

Both are dispatched once the surrounding database transaction commits. `settable` is the model for model scopes and `null` for global ones.

## Strict mode

By default, keys that were never defined still work as a simple untyped store, and `get($key, $default)` returns `$default` when nothing is stored. Set `SETTINGS_KIT_STRICT=true` to turn any undefined key into an `UndefinedSettingException`, which catches typos. The `$default` argument only applies to undefined keys; defined keys always use their declared default.

## Artisan commands

| Command | Description |
|---|---|
| `settings:list {--group=}` | Definitions with their default, global value and number of overrides. |
| `settings:export {path?} {--all} {--with-secrets}` | Export global values as JSON (stored values only, or all with `--all`). Encrypted settings are skipped unless `--with-secrets`. |
| `settings:import {path} {--dry-run}` | Validate and import global values from JSON. |
| `settings:prune {--force}` | Delete values of settings that are no longer defined, and overrides that are no longer allowed. |
| `settings:clear-cache` | Invalidate every cached value. |
| `settings:upgrade-from-v1` | Convert a 1.x installation. See [UPGRADE.md](UPGRADE.md). |

## Caching

Values are cached per scope (global, or one model) in your default cache store, forever by default, and invalidated on every write. This works with every cache driver; tags are not required. Configure it in `cache` (`enabled`, `store`, `ttl`, `prefix`). The package is safe to use with Octane and queue workers: values are memoised per request and per job, never across them.

## Testing

```bash
composer test
```

## Upgrading from 1.x

Version 2 is a redesign. See [UPGRADE.md](UPGRADE.md) for the automated migration command and the API changes.

## License

MIT. See [LICENSE.md](LICENSE.md).
