<?php

namespace Metalinked\LaravelSettingsKit\Console\Commands;

use Illuminate\Console\Command;

class ClearCacheCommand extends Command {
    protected $signature = 'settings:clear-cache';

    protected $description = 'Clear all cached settings values';

    public function handle(): int {
        app('settings-kit')->clearAllCache();
        $this->info('Settings cache cleared successfully.');

        return 0;
    }
}
