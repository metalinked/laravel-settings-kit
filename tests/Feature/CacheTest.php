<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Scope;
use Metalinked\LaravelSettingsKit\SettingsManager;
use Metalinked\LaravelSettingsKit\SettingsRepository;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;
use RuntimeException;

class CacheTest extends TestCase {
    protected User $user;

    protected function setUp(): void {
        parent::setUp();

        foreach (range(1, 10) as $i) {
            Settings::define("key{$i}")->default($i)->overridable();
        }

        $this->user = User::create(['name' => 'Anna']);
    }

    /**
     * Simulate a new request: drop the request-scoped memo but keep the cache.
     */
    protected function newRequest(): void {
        $this->app->forgetScopedInstances();
    }

    protected function countQueries(callable $callback): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_reading_many_settings_costs_one_query_per_scope(): void {
        $this->user->settings()->set('key1', 100);
        Settings::set('key2', 200);
        $this->newRequest();

        $queries = $this->countQueries(function () {
            foreach (range(1, 10) as $i) {
                $this->user->settings()->get("key{$i}");
            }
        });

        $this->assertSame(2, $queries); // user scope + global scope
    }

    public function test_cached_values_are_reused_across_requests(): void {
        Settings::get('key1');
        $this->user->settings()->get('key1');
        $this->newRequest();

        $this->assertSame(0, $this->countQueries(fn () => [Settings::get('key1'), $this->user->settings()->get('key1')]));
    }

    public function test_a_global_change_is_visible_to_users_without_override(): void {
        $this->assertSame(1, $this->user->settings()->get('key1'));

        Settings::set('key1', 50);
        $this->newRequest();

        $this->assertSame(50, $this->user->settings()->get('key1'));
    }

    public function test_writes_invalidate_the_cache_of_their_scope(): void {
        $this->user->settings()->get('key1');
        $this->user->settings()->set('key1', 7);
        $this->newRequest();
        $this->assertSame(7, $this->user->settings()->get('key1'));

        $this->user->settings()->forget('key1');
        $this->newRequest();
        $this->assertSame(1, $this->user->settings()->get('key1'));
    }

    public function test_flush_invalidates_every_scope(): void {
        $this->user->settings()->get('key1');

        // A change made behind the package's back is only seen after a flush.
        DB::table('settings_kit_values')->insert([
            'key' => 'key1', 'settable_type' => $this->user->getMorphClass(), 'settable_id' => (string) $this->user->id, 'value' => '99',
        ]);
        $this->newRequest();
        $this->assertSame(1, $this->user->settings()->get('key1'));

        $this->artisan('settings:clear-cache')->assertSuccessful();
        $this->newRequest();
        $this->assertSame(99, $this->user->settings()->get('key1'));
    }

    public function test_cache_can_be_disabled(): void {
        config(['settings-kit.cache.enabled' => false]);
        Settings::get('key1');
        $this->newRequest();

        $this->assertSame(1, $this->countQueries(fn () => Settings::get('key1')));
    }

    public function test_a_stale_value_cached_during_a_transaction_is_dropped_on_commit(): void {
        $repository = new class () extends SettingsRepository {
            public function keyFor(Scope $scope): string {
                return $this->cacheKey($scope);
            }
        };
        $scope = Scope::global();
        $repository->values($scope);

        DB::transaction(function () use ($repository, $scope) {
            $repository->put($scope, ['key1' => '111']);

            // Another request reads before the commit and caches the old rows.
            Cache::put($repository->keyFor($scope), [], 60);
        });

        $this->newRequest();
        $this->assertSame(111, Settings::get('key1'));
    }

    public function test_a_rolled_back_write_does_not_leave_its_value_in_the_cache(): void {
        Settings::get('key1');

        try {
            DB::transaction(function () {
                Settings::set('key1', 5);

                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
        }

        $this->newRequest();
        $this->assertSame(1, Settings::get('key1'));
    }

    public function test_a_rollback_after_reading_inside_the_transaction_leaves_nothing_behind(): void {
        Settings::get('key1');

        $this->rollBack(function () {
            Settings::set('key1', 5);
            $this->assertSame(5, Settings::get('key1'));
        });

        $this->assertSame(1, Settings::get('key1'), 'same request');
        $this->newRequest();
        $this->assertSame(1, Settings::get('key1'), 'next request');
    }

    public function test_a_rolled_back_write_to_two_scopes_leaves_nothing_behind(): void {
        $this->rollBack(function () {
            Settings::set('key1', 5);
            $this->user->settings()->set('key2', 6);
        });

        $this->newRequest();
        $this->assertSame(1, Settings::get('key1'));
        $this->assertSame(2, $this->user->settings()->get('key2'));
    }

    public function test_a_committed_transaction_is_cached_again(): void {
        DB::transaction(function () {
            Settings::set('key1', 5);
            $this->assertSame(5, Settings::get('key1'));
        });

        Settings::get('key1');
        $this->newRequest();

        $this->assertSame(0, $this->countQueries(fn () => $this->assertSame(5, Settings::get('key1'))));
    }

    protected function rollBack(callable $callback): void {
        try {
            DB::transaction(function () use ($callback) {
                $callback();

                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
        }
    }

    public function test_manager_and_repository_are_request_scoped(): void {
        $manager = app(SettingsManager::class);
        $repository = app(SettingsRepository::class);
        $this->newRequest();

        $this->assertNotSame($manager, app(SettingsManager::class));
        $this->assertNotSame($repository, app(SettingsRepository::class));
    }
}
