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
$user->settings()->fill($request->input('settings', []));  // save a whole form
$user->settings()->panel();                     // everything a preferences screen needs
```

## Contents

- [Why this package](#why-this-package) and [when to use it](#when-to-use-it)
- [Installation](#installation)
- [Defining settings](#defining-settings)
- [Reading and writing](#reading-and-writing)
- [Per-user (or per-model) settings](#per-user-or-per-model-settings)
- [Forms](#forms)
- [Validation and error messages](#validation-and-error-messages)
- [Laravel configuration](#laravel-configuration)
- [Settings screens](#settings-screens)
- [Encrypted settings](#encrypted-settings)
- [JSON endpoints](#json-endpoints)
- [Events](#events), [strict mode](#strict-mode), [Artisan commands](#artisan-commands) and [caching](#caching)

## Why this package

- **Definitions live in code.** Types, defaults and who may override what are versioned with your app. There are no seeders, and environments stay in sync. The database only stores the values you save.
- **Overrides for any model.** Users, teams, tenants. Each one reads its own value first, then the global value, then the default. UUID and ULID keys work.
- **Typed and validated.** `string`, `boolean`, `integer`, `float`, `array`, `select` and PHP backed `enum`s, plus any Laravel validation rule.
- **Made for forms.** `fill()` saves a submitted form, `rules()` validates it next to your other fields, and `panel()` gives you the data to render it.
- **Few queries.** All values of a scope are loaded with one query, cached together and memoised for the request. Ten settings for a user cost at most two queries, and none once cached.
- **Encrypted settings** for API keys and other secrets, write-only in panels and endpoints.
- **Optional JSON endpoints** that use your own auth middleware and Gates.

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

## Installation

Requires PHP 8.2+ and Laravel 11, 12 or 13.

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

| Option | Meaning |
|---|---|
| `type` | `string`, `boolean`, `integer`, `float`, `array`, `select` or `enum`. Inferred from `default`, `options` or `enum` when omitted. |
| `default` | Value used while nothing is stored. |
| `options` | Allowed values of a `select`, as a list or as `value => label`. |
| `enum` | Backed enum class of an `enum`. |
| `overridable` | `true`, or a list of model classes that may store their own value. |
| `rules` | Extra Laravel validation rules, applied after the type check. |
| `nullable` | Whether `null` is a valid value. |
| `encrypted` | Store the value encrypted. See [Encrypted settings](#encrypted-settings). |
| `config` | Laravel config key that `applyConfig()` writes the stored value to. See [Laravel configuration](#laravel-configuration). |
| `group` | Group of the setting in panels and commands. |
| `label`, `description` | Texts or translation keys for settings screens. |
| `meta` | Any extra data for your UI, returned by `panel()`. |

Unknown options throw an exception, so typos are caught early.

### With the fluent builder

Useful for enums and for packages or modules that register their own settings. Call it from a service provider's `boot()`:

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

Settings::define('appearance.theme')->enum(Theme::class)->default(Theme::Light)->overridable();
Settings::define('locale')->select(['en' => 'English', 'ca' => 'Català'])->default('en')->overridable(User::class);
Settings::define('items_per_page')->integer()->default(20)->rules('min:5|max:100');
Settings::define('mail.from_address')->string()->nullable()->config('mail.from.address');
Settings::define('dashboard.widgets')->array()->default(['sales', 'traffic'])->overridable()->meta(['icon' => 'layout']);
```

### Keys and groups

Keys can use dots to organise them. The first segment is the default group: `appearance.theme` belongs to `appearance`, and keys without a dot belong to `general`. Use `group` to choose another.

## Reading and writing

```php
use Metalinked\LaravelSettingsKit\Facades\Settings;

Settings::get('site_name');                 // typed value, or the default
Settings::set('site_name', 'Acme');         // validated, then stored
Settings::set(['site_name' => 'Acme', 'maintenance_mode' => true]); // all or nothing
Settings::fill($request->input('settings', []));  // save a form, see "Forms"
Settings::enabled('maintenance_mode');      // bool shortcut
Settings::many(['site_name', 'items_per_page']);
Settings::has('site_name');                 // is a value stored?
Settings::forget('site_name');              // remove it, back to the default
Settings::reset();                          // remove every global value
Settings::all();                            // every setting, resolved
Settings::stored();                         // only the stored values

settings('site_name');                      // helper, same as Settings::get()
settings(['site_name' => 'Acme']);          // helper, same as Settings::set()
```

In Blade:

```blade
<title>@setting('site_name')</title>
```

### Stored values and defaults

A setting uses the default from its definition until a value is saved. Changing the default in code reaches every installation that has not saved one.

Saving stores the value even when it equals the default. From then on `has()` returns `true`, and a later change of the default does not affect it. This also applies to a form that submits every field. To make a setting follow the default again, call `forget()`.

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

