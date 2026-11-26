# Changelog

## [2.3.0](https://github.com/metalinked/laravel-settings-kit/compare/v2.2.0...v2.3.0) (2026-11-26)

### Features

* a stored value that cannot be decrypted, or no longer fits its definition, logs a warning and dispatches `SettingUnreadable`, once per request or job for each scope and key. Neither carries the value. `log_unreadable` turns the warning off
* `settings:encrypt` encrypts the plain values of `encrypted` settings and encrypts the others again with the current `APP_KEY`. It decrypts only with `--decrypt`, and fails if some value cannot be read
* `settings:rename` moves the global value and every override of a setting to a new key
* `SettingUpdated` and `SettingReset` have an `encrypted` property, `true` for secrets
* each `panel()` entry, and so each JSON endpoint response, has the `rules` of the setting that are written as strings
* README: rules that depend on another field of a form

### Upgrading

* if stored values no longer fit their definitions, the log now shows a warning for them. Save or remove those values, run `settings:encrypt` if encryption is the cause, or set `log_unreadable` to `false`
* whoever can read a panel can now read the string rules, such as `exists:users,email` with its table name
* update tests or clients that expect the exact keys of a `panel()` entry

## [2.2.0](https://github.com/metalinked/laravel-settings-kit/compare/v2.1.0...v2.2.0) (2026-11-25)

### Features

* `fill()` saves a submitted form in one call: an empty field keeps an encrypted value and removes the stored value of a nullable setting. With a `field => key` map it reads the values by field name
* `rules()` returns validation rules for form fields that hold settings, and follows the custom messages and attribute names of the validator
* `applyConfig()` and the `config` definition option write stored values into Laravel's configuration
* the package's error messages are translated (English, Catalan and Spanish) and can be published with `--tag=settings-kit-lang`

### Bug Fixes

* values read inside a database transaction that may be rolled back are no longer cached
* boolean settings no longer accept an empty string as `false`

### Upgrading

* error messages follow the app locale. They name the setting by its label instead of its key, and list the labels of `select` and `enum` options instead of their values. Update tests or clients that compare the text
* `set()`, the JSON endpoints and `settings:import` now reject `""` for a boolean setting. A `null` or empty value of a mandatory setting is reported as required

## [2.1.0](https://github.com/metalinked/laravel-settings-kit/compare/v2.0.1...v2.1.0) (2026-11-23)

### Features

* the JSON endpoints limit writes: `PATCH` and `DELETE` go through the `throttle` middleware (60 requests per minute), a `PATCH` accepts at most 100 settings, and each value may be at most 10 KB encoded as JSON. Change them with `http.throttle`, `http.max_keys` and `http.max_value_bytes`; `http.throttle` set to `null` turns the rate limit off. The defaults also apply if your published config has no such keys
* README: writing to several scopes in one database transaction, and the cache entries that `settings:clear-cache` leaves behind on stores that do not evict (`file`, `database`)

### Upgrading

* if a client of the JSON endpoints sends more than 60 writes per minute, or a `PATCH` above the new limits, it now gets a 429 or a 422. Raise the limits or set `http.throttle` to `null` if that breaks something

### Tests and tooling

* tests for `settings:upgrade-from-v1` (dry run, orphan rows, existing files, type conversion), for cache invalidation around database transactions, and for the endpoint limits
* PHPStan runs at level 8 (it was 5), on PHP 8.2 and 8.4
* CI also runs the tests with the lowest supported dependencies (Laravel 11 on PHP 8.2, Laravel 13 on PHP 8.4)

## [2.0.1](https://github.com/metalinked/laravel-settings-kit/compare/v2.0.0...v2.0.1) (2026-10-10)

### Security

