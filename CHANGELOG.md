# Changelog

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
