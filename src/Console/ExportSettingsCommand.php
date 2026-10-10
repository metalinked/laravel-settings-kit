<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Metalinked\LaravelSettingsKit\Definition;
use Metalinked\LaravelSettingsKit\SettingsManager;

class ExportSettingsCommand extends Command {
    protected $signature = 'settings:export
        {path? : File to write; prints to the console when omitted}
        {--all : Include settings that still use their default value}
        {--with-secrets : Include encrypted settings, decrypted, in plain text}';

    protected $description = 'Export global setting values to JSON';

    public function handle(SettingsManager $settings): int {
        $values = array_map(
            fn ($value) => Definition::export($value),
            $this->option('all') ? $settings->all() : $settings->stored()
        );

        $secrets = array_filter(
            array_keys($values),
            fn ($key) => $settings->definition((string) $key)?->isEncrypted() ?? false
        );

        if ($secrets !== [] && !$this->option('with-secrets')) {
            $values = array_diff_key($values, array_flip($secrets));
            $this->components->warn('Skipped encrypted setting(s): ' . implode(', ', $secrets) . '. Use --with-secrets to include them.');
        }

        ksort($values);
        $json = (string) json_encode((object) $values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($path = $this->argument('path')) {
            file_put_contents($path, $json . PHP_EOL);
            $this->components->info(count($values) . " setting(s) exported to {$path}.");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
