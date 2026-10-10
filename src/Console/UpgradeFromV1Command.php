<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Metalinked\LaravelSettingsKit\Scope;
use Metalinked\LaravelSettingsKit\SettingsRepository;

/**
 * Moves a 1.x installation to 2.x: preference rows become code definitions,
 * translations become a language file and user overrides are copied to the
 * new values table. The 1.x tables are left untouched.
 */
class UpgradeFromV1Command extends Command {
    protected $signature = 'settings:upgrade-from-v1
        {--user-model= : Model that owned 1.x user preferences (default: auth user model)}
        {--definitions=config/settings-kit-definitions.php : Where to write the generated definitions}
        {--preferences-table=preferences}
        {--contents-table=preference_contents}
        {--user-preferences-table=user_preferences}
        {--dry-run : Show what would happen without writing anything}';

    protected $description = 'Convert settings stored by laravel-settings-kit 1.x to the 2.x format';

    public function handle(SettingsRepository $repository): int {
        $tables = [
            'preferences' => (string) $this->option('preferences-table'),
            'contents' => (string) $this->option('contents-table'),
            'user' => (string) $this->option('user-preferences-table'),
        ];

        if (!Schema::hasTable($tables['preferences'])) {
            $this->components->error("Table [{$tables['preferences']}] not found. Nothing to upgrade.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $preferences = DB::table($tables['preferences'])->orderBy('id')->get();

        $definitions = [];
        $translations = [];

        foreach ($preferences as $preference) {
            $definitions[$preference->key] = $this->definition($preference);
        }

        if (Schema::hasTable($tables['contents'])) {
            $keys = $preferences->pluck('key', 'id');

            foreach (DB::table($tables['contents'])->get() as $content) {
                if (!isset($keys[$content->preference_id])) {
                    continue;
                }

                $key = $keys[$content->preference_id];
                $translations[$content->locale][$key]['label'] = $content->title;

                if ($content->text !== null && $content->text !== '') {
                    $translations[$content->locale][$key]['description'] = $content->text;
                }
            }
        }

        $this->writeDefinitions($definitions, $dryRun);
        $this->writeTranslations($translations, $dryRun);
        $copied = $this->copyOverrides($preferences, $tables['user'], $repository, $dryRun);

        $this->newLine();
        $this->components->info(sprintf(
            '%s %d definition(s), %d locale file(s) and %d user override(s).',
            $dryRun ? 'Dry run: would convert' : 'Converted',
            count($definitions),
            count($translations),
            $copied
        ));

        if (!$dryRun) {
            $this->components->bulletList([
                "Load the definitions in config/settings-kit.php: 'settings' => require __DIR__ . '/" . basename((string) $this->option('definitions')) . "',",
                'Replace Settings::get($key, $userId) calls with Settings::for($user)->get($key) or $user->settings()->get($key).',
                'Once everything works, drop the 1.x tables: ' . implode(', ', $tables) . '.',
            ]);
        }

        return self::SUCCESS;
    }

    protected function definition(object $preference): array {
        $type = match ($preference->type) {
            'boolean', 'integer', 'select' => $preference->type,
            'json' => 'array',
            default => 'string',
        };

        $definition = ['type' => $type];
        $options = is_string($preference->options ?? null) ? json_decode($preference->options, true) : null;

        if ($type === 'select') {
            if (is_array($options) && $options !== []) {
                $definition['options'] = $options;
            } else {
                $definition['type'] = 'string';
            }
        }

        $definition['default'] = $preference->default_value === null
            ? null
            : $this->convert($preference->type, $preference->default_value);

        if ($definition['default'] === null) {
            $definition['nullable'] = true;
        }

        if ($preference->is_user_customizable ?? false) {
            $definition['overridable'] = true;
        }

        if (!empty($preference->category)) {
            $definition['group'] = $preference->category;
        }

        $meta = array_filter([
            'role' => $preference->role ?? null,
            'required' => (bool) ($preference->required ?? false) ?: null,
        ], fn ($value) => $value !== null);

        if ($meta !== []) {
            $definition['meta'] = $meta;
        }

        return $definition;
    }

    /**
     * Convert a 1.x string value to the PHP value its type described.
     */
    protected function convert(string $type, string $value): mixed {
        return match ($type) {
            'boolean' => in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true),
            'integer' => (int) $value,
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    protected function writeDefinitions(array $definitions, bool $dryRun): void {
        $path = (string) $this->option('definitions');
        $isAbsolute = str_starts_with($path, '/') || str_starts_with($path, '\\') || (strlen($path) > 2 && $path[1] === ':');
        $path = $isAbsolute ? $path : base_path($path);

        if ($dryRun) {
            $this->components->twoColumnDetail('Definitions', $path);

            return;
        }

        if (is_file($path) && !$this->confirm("{$path} already exists. Overwrite it?")) {
            return;
        }

        $this->ensureDirectory($path);
        file_put_contents($path, "<?php\n\n// Generated by settings:upgrade-from-v1. Edit freely.\n\nreturn " . $this->export($definitions) . ";\n");
        $this->components->twoColumnDetail('Definitions', $path);
    }

    protected function writeTranslations(array $translations, bool $dryRun): void {
        $file = config('settings-kit.translations', 'settings');

        foreach ($translations as $locale => $entries) {
            $path = lang_path("{$locale}/{$file}.php");

            if (is_file($path)) {
                // Never touch an existing language file; write next to it instead.
                $path = lang_path("{$locale}/{$file}.from-v1.php");
                $this->components->warn("lang/{$locale}/{$file}.php exists; merge {$path} into it by hand.");
            }

            $this->components->twoColumnDetail("Translations ({$locale})", $path);

            if ($dryRun) {
                continue;
            }

            $nested = [];

            foreach ($entries as $key => $lines) {
                Arr::set($nested, $key, $lines);
            }

            $this->ensureDirectory($path);
            file_put_contents($path, "<?php\n\nreturn " . $this->export($nested) . ";\n");
        }
    }

    protected function copyOverrides($preferences, string $table, SettingsRepository $repository, bool $dryRun): int {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $userModel = (string) ($this->option('user-model') ?: config('auth.providers.users.model', 'App\\Models\\User'));
        $morphType = class_exists($userModel) && is_subclass_of($userModel, Model::class)
            ? (new $userModel())->getMorphClass()
            : $userModel;

        $byId = $preferences->keyBy('id');
        $perUser = [];
        $count = 0;

        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            $preference = $byId[$row->preference_id] ?? null;

            if ($preference === null || $row->user_id === null) {
                continue;
            }

            $perUser[$row->user_id][$preference->key] = json_encode(
                $this->convert($preference->type, (string) $row->value),
                JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE
            );
            $count++;
        }

        if (!$dryRun) {
            foreach ($perUser as $userId => $values) {
                $repository->put(Scope::of($morphType, $userId), $values);
            }
        }

        return $count;
    }

    protected function ensureDirectory(string $path): void {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
    }

    /**
     * var_export() with short array syntax and readable indentation.
     */
    protected function export(mixed $value, int $depth = 0): string {
        if (!is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $isList = array_is_list($value);
        $lines = [];

        foreach ($value as $key => $item) {
            $lines[] = $indent . ($isList ? '' : var_export($key, true) . ' => ') . $this->export($item, $depth + 1) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ']';
    }
}