Only settings marked `overridable` can be overridden. Calling `set()` with any other key on a model throws `SettingNotOverridableException`. Overrides are deleted when the model is deleted; soft-deleted models keep them until they are force-deleted.

Changing a global value immediately affects every model that has not overridden it.

## Forms

`fill()` saves a submitted form in one call:

```php
Settings::fill($request->input('settings', []));      // global values
$user->settings()->fill($request->input('settings', []));  // a user's overrides
```

The array maps setting keys to values. In HTML, name each input after its key, as in `name="settings[mail.port]"`. Because of the dot, `old('settings.mail.port')` returns `null`: use `old('settings')['mail.port'] ?? $current`. [docs/settings-screens.md](docs/settings-screens.md) has a complete form.

An empty field is `null` or `""`, and it is handled according to the definition:

| Setting | What an empty field does |
|---|---|
| `encrypted` | Keeps the stored value. |
| `nullable` | Removes the stored value, so it falls back to the global value or the default. |
| Any other | A `string` accepts `""`. `null`, and `""` for every other type, are reported as a missing value. |

Keys that are not in the array are left alone. An unchecked checkbox sends nothing, so add a hidden field with `0` before it.

Everything is checked before anything is written, and the writes and removals run in one database transaction. Errors are thrown as a `ValidationException` keyed by setting. Keys that are not defined, or that the model may not override, are errors too, even outside strict mode, so request input can be passed in. If the input is not an array, the error is keyed `settings`. `fill()` accepts any defined setting of the scope: if a form must only change some of them, pick those keys first.

`set()` differs on purpose: it stores exactly what you give it, including `null` and `""`.

### Validating with the rest of a request

When the form has its own field names, or other fields besides settings, use `rules()`. It takes `field name => setting key` and returns Laravel validation rules:

```php
$data = $request->validate([
    'title' => ['required'],
    ...Settings::rules([
        'conversion' => 'points.conversion',
        'reply_to' => 'mail.reply_to',
    ]),
]);

Settings::fill($data, [
    'conversion' => 'points.conversion',
    'reply_to' => 'mail.reply_to',
]);
```

With the same `field => key` map as a second argument, `fill()` reads the values by field name and ignores the rest of the data, such as `title`. A field that is missing from the data is left alone.

- A field passes when `fill()` would accept it, with the same type conversion and the setting's `rules`. Empty fields are allowed for `encrypted` and `nullable` settings.
- Errors are keyed by the field name, so `@error('conversion')` works. The message names the setting by its label.
- The custom messages and attribute names of `validate()` apply, such as `['conversion.min' => 'Too low.']` and `['conversion' => 'rate']`. A type error takes the custom message of the matching rule (`boolean`, `integer`, `numeric`, `string`, `array`, `in` or `required`).
- A missing field is rejected unless the setting is `nullable` or `encrypted`. For optional fields, put `sometimes` first: `['sometimes', ...Settings::rules(['conversion' => 'points.conversion'])['conversion']]`.
- Field names follow Laravel's dot notation, so avoid literal dots in them.
- `$user->settings()->rules([...])` also checks that the user may override each setting.

Nothing is saved by validating.

An empty field keeps an encrypted value. To let a form delete one, call `forget()` after `fill()`, with both inside a `DB::transaction()`.

## Validation and error messages

Every write is checked against the setting's type, then against its `rules`. Input is converted where it makes sense: `"1"`, `"true"` and `"on"` become `true`, and `"42"` becomes `42`, so form and JSON input can be passed straight in.

```php
try {
    $user->settings()->set($request->input('settings'));
} catch (ValidationException $e) {
    // $e->errors() is keyed by setting:
    // ['appearance.theme' => ['The Theme setting must be one of: Light, Dark, Auto.']]
}
```

When several values are set at once, they are all validated before anything is written.

Errors are keyed by setting. Their messages use the setting's label and, for a `select` or `enum`, the labels of its options.

Messages from your `rules` use Laravel's validation translations. The package's own messages (type errors and endpoint errors) come in English, Catalan and Spanish, follow the app locale and fall back to the fallback locale, then to English. To change them or add a language, publish them to `lang/vendor/settings-kit`:

```bash
php artisan vendor:publish --tag=settings-kit-lang
```

Exceptions that point to a programming mistake, such as an undefined key in strict mode, are always in English.

## Laravel configuration

A setting can replace a value of Laravel's configuration, for example to let an administrator change the sender of your emails. Name the config key in the definition:

```php
'mail.from_address' => [
    'type' => 'string',
    'nullable' => true,
    'rules' => ['email'],
    'config' => 'mail.from.address',
],
```

Nothing changes until you call `applyConfig()`:

```php
Settings::applyConfig();                        // every setting with a `config` key
Settings::applyConfig(['mail.from_address']);   // only these
```

Only stored values are written. A setting with nothing stored leaves the configuration as your files and `.env` define it, and if an earlier call had overwritten it, the original value is restored. For a `nullable` setting, a stored `null` or `""` counts as nothing stored.

