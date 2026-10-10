<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Metalinked\LaravelSettingsKit\SettingsManager;
use Metalinked\LaravelSettingsKit\SettingsRepository;

class PruneSettingsCommand extends Command {
    protected $signature = 'settings:prune {--force : Do not ask for confirmation}';

    protected $description = 'Delete stored values of settings that are no longer defined, and overrides that are no longer allowed';

    public function handle(SettingsManager $settings, SettingsRepository $repository): int {
        $summary = $repository->summary();
        $definitions = $settings->definitions();

        $undefined = array_values(array_diff(array_keys($summary), array_keys($definitions)));
        $notOverridable = array_values(array_filter(
            array_keys($summary),
            fn ($key) => isset($definitions[$key]) && !$definitions[$key]->isOverridable() && $summary[$key]['overrides'] > 0
        ));

        if ($undefined === [] && $notOverridable === []) {
            $this->components->info('Nothing to prune.');

            return self::SUCCESS;
        }

        $this->components->bulletList(array_merge(
            array_map(fn ($key) => "{$key} (not defined)", $undefined),
            array_map(fn ($key) => "{$key} (overrides no longer allowed)", $notOverridable),
        ));

        if (!$this->option('force') && !$this->confirm('Delete these stored values?')) {
            return self::FAILURE;
        }

        $deleted = ($undefined !== [] ? $repository->deleteEverywhere($undefined) : 0)
            + ($notOverridable !== [] ? $repository->deleteEverywhere($notOverridable, onlyModels: true) : 0);

        $this->components->info("{$deleted} stored value(s) deleted.");

        return self::SUCCESS;
    }
}
