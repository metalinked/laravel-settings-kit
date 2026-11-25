<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Events\SettingReset;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;
use RuntimeException;

class FillTest extends TestCase {
    protected User $user;

    protected function setUp(): void {
        parent::setUp();

        Settings::define('site_name')->default('My App')->rules('max:20');
        Settings::define('items')->integer()->default(20)->rules('min:5');
        Settings::define('mail.from')->string()->nullable()->default('hello@example.com')->overridable();
        Settings::define('mail.password')->string()->nullable()->encrypted()->overridable();
        Settings::define('api.token')->string()->default('')->encrypted();
        Settings::define('enabled')->default(true);

        $this->user = User::create(['name' => 'Anna']);
    }

    public function test_a_form_is_saved_in_one_call(): void {
        Settings::fill(['site_name' => 'Acme', 'items' => '50', 'enabled' => '0']);

        $this->assertSame('Acme', Settings::get('site_name'));
        $this->assertSame(50, Settings::get('items'));
        $this->assertFalse(Settings::get('enabled'));
    }

    public function test_keys_that_are_not_sent_are_left_alone(): void {
        Settings::set(['site_name' => 'Acme', 'items' => 50]);

        Settings::fill(['items' => 60]);

        $this->assertSame('Acme', Settings::get('site_name'));
    }

    public function test_an_empty_field_keeps_an_encrypted_value(): void {
        Settings::set(['mail.password' => 'secret', 'api.token' => 'token']);

        Settings::fill(['mail.password' => null, 'api.token' => '']);

        $this->assertSame('secret', Settings::get('mail.password'));
        $this->assertSame('token', Settings::get('api.token'));
    }

    public function test_an_empty_field_removes_the_value_of_a_nullable_setting(): void {
        Settings::set('mail.from', 'acme@example.com');

        Settings::fill(['mail.from' => '']);

        $this->assertFalse(Settings::has('mail.from'));
        $this->assertSame('hello@example.com', Settings::get('mail.from'));

        Settings::fill(['mail.from' => null]);
        $this->assertFalse(Settings::has('mail.from'));
    }

    public function test_a_model_falls_back_to_the_global_value(): void {
        Settings::set('mail.from', 'global@example.com');
        $this->user->settings()->fill(['mail.from' => 'anna@example.com']);
        $this->assertSame('anna@example.com', $this->user->settings()->get('mail.from'));

        $this->user->settings()->fill(['mail.from' => '']);

        $this->assertSame('global@example.com', $this->user->settings()->get('mail.from'));
    }

    public function test_an_empty_string_is_stored_for_a_non_nullable_string(): void {
        Settings::fill(['site_name' => '']);

        $this->assertTrue(Settings::has('site_name'));
        $this->assertSame('', Settings::get('site_name'));
    }

    public function test_nothing_is_written_when_one_field_is_invalid(): void {
        Settings::set('mail.from', 'acme@example.com');

        try {
            Settings::fill(['site_name' => 'Acme', 'mail.from' => '', 'items' => 1]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['items'], array_keys($e->errors()));
        }

        $this->assertFalse(Settings::has('site_name'));
        $this->assertSame('acme@example.com', Settings::get('mail.from'));
    }

    public function test_unknown_keys_are_rejected_even_without_strict_mode(): void {
        try {
            Settings::fill(['_token' => 'abc', 'site_name' => 'Acme']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(["Unknown setting '_token'."], $e->errors()['_token']);
        }

        $this->assertSame([], Settings::stored());
    }

    public function test_a_model_cannot_fill_or_empty_a_setting_it_may_not_override(): void {
        foreach (['Acme', ''] as $value) {
            try {
                $this->user->settings()->fill(['site_name' => $value]);
                $this->fail('Expected a ValidationException.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('site_name', $e->errors());
            }
        }

        $this->assertSame([], $this->user->settings()->stored());
    }

    public function test_it_dispatches_the_events_of_what_changed(): void {
        Settings::set(['mail.from' => 'acme@example.com', 'mail.password' => 'secret']);
        Event::fake([SettingUpdated::class, SettingReset::class]);

        Settings::fill(['site_name' => 'Acme', 'mail.from' => '', 'mail.password' => '']);

        Event::assertDispatched(SettingUpdated::class, 1);
        Event::assertDispatched(SettingReset::class, fn (SettingReset $e) => $e->key === 'mail.from' && $e->previous === 'acme@example.com');
        Event::assertDispatched(SettingReset::class, 1);
    }

    public function test_it_is_undone_with_the_surrounding_transaction(): void {
        Settings::set('mail.from', 'acme@example.com');

        try {
            DB::transaction(function () {
                Settings::fill(['site_name' => 'Acme', 'mail.from' => '']);

                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
        }

        $this->app->forgetScopedInstances();
        $this->assertSame('My App', Settings::get('site_name'));
        $this->assertSame('acme@example.com', Settings::get('mail.from'));
    }

    public function test_an_empty_array_is_accepted(): void {
        Settings::fill([]);

        $this->assertSame([], Settings::stored());
    }
}
