<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Definition;
use Metalinked\LaravelSettingsKit\SettingsManager;

class ImportSettingsCommand extends Command {
    protected $signature = 'settings:import
        {path : JSON file created by settings:export}
        {--dry-run : Validate and show the changes without saving them}';

    protected $description = 'Import global setting values from JSON';

    public function handle(SettingsManager $settings): int {
        $path = $this->argument('path');

        if (!is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $values = json_decode((string) file_get_contents($path), true);

        if (!is_array($values) || ($values !== [] && array_is_list($values))) {
            $this->components->error('The file must contain a JSON object of setting keys and values.');

            return self::FAILURE;
        }

        $undefined = array_filter(array_keys($values), fn ($key) => $settings->definition((string) $key) === null);

        if ($undefined !== [] && config('settings-kit.strict', false)) {
            $this->components->error('Undefined setting(s) in strict mode: ' . implode(', ', $undefined));

            return self::FAILURE;
        }

        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = [$key, json_encode(Definition::export($settings->get($key))), json_encode($value)];
        }

        $this->table(['Key', 'Current', 'Imported'], $rows);

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            // set() validates every value before writing anything.
            $settings->set($values);
        } catch (ValidationException $e) {
            DB::rollBack();

            foreach ($e->errors() as $key => $messages) {
                $this->components->error("{$key}: " . implode(' ', $messages));
            }

            return self::FAILURE;
        }

        if ($dryRun) {
            DB::rollBack();
            $settings->flushCache();
            $this->components->info('Dry run: ' . count($values) . ' setting(s) are valid. Nothing was saved.');

            return self::SUCCESS;
        }

        DB::commit();
        $this->components->info(count($values) . ' setting(s) imported.');

        return self::SUCCESS;
    }
}
