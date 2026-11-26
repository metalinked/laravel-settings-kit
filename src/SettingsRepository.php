<?php

namespace Metalinked\LaravelSettingsKit;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

/**
 * Reads and writes raw stored values. All values of a scope are loaded with one
 * query, cached together and memoised for the rest of the request.
 */
class SettingsRepository {
    /** @var array<string, array<string, string>> scope suffix => [key => raw value] */
    protected array $loaded = [];

    /** @var array<string, true> scopes written by a transaction that is still open */
    protected array $uncommitted = [];

    /** @var array<string, array<string, true>> scope suffix => unreadable keys already reported */
    protected array $reported = [];

    protected ?string $version = null;

    /**
     * @return array<string, string>
     */
    public function values(Scope $scope): array {
        $suffix = $scope->cacheSuffix();

        if (isset($this->uncommitted[$suffix])) {
            // These rows may still be rolled back: read them, but keep no copy.
            if (DB::transactionLevel() > 0) {
                return $this->load($scope);
            }

            unset($this->uncommitted[$suffix]);
        }

        return $this->loaded[$suffix] ??= $this->fromCache($scope);
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
     * Move the values of a key to another one, in every scope. A scope that
     * already stores the new key keeps both rows, unless $overwrite is true.
     *
     * @return array{int, int} moved and skipped rows
     */
    public function rename(string $from, string $to, bool $overwrite = false): array {
        $result = DB::transaction(function () use ($from, $to, $overwrite) {
            $taken = [];

            foreach ($this->rows($to) as $row) {
                $taken["{$row->settable_type}|{$row->settable_id}"] = $row->id;
            }

            $move = [];
            $replace = [];
            $skipped = 0;

            foreach ($this->rows($from) as $row) {
                $existing = $taken["{$row->settable_type}|{$row->settable_id}"] ?? null;

                if ($existing !== null && !$overwrite) {
                    $skipped++;

                    continue;
                }

                if ($existing !== null) {
                    $replace[] = $existing;
                }

                $move[] = $row->id;
            }

            foreach (array_chunk($replace, 500) as $ids) {
                $this->query()->whereIn('id', $ids)->delete();
            }

            foreach (array_chunk($move, 500) as $ids) {
                $this->query()->whereIn('id', $ids)->update(['key' => $to, 'updated_at' => now()]);
            }

            return [count($move), $skipped];
        });

        $this->flush();

        return $result;
    }

    /**
     * Pass every stored value of a key, in every scope, through $callback and
     * save the ones it returns a new raw value for. A row that changes in the
     * meantime is left alone.
     *
     * @param Closure(string): ?string $callback
     */
    public function rewrite(string $key, Closure $callback): void {
        $changed = false;

        foreach ($this->rows($key) as $row) {
            $value = $callback($row->value);

            if ($value === null) {
                continue;
            }

            $this->query()
                ->where('id', $row->id)
                ->where('value', $row->value)
                ->update(['value' => $value, 'updated_at' => now()]);
            $changed = true;
        }

        if ($changed) {
            $this->flush();
        }
    }

    /**
     * Whether this is the first time in this request that a key of a scope is
     * reported as unreadable. Writing to the scope starts over.
     */
    public function firstReport(Scope $scope, string $key): bool {
        $suffix = $scope->cacheSuffix();

        if (isset($this->reported[$suffix][$key])) {
            return false;
        }

        return $this->reported[$suffix][$key] = true;
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
        $this->reported = [];
        $this->version = Str::random(12);

        if ($this->cacheEnabled()) {
            $this->cache()->forever($this->versionKey(), $this->version);
        }
    }

    /**
     * @return array<string, string>
     */
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
        $suffix = $scope->cacheSuffix();
        $key = $this->cacheEnabled() ? $this->cacheKey($scope) : null;

        unset($this->loaded[$suffix], $this->reported[$suffix]);
        $this->uncommitted[$suffix] = true;

        if ($key !== null) {
            $this->cache()->forget($key);
        }

        // Runs at once outside a transaction. Forgets the key a second time
        // because another request may have cached the old rows before the commit.
        DB::afterCommit(function () use ($suffix, $key) {
            unset($this->uncommitted[$suffix], $this->loaded[$suffix]);

            if ($key !== null) {
                $this->cache()->forget($key);
            }
        });
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

    /**
     * Every row of a key, read in chunks.
     *
     * @return LazyCollection<int, \stdClass>
     */
    protected function rows(string $key): LazyCollection {
        /** @var LazyCollection<int, \stdClass> $rows */
        $rows = $this->query()->where('key', $key)->lazyById();

        return $rows;
    }

    protected function query(): Builder {
        return DB::table(config('settings-kit.table', 'settings_kit_values'));
    }
}