* encrypted settings are now write-only outside your own code: `panel()` and the JSON endpoints return `value` and `default` as `null` for them (they used to return the decrypted secret, including the inherited global secret as a model's `default`)
* the JSON endpoints ignore `null` or empty values for encrypted settings, so sending a panel back unchanged no longer wipes a stored secret
* `settings:export` skips encrypted settings unless `--with-secrets` is passed; `settings:list` shows `(encrypted)` instead of the value

### Features

* panel items include `filled` (whether there is any value) and `encrypted`
* [docs/settings-screens.md](docs/settings-screens.md): settings screens with Blade, Livewire and a JavaScript SPA
* README: when to use the package (application settings, personal preferences, team or tenant settings) and when not to

## [2.0.0](https://github.com/metalinked/laravel-settings-kit/compare/v1.2.2...v2.0.0) (2026-10-10)

Complete redesign. See [UPGRADE.md](UPGRADE.md) for the migration path and `php artisan settings:upgrade-from-v1`.

### Features

* settings are defined in code (config file or fluent `Settings::define()`); the database only stores values
* per-model overrides for any Eloquent model via polymorphic scopes and the `HasSettings` trait (integer, UUID and ULID keys)
* new types: `float`, `array`, PHP backed `enum`; Laravel validation rules per setting; encrypted settings
* `panel()` returns everything a settings screen needs, translated through regular language files
* one query per scope, cached per scope with version-based invalidation (any cache driver), request-scoped memo (Octane/queue safe)
* `settings()` helper, `SettingReset` event, events dispatched after commit with previous value
* optional JSON endpoints registered with `Settings::routes()`, authorised with your middleware and Gates
* new commands: `settings:prune`, `settings:upgrade-from-v1`; `settings:export`/`settings:import` validate values

### Breaking changes

* requires PHP 8.2+ and Laravel 11+
* `preferences`, `preference_contents` and `user_preferences` tables replaced by `settings_kit_values`
* `Settings::get($key, $userId)` replaced by `Settings::for($model)->get($key)`; the second argument of `get()` is now a default value
* 1.x REST API, token/Sanctum/Passport auth modes and Eloquent models removed

## [1.2.2](https://github.com/metalinked/laravel-settings-kit/compare/v1.2.1...v1.2.2) (2026-10-10)


### Bug Fixes

* declare nullable parameters explicitly to avoid PHP 8.4 deprecation notices
* API 500 responses no longer expose the exception message; the exception is reported instead

## [1.2.1](https://github.com/metalinked/laravel-settings-kit/compare/v1.2.0...v1.2.1) (2026-06-21)


### Miscellaneous Chores

* update package description to match README ([237a456](https://github.com/metalinked/laravel-settings-kit/commit/237a45642cccf7353a5f97815653a6791dd020d1))

## [1.2.0](https://github.com/metalinked/laravel-settings-kit/compare/v1.1.0...v1.2.0) (2026-06-21)


### Features

* major refactor — coherence fixes, batch API, Laravel 13 support ([4544b08](https://github.com/metalinked/laravel-settings-kit/commit/4544b087d05589e0a23c4994c2e64a9be87cb921))

## [1.1.0](https://github.com/metalinked/laravel-settings-kit/compare/v1.0.3...v1.1.0) (2025-09-30)


### Features

* rename lang column to locale for Laravel consistency ([242acb0](https://github.com/metalinked/laravel-settings-kit/commit/242acb06b3314ed3d8b85eff5f10ae6538ea7cf4))


### Bug Fixes

* correct locale field references in translation queries ([19906d9](https://github.com/metalinked/laravel-settings-kit/commit/19906d95b6aa23db56fd242c216bc4fdf81be7fb))

## [1.0.3](https://github.com/metalinked/laravel-settings-kit/compare/v1.0.2...v1.0.3) (2025-09-05)


### Bug Fixes

* completely solved issues with settings kit endpoints, config, tests, and docs ([4f1994a](https://github.com/metalinked/laravel-settings-kit/commit/4f1994a1d554731a912000fe0616984c610c451f))

## [1.0.2](https://github.com/metalinked/laravel-settings-kit/compare/v1.0.1...v1.0.2) (2025-09-05)


### Bug Fixes

* add smart API auth middleware, flexible config, docs & tests ([5831f7b](https://github.com/metalinked/laravel-settings-kit/commit/5831f7b4b1d045ec6633489cdd77755ea74df76b))

## [1.0.1](https://github.com/metalinked/laravel-settings-kit/compare/v1.0.0...v1.0.1) (2025-08-13)


### Bug Fixes

* set user customizable flag when creating settings for users ([26f3531](https://github.com/metalinked/laravel-settings-kit/commit/26f3531e6e7b4e6ba059a0c23a743999b59a126b))

## 1.0.0 (2025-08-12)


### Miscellaneous Chores

* trigger Packagist webhook ([b716cf6](https://github.com/metalinked/laravel-settings-kit/commit/b716cf68dede0198683d697f8f84fa5e47b97dd1))
