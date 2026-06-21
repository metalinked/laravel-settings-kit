# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run tests (SQLite in-memory, no setup needed)
composer test

# Run a single test file
vendor/bin/phpunit tests/Feature/SettingsServiceTest.php

# Run a single test method
vendor/bin/phpunit --filter test_can_get_global_setting

# Apply code formatting
composer format

# Check formatting without applying
composer format-check

# Static analysis (PHPStan level 5)
composer analyse

# Run all quality checks (format + analyse + test)
composer quality

# Coverage report
composer test-coverage
```

## Architecture

This is a **Laravel package** (not an app), registered via `SettingsKitServiceProvider` and published to Packagist as `metalinked/laravel-settings-kit`. The package auto-discovers via composer's `extra.laravel` section.

### Database structure (3 tables)

- `preferences` — one row per setting definition. `default_value` is the canonical global value. `is_user_customizable` controls whether per-user overrides are allowed.
- `preference_contents` — i18n translations (title/text per locale) linked to preferences.
- `user_preferences` — stores user-specific overrides. Only created when a user actually customizes a setting; users without a row inherit `preferences.default_value`.

Table names are configurable via `config('settings-kit.tables.*')`, so models read the table name from config in their constructors rather than using the default convention.

### Value resolution logic

`Settings::get($key, $userId)` resolves in this order:
1. If `$userId` given → look up `user_preferences` for that user; if not found, fall back to `preferences.default_value`
2. If no `$userId` → return `preferences.default_value` directly

`Settings::set($key, $value)` (no userId) **directly modifies `preferences.default_value`** using `prepareValue()` to ensure consistent string storage — it never creates a `user_preferences` row. Changing the global default immediately affects all users who have not customized the setting.

Setting `is_user_customizable = false` on a preference prevents `Settings::set($key, $value, $userId)` with a userId — it throws `InvalidArgumentException`.

`Settings::forget($key, $userId)` removes user-specific overrides only. Calling it without a userId is a no-op (global settings are changed via `set()`).

`Settings::delete($key)` permanently removes a preference and manually cascades to `user_preferences` and `preference_contents` (not DB-level cascade, for SQLite compatibility).

### Service and facade

`SettingsService` is bound as a singleton under the `settings-kit` key. `Facades\Settings` proxies to it. All caching happens inside `SettingsService` using configurable TTL and prefix; cache key format: `{prefix}:{key}:global` or `{prefix}:{key}:user_{id}`.

When a global default changes, `clearCache()` also invalidates cache for all users who have rows in `user_preferences` for that key, so they get the new default.

### Events

`SettingUpdated` event is dispatched after every `set()` call (after cache is cleared). Payload: `key`, `value`, `userId`. Applications can listen to this event for audit logging or side effects.

### API layer

Routes are loaded unconditionally from `routes/api.php`. The `SettingsKitApiAuth` middleware decides whether to allow or reject based on `SETTINGS_KIT_API_ENABLED` and auth mode. Auth modes: `token` (static bearer using `hash_equals()` for timing-safe comparison), `sanctum`, `passport`, or bypassed in dev via `SETTINGS_KIT_API_DISABLE_AUTH_DEV=true`.

Two explicit route groups (`/global/{key}`, `/user/{key}`) plus legacy routes (`/{key}`). The `user_id` in user endpoints is passed as a query param, not path segment, to allow authenticated routes to fall back to `Auth::user()`.

### Blade directive

`@setting('key')` outputs the global value. `@setting('key', auth()->id())` outputs the user-specific value.

### Artisan commands

- `settings:list` — table view of all settings, with `--category`, `--role`, `--type` filters
- `settings:export` — export to JSON (or YAML if `yaml_emit` available)
- `settings:import` — import from JSON with `--force` and `--dry-run` options
- `settings:clear-cache` — clear cached settings (requires a tags-capable driver; no-op with file/array)

### Code style

K&R brace style (opening brace on the same line), PSR-12 base, enforced by PHP-CS-Fixer. Run `composer format` before committing. Commits use semantic prefixes: `feat:`, `fix:`, `docs:`, `style:`, `refactor:`, `test:`.

### Testing

Tests use Orchestra Testbench with SQLite in-memory. `TestCase` in `tests/TestCase.php` loads migrations from `database/migrations` and disables cache (`settings-kit.cache.enabled = false`). No external services needed.

`RefreshDatabase` wraps each test in a transaction. SQLite foreign-key PRAGMA cannot be set inside a transaction, so the `delete()` method manually cascades deletions instead of relying on DB-level `ON DELETE CASCADE`.
