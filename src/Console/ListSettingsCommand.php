<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Metalinked\LaravelSettingsKit\Definition;
use Metalinked\LaravelSettingsKit\SettingsManager;
use Metalinked\LaravelSettingsKit\SettingsRepository;

class ListSettingsCommand extends Command {
    protected $signature = 'settings:list {--group= : Only show one group}';

    protected $description = 'List defined settings with their default and current global value';

    public function handle(SettingsManager $settings, SettingsRepository $repository): int {
        $summary = $repository->summary();
        $group = $this->option('group');
        $rows = [];

        foreach ($settings->definitions() as $key => $definition) {
            if ($group !== null && $definition->getGroup() !== $group) {
                continue;
            }

            $rows[] = [
                $key,
                $definition->getGroup(),
                $definition->getType(),
                $this->display($definition->getDefault()),
                $settings->has($key) ? $this->display($settings->get($key)) : '—',
                $definition->isOverridable() ? 'yes' : 'no',
                $summary[$key]['overrides'] ?? 0,
            ];
        }

        if ($rows === []) {
            $this->components->info('No settings defined. Add them to config/settings-kit.php or use Settings::define().');
        } else {
            $this->table(['Key', 'Group', 'Type', 'Default', 'Global value', 'Overridable', 'Overrides'], $rows);
        }

        $orphans = array_diff(array_keys($summary), array_keys($settings->definitions()));

        if ($orphans !== []) {
            $this->newLine();
            $this->components->warn('Stored but not defined: ' . implode(', ', $orphans) . '. Run settings:prune to remove them.');
        }

        return self::SUCCESS;
    }

    protected function display(mixed $value): string {
        $value = Definition::export($value);

        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
