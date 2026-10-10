<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads and writes raw stored values. All values of a scope are loaded with one
 * query, cached together and memoised for the rest of the request.
 */
class SettingsRepository {
    /** @var array<string, array<string, string>> scope suffix => [key => raw value] */
    protected array $loaded = [];

    protected ?string $version = null;

    /**
     * @return array<string, string>
     */
    public function values(Scope $scope): array {
        return $this->loaded[$scope->cacheSuffix()] ??= $this->fromCache($scope);
    }

    /**
     * Insert or update raw values in a scope.
     *
     * @param array<string, string> $values
     */
    public function put(Scope $scope, array $values): void {
        if ($values === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = [
                'key' => (string) $key,
                'settable_type' => $scope->type,
                'settable_id' => $scope->id,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->query()->upsert($rows, ['settable_type', 'settable_id', 'key'], ['value', 'updated_at']);
        $this->forgetScope($scope);
    }

    /**
     * Delete stored values in a scope (all of them when $keys is null).
     *
     * @param list<string>|null $keys
     */
    public function delete(Scope $scope, ?array $keys = null): int {
        $query = $this->query()
            ->where('settable_type', $scope->type)
            ->where('settable_id', $scope->id);

        if ($keys !== null) {
            $query->whereIn('key', $keys);
        }

        $deleted = $query->delete();
        $this->forgetScope($scope);

        return $deleted;
    }

    /**
     * Delete the given keys in every scope.
     *
     * @param list<string> $keys
     */
    public function deleteEverywhere(array $keys, bool $onlyModels = false): int {
        $query = $this->query()->whereIn('key', $keys);

        if ($onlyModels) {
            $query->where('settable_type', '!=', '');
        }

        $deleted = $query->delete();
        $this->flush();

        return $deleted;
    }

    /**
     * Number of stored rows per key, split by global value and model overrides.
     *
     * @return array<string, array{global: bool, overrides: int}>
     */
    public function summary(): array {
        $summary = [];

        $rows = $this->query()
            ->select('key', DB::raw("case when settable_type = '' then 1 else 0 end as is_global"), DB::raw('count(*) as total'))
            ->groupBy('key', 'is_global')
            ->get();

        foreach ($rows as $row) {
            $summary[$row->key] ??= ['global' => false, 'overrides' => 0];

            if ((int) $row->is_global === 1) {
                $summary[$row->key]['global'] = true;
            } else {
                $summary[$row->key]['overrides'] += (int) $row->total;
            }
        }

        return $summary;
    }

    /**
     * Invalidate every cached scope at once by rotating the cache version.
     * Works with any cache driver; no tags needed.
     */
    public function flush(): void {
        $this->loaded = [];
        $this->version = Str::random(12);

        if ($this->cacheEnabled()) {
            $this->cache()->forever($this->versionKey(), $this->version);
        }
    }

    protected function fromCache(Scope $scope): array {
        if (!$this->cacheEnabled()) {
            return $this->load($scope);
        }

        $key = $this->cacheKey($scope);
        $ttl = config('settings-kit.cache.ttl');

        return $ttl === null
            ? $this->cache()->rememberForever($key, fn () => $this->load($scope))
            : $this->cache()->remember($key, (int) $ttl, fn () => $this->load($scope));
    }

    /**
     * @return array<string, string>
     */
    protected function load(Scope $scope): array {
        return $this->query()
            ->where('settable_type', $scope->type)
            ->where('settable_id', $scope->id)
            ->pluck('value', 'key')
            ->all();
    }

    protected function forgetScope(Scope $scope): void {
        unset($this->loaded[$scope->cacheSuffix()]);

        if (!$this->cacheEnabled()) {
            return;
        }

        $key = $this->cacheKey($scope);
        $this->cache()->forget($key);

        // A read between this write and the commit could re-cache the old value.
        DB::afterCommit(fn () => $this->cache()->forget($key));
    }

    protected function cacheKey(Scope $scope): string {
        return config('settings-kit.cache.prefix', 'settings_kit') . ":{$this->version()}:{$scope->cacheSuffix()}";
    }

    protected function version(): string {
        return $this->version ??= $this->cache()->rememberForever($this->versionKey(), fn () => Str::random(12));
    }

    protected function versionKey(): string {
        return config('settings-kit.cache.prefix', 'settings_kit') . ':version';
    }

    protected function cacheEnabled(): bool {
        return (bool) config('settings-kit.cache.enabled', true);
    }

    protected function cache(): Cache {
        return CacheFacade::store(config('settings-kit.cache.store'));
    }

    protected function query(): Builder {
        return DB::table(config('settings-kit.table', 'settings_kit_values'));
    }
}
