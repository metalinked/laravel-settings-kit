# TODO

Ideas for future versions. None of them has a date. If you need one, [open an issue](https://github.com/metalinked/laravel-settings-kit/issues) and describe your case.

## Under consideration

- [ ] Rules for the items of an array setting, such as `*.email`. A closure rule over the whole array works today.
- [ ] `date` and `datetime` types.
- [ ] Custom types registered by the application.
- [ ] Defaults that depend on the model, for settings declared with `Settings::define()` and an explicit type.
- [ ] Settings that only exist per model, without a global value.
- [ ] A transformer for the `config` option, for when Laravel expects a different value from the stored one.
- [ ] A label, a description and an order for groups.
- [ ] Loading the settings of several models in one query.
- [ ] `settings:get` and `settings:set` commands.
- [ ] `settings:export` and `settings:import` for one model.
- [ ] A Filament example in [docs/settings-screens.md](docs/settings-screens.md).
- [ ] A configurable fallback chain, such as user, team and global.
- [ ] An optional change history.

## For the next major version

- [ ] Leave the decrypted value of secrets out of `SettingUpdated` and `SettingReset`.

## Not planned

- An automatic current scope for `settings('key')`. Writes, `applyConfig()`, exports and imports could act on a tenant without the code showing it. An application can add its own helper.
- Permissions per setting and JSON endpoints for other models. Authorization belongs to the application.
- A `Settings::fake()` helper. Tests can store real values.
