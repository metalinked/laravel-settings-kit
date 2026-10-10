<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Metalinked\LaravelSettingsKit\SettingsManager;

class ClearCacheCommand extends Command {
    protected $signature = 'settings:clear-cache';

    protected $description = 'Clear all cached setting values';

    public function handle(SettingsManager $settings): int {
        $settings->flushCache();
        $this->components->info('Settings cache cleared.');

        return self::SUCCESS;
    }
}
