<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class HttpThrottleTest extends TestCase {
    protected function defineEnvironment($app): void {
        parent::defineEnvironment($app);

        $app['config']->set('settings-kit.http.throttle', '2,1');
    }

    protected function defineRoutes($router): void {
        Route::prefix('api')->group(fn () => Settings::routes());
    }

    public function test_write_endpoints_are_rate_limited_and_reads_are_not(): void {
        Settings::define('appearance.theme')->select(['light', 'dark'])->default('light')->overridable();
        $user = User::create(['name' => 'Anna']);

        $this->actingAs($user)->patchJson('/api/settings/me', ['values' => ['appearance.theme' => 'dark']])->assertOk();
        $this->actingAs($user)->patchJson('/api/settings/me', ['values' => ['appearance.theme' => 'light']])->assertOk();
        $this->actingAs($user)->patchJson('/api/settings/me', ['values' => ['appearance.theme' => 'dark']])->assertStatus(429);

        $this->actingAs($user)->getJson('/api/settings/me')->assertOk();
    }

    public function test_a_published_config_without_the_throttle_key_still_gets_the_default_limit(): void {
        // Configs published before 2.1 have an "http" array without "throttle".
        config(['settings-kit.http' => ['global_ability' => 'manage-settings']]);
        app('router')->setRoutes(new RouteCollection());
        Route::prefix('api')->group(fn () => Settings::routes());

        Settings::define('appearance.theme')->select(['light', 'dark'])->default('light')->overridable();
        $user = User::create(['name' => 'Anna']);

        foreach (range(1, 60) as $i) {
            $this->actingAs($user)->patchJson('/api/settings/me', ['values' => ['appearance.theme' => $i % 2 ? 'dark' : 'light']])->assertOk();
        }

        $this->actingAs($user)->patchJson('/api/settings/me', ['values' => ['appearance.theme' => 'dark']])->assertStatus(429);
    }

    public function test_the_limit_can_be_turned_off(): void {
        config(['settings-kit.http.throttle' => null]);
        app('router')->setRoutes(new RouteCollection());
        Route::prefix('api')->group(fn () => Settings::routes());

        Settings::define('appearance.theme')->select(['light', 'dark'])->default('light')->overridable();
        $user = User::create(['name' => 'Anna']);

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->patchJson('/api/settings/me', ['values' => ['appearance.theme' => $i % 2 ? 'dark' : 'light']])->assertOk();
        }
    }
}
