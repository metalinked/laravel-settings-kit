<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Metalinked\LaravelSettingsKit\Events\SettingReset;
use Metalinked\LaravelSettingsKit\Events\SettingUnreadable;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class UnreadableTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        Settings::define('per_page')->default(20)->overridable();
    }

    protected function store(string $key, string $raw): void {
        DB::table('settings_kit_values')->insert(['key' => $key, 'value' => $raw]);
        Settings::flushCache();
    }

    public function test_a_value_that_no_longer_fits_is_reported_once_without_it(): void {
        $this->store('per_page', '"many"');
        Event::fake([SettingUnreadable::class]);
        Log::spy();

        $this->assertSame(20, Settings::get('per_page'));
        $this->assertSame(20, Settings::get('per_page'));

        Event::assertDispatchedTimes(SettingUnreadable::class, 1);
        Event::assertDispatched(SettingUnreadable::class, fn (SettingUnreadable $e) => $e->key === 'per_page'
            && $e->reason === SettingUnreadable::INVALID
            && $e->scope->isGlobal()
            && $e->settable === null);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['key'] === 'per_page'
            && !str_contains((string) json_encode($context), 'many'));
    }

    public function test_it_is_reported_again_in_a_new_request(): void {
        $this->store('per_page', '"many"');
        Event::fake([SettingUnreadable::class]);

        Settings::get('per_page');
        $this->app->forgetScopedInstances();
        Settings::get('per_page');

        Event::assertDispatchedTimes(SettingUnreadable::class, 2);
    }

    public function test_a_global_value_read_through_a_model_is_reported_as_global(): void {
        $this->store('per_page', '"many"');
        Event::fake([SettingUnreadable::class]);
        $user = User::create(['name' => 'Ada']);

        $this->assertSame(20, $user->settings()->get('per_page'));

        Event::assertDispatched(SettingUnreadable::class, fn (SettingUnreadable $e) => $e->scope->isGlobal() && $e->settable === null);
    }

    public function test_an_unreadable_override_falls_back_to_the_global_value(): void {
        $user = User::create(['name' => 'Ada']);
        Settings::set('per_page', 50);
        $user->settings()->set('per_page', 10);
        DB::table('settings_kit_values')->where('settable_type', '!=', '')->update(['value' => '{']);
        Settings::flushCache();
        Event::fake([SettingUnreadable::class]);

        $this->assertSame(50, $user->settings()->get('per_page'));

        Event::assertDispatched(SettingUnreadable::class, fn (SettingUnreadable $e) => !$e->scope->isGlobal() && $e->settable?->is($user));
    }

    public function test_a_plain_value_of_an_encrypted_setting_is_a_decryption_problem(): void {
        Settings::define('api_key')->string()->nullable()->encrypted();
        $this->store('api_key', '"secret"');
        Event::fake([SettingUnreadable::class]);

        $this->assertNull(Settings::get('api_key'));

        Event::assertDispatched(SettingUnreadable::class, fn (SettingUnreadable $e) => $e->reason === SettingUnreadable::DECRYPTION);
    }

    public function test_apply_config_reports_it_too(): void {
        Settings::define('sender')->string()->nullable()->rules('email')->config('mail.from.address');
        $this->store('sender', '[1]');
        Event::fake([SettingUnreadable::class]);

        Settings::applyConfig(['sender']);

        Event::assertDispatched(SettingUnreadable::class);
    }

    public function test_the_warning_can_be_turned_off(): void {
        config(['settings-kit.log_unreadable' => false]);
        $this->store('per_page', '"many"');
        Event::fake([SettingUnreadable::class]);
        Log::spy();

        Settings::get('per_page');

        Log::shouldNotHaveReceived('warning');
        Event::assertDispatched(SettingUnreadable::class);
    }

    public function test_a_listener_may_read_the_setting(): void {
        $this->store('per_page', '"many"');
        $seen = [];
        Event::listen(SettingUnreadable::class, function () use (&$seen) {
            $seen[] = Settings::get('per_page');
        });

        Settings::get('per_page');

        $this->assertSame([20], $seen);
    }

    public function test_change_events_mark_secrets(): void {
        Settings::define('api_key')->string()->nullable()->encrypted();
        Event::fake([SettingUpdated::class, SettingReset::class]);

        Settings::set(['api_key' => 'secret', 'per_page' => 5, 'undefined' => 1]);
        Settings::forget(['api_key', 'per_page']);

        foreach ([SettingUpdated::class, SettingReset::class] as $event) {
            Event::assertDispatched($event, fn ($e) => $e->key === 'api_key' && $e->encrypted);
            Event::assertDispatched($event, fn ($e) => $e->key === 'per_page' && !$e->encrypted);
        }
    }
}
