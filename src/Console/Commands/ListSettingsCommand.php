<?php

namespace Metalinked\LaravelSettingsKit\Console\Commands;

use Illuminate\Console\Command;
use Metalinked\LaravelSettingsKit\Models\Preference;

class ListSettingsCommand extends Command {
    protected $signature = 'settings:list
                            {--category= : Filter by category}
                            {--role= : Filter by role}
                            {--type= : Filter by type (string, boolean, integer, json, select)}';

    protected $description = 'List all registered settings';

    public function handle(): int {
        $query = Preference::query();

        if ($category = $this->option('category')) {
            $query->forCategory($category);
        }

        if ($this->option('role') !== null) {
            $query->forRole($this->option('role') ?: null);
        }

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        $preferences = $query->orderBy('category')->orderBy('key')->get();

        if ($preferences->isEmpty()) {
            $this->info('No settings found.');

            return 0;
        }

        $this->table(
            ['Key', 'Type', 'Category', 'Default Value', 'User Customizable', 'Role'],
            $preferences->map(fn ($p) => [
                $p->key,
                $p->type,
                $p->category ?? '—',
                mb_strimwidth((string) ($p->default_value ?? '(null)'), 0, 40, '…'),
                $p->is_user_customizable ? 'Yes' : 'No',
                $p->role ?? '—',
            ])
        );

        $this->line('');
        $this->info("Total: {$preferences->count()} setting(s)");

        return 0;
    }
}
