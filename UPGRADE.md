# Upgrading from 1.x to 2.0

Version 2 changes where settings are described. In 1.x every setting was a row in the `preferences` table. In 2.x settings are **defined in code** (config file or `Settings::define()`), and the database only stores values that differ from the default, in a single table (`settings_kit_values`).

Requirements are now PHP 8.2+ and Laravel 11+. Projects on older versions can stay on the `1.x` branch, which keeps receiving security fixes.

## 1. Update and migrate

```bash
composer require metalinked/laravel-settings-kit:^2.0
php artisan migrate        # creates settings_kit_values; the 1.x tables are left untouched
```

If you published the 1.x config file, publish the new one again (`php artisan vendor:publish --tag=settings-kit-config --force`) and copy over any custom values. Most 1.x options (`api.*`, `tables.*`, `fallback_locale`, `user_model`) no longer exist.

## 2. Convert your data

```bash
php artisan settings:upgrade-from-v1 --dry-run   # preview
php artisan settings:upgrade-from-v1
```

The command:

- writes one definition per `preferences` row to `config/settings-kit-definitions.php`, keeping the type, default, select options, `is_user_customizable` (now `overridable`) and `category` (now `group`). `role` and `required` are kept under `meta`;
- writes the `preference_contents` translations to `lang/{locale}/settings.php`. If that file already exists, it writes `settings.from-v1.php` next to it so you can merge it yourself;
- copies every `user_preferences` row as an override of your user model (`--user-model` if it is not the auth model).

Then load the definitions from `config/settings-kit.php`:

```php
'settings' => require __DIR__ . '/settings-kit-definitions.php',
```

The command options `--preferences-table`, `--contents-table` and `--user-preferences-table` cover renamed 1.x tables. Once everything works, drop the three 1.x tables.

## 3. Update your code

| 1.x | 2.x |
|---|---|
| `Settings::get('key')` | `Settings::get('key')` (unchanged) |
| `Settings::get('key', $userId)` | `Settings::for($user)->get('key')` or `$user->settings()->get('key')` |
| `Settings::set('key', $value, $userId)` | `$user->settings()->set('key', $value)` |
| `Settings::forget('key', $userId)` | `$user->settings()->forget('key')` |
| `Settings::forgetAll($userId)` | `$user->settings()->reset()` |
| `Settings::getUserOverrides($userId)` | `$user->settings()->stored()` |
| `Settings::allForUser($userId, $locale)` | `$user->settings()->panel()` (uses the app locale) |
| `Settings::getMultiple($keys, $userId)` | `$user->settings()->many($keys)` |
| `Settings::setMultiple($values, $userId)` | `$user->settings()->set($values)` |
| `Settings::isEnabled('key')` | `Settings::enabled('key')` |
| `Settings::exists('key')` / `has('key')` | `Settings::definition('key') !== null` (defined) or `Settings::has('key')` (has a stored value) |
| `Settings::create([...])`, `createIfNotExists()`, `createWithTranslations()` | Define it in config or with `Settings::define()` |
| `Settings::setWithAutoCreate()`, `remember()` | `Settings::set()` (undefined keys work when strict mode is off) |
| `Settings::delete('key')` | Remove the definition, then `php artisan settings:prune` |
| `Settings::label('key')`, `description('key')` | `Settings::definition('key')->getLabel()` / `getDescription()` |
| `Settings::all($role, $userId, $category)` | `Settings::panel($group)` / `$user->settings()->panel($group)` |
| `Settings::getCategories()` | `collect(Settings::definitions())->map->getGroup()->unique()` |
| `@setting('key', $userId)` | `{{ $user->settings()->get('key') }}` |
| `Preference`, `UserPreference`, `PreferenceContent` models | Removed |

Behaviour changes to be aware of:

- **Validation.** Invalid values throw `Illuminate\Validation\ValidationException` (keyed by setting), not `InvalidArgumentException`.
- **Types.** `json` is now `array`. `float` and PHP backed enums are new.
- **Events.** `SettingUpdated` now carries `previous`, `scope` and `settable` instead of `userId`. Resets dispatch a separate `SettingReset` event. Both wait for the database transaction to commit.
- **Global changes.** `Settings::set()` no longer rewrites the definition's default. It stores a global value, and `Settings::forget('key')` goes back to the default declared in code.

## 4. REST API

The 1.x API (`/api/settings-kit/...` with token/Sanctum/Passport modes) is replaced by a smaller set of endpoints that you mount yourself, behind your own middleware:

```php
Route::middleware('auth:sanctum')->prefix('api')->group(fn () => Settings::routes());
Gate::define('manage-settings', fn (User $user) => $user->is_admin);
```

See "JSON endpoints" in the README. Responses are `{"data": [...]}` panels, and errors are standard Laravel 401/403/422 responses. If you need static-token server-to-server access, add your own middleware to the route group.
