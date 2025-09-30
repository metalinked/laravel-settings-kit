<?php

namespace Metalinked\LaravelSettingsKit\Console\Commands;

use Illuminate\Console\Command;
use Metalinked\LaravelSettingsKit\Models\Preference;

class ExportSettingsCommand extends Command {
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'settings:export
                            {--format=json : Export format (json or yaml)}
                            {--file= : Output file path}
                            {--role= : Export only settings for specific role}
                            {--category= : Export only settings for specific category}';

    /**
     * The console command description.
     */
    protected $description = 'Export settings to JSON or YAML format';

    /**
     * Execute the console command.
     */
    public function handle(): int {
        $format = $this->option('format');
        $file = $this->option('file');
        $role = $this->option('role');
        $category = $this->option('category');

        if (!in_array($format, ['json', 'yaml'])) {
            $this->error('Format must be either json or yaml');

            return 1;
        }

        $query = Preference::with('contents');

        if ($role) {
            $query->forRole($role);
        }

        if ($category) {
            $query->forCategory($category);
        }

        $preferences = $query->get();

        $data = [
            'exported_at' => now()->toISOString(),
            'settings' => [],
        ];

        foreach ($preferences as $preference) {
            $settingData = [
                'key' => $preference->key,
                'type' => $preference->type,
                'category' => $preference->category,
                'role' => $preference->role,
                'required' => $preference->required,
                'default_value' => $preference->default_value,
                'options' => $preference->options,
                'translations' => [],
            ];

            foreach ($preference->contents as $content) {
                $settingData['translations'][$content->locale] = [
                    'title' => $content->title,
                    'text' => $content->text,
                ];
            }

            $data['settings'][] = $settingData;
        }

        $output = match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'yaml' => function_exists('yaml_emit') ? yaml_emit($data) : json_encode($data, JSON_PRETTY_PRINT),
            default => json_encode($data, JSON_PRETTY_PRINT),
        };

        if ($file) {
            file_put_contents($file, $output);
            $this->info("Settings exported to: {$file}");
        } else {
            $this->line($output);
        }

        $this->info('Export completed successfully!');
        $this->info("Exported {$preferences->count()} settings");

        return 0;
    }
}
