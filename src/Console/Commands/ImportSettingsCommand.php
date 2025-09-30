<?php

namespace Metalinked\LaravelSettingsKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;

class ImportSettingsCommand extends Command {
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'settings:import
                            {file : Path to the import file}
                            {--format=json : Import format (json or yaml)}
                            {--force : Overwrite existing settings}
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     */
    protected $description = 'Import settings from JSON or YAML format';

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $file = $this->argument('file');
        $format = $this->option('format');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        if (!in_array($format, ['json', 'yaml'])) {
            $this->error('Format must be either json or yaml');

            return 1;
        }

        $content = file_get_contents($file);

        $data = match ($format) {
            'json' => json_decode($content, true),
            'yaml' => function_exists('yaml_parse') ? yaml_parse($content) : null,
            default => null,
        };

        if (!$data) {
            $this->error('Failed to parse the import file');

            return 1;
        }

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            $this->error('Invalid file format: missing settings array');

            return 1;
        }

        $settings = $data['settings'];
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
            $this->line('');
        }

        DB::beginTransaction();

        try {
            foreach ($settings as $settingData) {
                if (!isset($settingData['key'])) {
                    $this->warn('Skipping setting without key');
                    $errors++;

                    continue;
                }

                $key = $settingData['key'];
                $existing = Preference::where('key', $key)->first();

                if ($existing && !$force) {
                    $this->warn("Skipping existing setting: {$key} (use --force to overwrite)");
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $action = $existing ? 'UPDATE' : 'CREATE';
                    $this->line("  {$action}: {$key}");

                    if (isset($settingData['translations'])) {
                        foreach ($settingData['translations'] as $locale => $translation) {
                            $this->line("    Translation ({$locale}): {$translation['title']}");
                        }
                    }

                    $imported++;

                    continue;
                }

                // Create or update preference
                $preference = Preference::updateOrCreate(
                    ['key' => $key],
                    [
                        'type' => $settingData['type'] ?? 'string',
                        'category' => $settingData['category'] ?? null,
                        'role' => $settingData['role'] ?? null,
                        'required' => $settingData['required'] ?? false,
                        'default_value' => $settingData['default_value'] ?? null,
                        'options' => $settingData['options'] ?? null,
                    ]
                );

                // Import translations
                if (isset($settingData['translations']) && is_array($settingData['translations'])) {
                    foreach ($settingData['translations'] as $locale => $translation) {
                        PreferenceContent::updateOrCreate(
                            [
                                'preference_id' => $preference->id,
                                'locale' => $locale,
                            ],
                            [
                                'title' => $translation['title'] ?? '',
                                'text' => $translation['text'] ?? '',
                            ]
                        );
                    }
                }

                $action = $existing ? 'Updated' : 'Created';
                $this->info("{$action}: {$key}");
                $imported++;
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Import failed: {$e->getMessage()}");

            return 1;
        }

        $this->line('');
        $this->info('Import completed!');
        $this->info("Imported: {$imported}");

        if ($skipped > 0) {
            $this->info("Skipped: {$skipped}");
        }

        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
        }

        return 0;
    }
}
