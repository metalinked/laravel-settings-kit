<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class HttpTest extends TestCase {
    protected User $user;

    protected function defineRoutes($router): void {
        Route::prefix('api')->group(fn () => Settings::routes());
    }

    protected function setUp(): void {
        parent::setUp();

        Settings::define('site_name')->default('My App')->rules('max:20');
        Settings::define('appearance.theme')->select(['light', 'dark'])->default('light')->overridable();
        Settings::define('notifications.email')->default(true)->overridable();

        $this->user = User::create(['name' => 'Anna']);
    }

    public function test_global_endpoints_are_forbidden_until_the_ability_is_defined(): void {
        $this->actingAs($this->user)->getJson('/api/settings')->assertForbidden();
        $this->actingAs($this->user)->patchJson('/api/settings', ['values' => ['site_name' => 'X']])->assertForbidden();
    }

    public function test_global_settings_can_be_read_and_updated_with_the_ability(): void {
        Gate::define('manage-settings', fn () => true);

        $this->actingAs($this->user)->getJson('/api/settings')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.key', 'site_name')
            ->assertJsonPath('data.0.value', 'My App');

        $this->actingAs($this->user)
            ->patchJson('/api/settings', ['values' => ['site_name' => 'Renamed', 'appearance.theme' => 'dark']])
            ->assertOk()
            ->assertJsonPath('data.0.value', 'Renamed')
            ->assertJsonPath('data.0.overridden', true);

        $this->assertSame('dark', Settings::get('appearance.theme'));
    }

    public function test_encrypted_settings_are_write_only(): void {
        Gate::define('manage-settings', fn () => true);
        Settings::define('services.api_key')->string()->nullable()->encrypted();

        $this->actingAs($this->user)
            ->patchJson('/api/settings', ['values' => ['services.api_key' => 'secret-123']])
            ->assertOk()
            ->assertDontSee('secret-123')
            ->assertJsonPath('data.3.key', 'services.api_key')
            ->assertJsonPath('data.3.value', null)
            ->assertJsonPath('data.3.filled', true)
            ->assertJsonPath('data.3.encrypted', true);

        $this->actingAs($this->user)->getJson('/api/settings')->assertOk()->assertDontSee('secret-123');
        $this->assertSame('secret-123', Settings::get('services.api_key'));
    }

    public function test_sending_a_form_back_unchanged_keeps_existing_secrets(): void {
        Gate::define('manage-settings', fn () => true);
        Settings::define('services.api_key')->string()->nullable()->encrypted()->overridable();
        Settings::set('services.api_key', 'global-secret');
        $this->user->settings()->set('services.api_key', 'my-secret');

        // What a client gets from the panel and sends straight back.
        $this->actingAs($this->user)
            ->patchJson('/api/settings', ['values' => ['site_name' => 'Renamed', 'services.api_key' => null]])
            ->assertOk();
        $this->actingAs($this->user)
            ->patchJson('/api/settings/me', ['values' => ['appearance.theme' => 'dark', 'services.api_key' => '']])
            ->assertOk()
            ->assertDontSee('my-secret');

        $this->assertSame('Renamed', Settings::get('site_name'));
        $this->assertSame('global-secret', Settings::get('services.api_key'));
        $this->assertSame('my-secret', $this->user->settings()->get('services.api_key'));

        // Removing a user's secret is explicit.
        $this->actingAs($this->user)->deleteJson('/api/settings/me/services.api_key')->assertOk();
        $this->assertSame('global-secret', $this->user->settings()->get('services.api_key'));
    }

    public function test_group_filter(): void {
        Gate::define('manage-settings', fn () => true);

        $this->actingAs($this->user)->getJson('/api/settings?group=appearance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'appearance.theme');
    }

    public function test_user_endpoints_require_authentication(): void {
        $this->getJson('/api/settings/me')->assertUnauthorized();
    }

    public function test_user_panel_only_lists_overridable_settings(): void {
        $this->actingAs($this->user)->getJson('/api/settings/me')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.key', 'appearance.theme')
            ->assertJsonPath('data.0.options.1', ['value' => 'dark', 'label' => 'Dark']);
    }

    public function test_user_can_update_and_reset_their_settings(): void {
        $this->actingAs($this->user)
            ->patchJson('/api/settings/me', ['values' => ['appearance.theme' => 'dark', 'notifications.email' => false]])
            ->assertOk()
            ->assertJsonPath('data.0.value', 'dark')
            ->assertJsonPath('data.1.value', false);

        $this->assertSame('dark', $this->user->settings()->get('appearance.theme'));
        $this->assertSame('light', Settings::get('appearance.theme'));

        $this->actingAs($this->user)->deleteJson('/api/settings/me/appearance.theme')
            ->assertOk()
            ->assertJsonPath('data.0.overridden', false);

        $this->actingAs($this->user)->deleteJson('/api/settings/me')->assertOk();
        $this->assertSame([], $this->user->settings()->stored());
    }

    public function test_users_cannot_change_non_overridable_or_unknown_settings(): void {
        $this->actingAs($this->user)
            ->patchJson('/api/settings/me', ['values' => ['site_name' => 'Mine', 'nope' => 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['site_name', 'nope']);

        $this->assertSame([], $this->user->settings()->stored());
    }

    public function test_invalid_values_are_rejected_with_422(): void {
        Gate::define('manage-settings', fn () => true);

        $this->actingAs($this->user)
            ->patchJson('/api/settings', ['values' => ['site_name' => str_repeat('x', 30), 'appearance.theme' => 'pink']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['site_name', 'appearance.theme']);
    }

    public function test_values_must_be_an_object(): void {
        $this->actingAs($this->user)->patchJson('/api/settings/me', ['values' => ['a', 'b']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values']);
    }
}