### When to call it

Call it right before the service that reads the configuration is built. For mail, in a service provider:

```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

public function boot(): void {
    // Runs when the mail manager is built, or now if it already is.
    $this->callAfterResolving('mail.manager', function () {
        // The table does not exist yet on a fresh install.
        rescue(fn () => Settings::applyConfig(['mail.from_address']));
    });

    // Queue workers live for many jobs, and Laravel keeps the mailers it built.
    Queue::before(function () {
        Settings::applyConfig(['mail.from_address']);
        Mail::forgetMailers();
    });
}
```

Avoid calling it directly in `boot()`:

- It queries the database on every request and command, which fails on a fresh install before `php artisan migrate`.
- `php artisan config:cache` boots the application, so the stored values, secrets included, would be written to the cached configuration file.

For tenant or user values, call it on the model once you know which one it is: `$team->settings()->applyConfig()`. It writes the model's own value, or else the stored global one.

## Settings screens

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

### Translating labels

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

## Encrypted settings

Settings declared with `encrypted` are stored encrypted with your `APP_KEY`. They are meant for API keys and other secrets, so they are treated as **write-only** outside your own code:

- `get()` returns the decrypted value, as usual.
- `panel()` and the JSON endpoints always return `value` and `default` as `null` for them. Use `filled` to show whether a value is configured.
- `fill()` and the JSON endpoints ignore `null` or `""` for an encrypted setting, so a form sent back unchanged keeps the stored secret. To remove it, call `forget()`, or `DELETE /settings/me/{key}` for a user's secret.
- `settings:list` shows `(encrypted)` instead of the value.
- `settings:export` skips them unless you pass `--with-secrets`. The resulting file then contains them in plain text.

`SettingUpdated` and `SettingReset` events do carry the decrypted value, so do not log them blindly. If you rotate `APP_KEY`, keep the old key in `APP_PREVIOUS_KEYS`: values that can no longer be decrypted fall back to their default.

## JSON endpoints

They are optional. Register the routes inside your own group, so your auth middleware applies:

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

`PATCH` stores values as `set()` does, with one exception: empty values of encrypted settings are ignored. Sending `null` for any other nullable setting stores `null`; use `DELETE` to remove a user's override.

Global endpoints answer 403 until you define the ability (the name is configurable in `http.global_ability`):

```php
Gate::define('manage-settings', fn (User $user) => $user->is_admin);
```

The write endpoints (`PATCH` and `DELETE`) are rate limited with the `throttle` middleware, 60 requests per minute by default. A single `PATCH` accepts at most 100 settings, and each value may be at most 10 KB once encoded as JSON; anything bigger returns a 422 before anything is written. Change these in `http.throttle` (`'attempts,minutes'`, or `null` to turn it off), `http.max_keys` and `http.max_value_bytes`.

## Events

- `SettingUpdated` (`key`, `value`, `previous`, `scope`, `settable`) is dispatched after a value is stored.
- `SettingReset` (`key`, `previous`, `scope`, `settable`) is dispatched after a stored value is removed.

Both are dispatched once the surrounding database transaction commits. `settable` is the model for model scopes, and `null` for global ones or when the scope was given as a type and id.

## Strict mode

By default, keys that were never defined still work with `get()` and `set()` as a simple untyped store, and `get($key, $default)` returns `$default` when nothing is stored. Set `SETTINGS_KIT_STRICT=true` to turn any undefined key into an `UndefinedSettingException`, which catches typos. The `$default` argument only applies to undefined keys; defined keys always use their declared default.

`fill()`, `rules()`, `applyConfig()` and the JSON endpoints only accept defined keys in both modes.

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

### Transactions

Wrap writes to several scopes in a normal database transaction when they must succeed or fail together:

```php
DB::transaction(function () use ($user) {
    Settings::set('site_name', 'Renamed');
    $user->settings()->set('appearance.theme', 'dark');
});
```

A scope written inside a transaction is read straight from the database until the transaction ends, so values that are rolled back never reach the cache. Its cache entry is cleared again after the commit, in case another request cached the old rows in the meantime. `SettingUpdated` and `SettingReset` are dispatched after the commit.

### Size and cleanup

A user's overrides live in one cache entry per user, and the global values in one more. A request reads the global entry plus the entry of each model it asks about, so the cost does not grow with the number of users, only with the number of settings one user has overridden.

`settings:clear-cache` and `Settings::flushCache()` do not delete the old entries: they change the version in the cache key, and the old entries are never read again. With the default `ttl` of `null` on a store that does not evict (`file`, `database`), they stay until you clear that store. Set `cache.ttl` to a number of seconds, or use Redis or Memcached, which evict entries that are no longer used.

## Upgrading from 1.x

Version 2 is a redesign. See [UPGRADE.md](UPGRADE.md) for the automated migration command and the API changes.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Run the tests with `composer test`. Report security problems as described in [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
