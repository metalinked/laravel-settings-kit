<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Metalinked\LaravelSettingsKit\SettingsManager;
use Metalinked\LaravelSettingsKit\SettingsRepository;

class RenameSettingCommand extends Command {
    protected $signature = 'settings:rename
        {from : The old key}
        {to : The new key, already defined}
        {--overwrite : Replace the values already stored under the new key}';

    protected $description = 'Move the stored values of a setting to a new key, in every scope';

    public function handle(SettingsManager $settings, SettingsRepository $repository): int {
        /** @var string $from */
        $from = $this->argument('from');
        /** @var string $to */
        $to = $this->argument('to');

        // Some databases compare keys ignoring case.
        if (strcasecmp($from, $to) === 0) {
            $this->components->error('The new key must differ from the old one in more than letter case.');

            return self::FAILURE;
        }

        if ($settings->definition($to) === null) {
            $this->components->error("Setting '{$to}' is not defined.");

            return self::FAILURE;
        }

        [$moved, $skipped] = $repository->rename($from, $to, (bool) $this->option('overwrite'));

        if ($skipped > 0) {
            $this->components->warn("{$skipped} value(s) stay under '{$from}': their scope already stores '{$to}'. Use --overwrite to replace those.");
        }

        $this->components->info("{$moved} stored value(s) moved from '{$from}' to '{$to}'.");

        return self::SUCCESS;
    }
}
