<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Events\SettingReset;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Exceptions\UndefinedSettingException;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\SettingsManager;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\Theme;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class GlobalSettingsTest extends TestCase {
    protected function defineEnvironment($app): void {
        parent::defineEnvironment($app);

        $app['config']->set('settings-kit.settings', [
            'site_name' => 'My App',
            'maintenance' => false,
            'per_page' => ['type' => 'integer', 'default' => 20, 'rules' => 'min:5|max:100'],
        ]);
    }

    protected function setUp(): void {
        parent::setUp();

        Settings::define('appearance.theme')->enum(Theme::class)->default(Theme::Light);
    }

    public function test_defined_settings_return_their_default_without_any_row(): void {
        $this->assertSame('My App', Settings::get('site_name'));
        $this->assertFalse(Settings::get('maintenance'));
        $this->assertSame(Theme::Light, Settings::get('appearance.theme'));
        $this->assertSame(0, DB::table('settings_kit_values')->count());
    }

    public function test_set_stores_and_get_returns_the_typed_value(): void {
        Settings::set('per_page', '50');
        Settings::set('appearance.theme', 'dark');

        $this->assertSame(50, Settings::get('per_page'));
        $this->assertSame(Theme::Dark, Settings::get('appearance.theme'));
    }

    public function test_set_many_values_at_once(): void {
        Settings::set(['site_name' => 'New', 'maintenance' => '1']);

        $this->assertSame(['site_name' => 'New', 'maintenance' => true], Settings::many(['site_name', 'maintenance']));
        $this->assertTrue(Settings::enabled('maintenance'));
    }

    public function test_invalid_values_throw_a_validation_exception_keyed_by_setting(): void {
        try {
            Settings::set(['site_name' => 'Valid', 'per_page' => 'lots', 'appearance.theme' => 'purple']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['per_page', 'appearance.theme'], array_keys($e->errors()));
        }

        // Nothing is written when any value fails.
        $this->assertSame('My App', Settings::get('site_name'));
    }

    public function test_extra_validation_rules_are_applied(): void {
        $this->expectException(ValidationException::class);

        Settings::set('per_page', 500);
    }

    public function test_forget_reverts_to_the_default(): void {
        Settings::set('site_name', 'Changed');
        $this->assertTrue(Settings::has('site_name'));

        Settings::forget('site_name');

        $this->assertFalse(Settings::has('site_name'));
        $this->assertSame('My App', Settings::get('site_name'));
    }

    public function test_undefined_keys_work_as_a_plain_store_when_not_strict(): void {
        $this->assertSame('fallback', Settings::get('free.key', 'fallback'));

        Settings::set('free.key', ['any' => 'thing']);

        $this->assertSame(['any' => 'thing'], Settings::get('free.key', 'fallback'));
    }

    public function test_strict_mode_rejects_undefined_keys(): void {
        config(['settings-kit.strict' => true]);

        $this->expectException(UndefinedSettingException::class);

        Settings::get('typo_key');
    }

    public function test_all_and_stored(): void {
        Settings::set('per_page', 30);

        $this->assertSame(['per_page' => 30], Settings::stored());
        $this->assertSame([
            'site_name' => 'My App',
            'maintenance' => false,
            'per_page' => 30,
            'appearance.theme' => Theme::Light,
        ], Settings::all());
    }

    public function test_helper(): void {
        $this->assertInstanceOf(SettingsManager::class, settings());
        $this->assertSame('My App', settings('site_name'));
        $this->assertSame('x', settings('missing', 'x'));

        settings(['site_name' => 'Helper']);

        $this->assertSame('Helper', settings('site_name'));
    }

    public function test_blade_directive(): void {
        Settings::set('appearance.theme', Theme::Dark);

        $this->assertSame('My App / dark', Blade::render("@setting('site_name') / @setting('appearance.theme')"));
    }

    public function test_events_carry_new_and_previous_values(): void {
        Event::fake([SettingUpdated::class, SettingReset::class]);

        Settings::set('per_page', 40);
        Settings::forget('per_page');
        Settings::forget('per_page'); // nothing stored: no event

        Event::assertDispatched(SettingUpdated::class, fn ($e) => $e->key === 'per_page'
            && $e->value === 40 && $e->previous === 20 && $e->scope->isGlobal() && $e->settable === null);
        Event::assertDispatchedTimes(SettingReset::class, 1);
        Event::assertDispatched(SettingReset::class, fn ($e) => $e->previous === 40);
    }

    public function test_events_wait_for_the_transaction_to_commit(): void {
        Event::fake([SettingUpdated::class]);

        DB::transaction(function () {
            Settings::set('per_page', 40);
            Event::assertNotDispatched(SettingUpdated::class);
        });

        Event::assertDispatched(SettingUpdated::class);
    }

    public function test_stored_values_that_no_longer_fit_fall_back_to_the_default(): void {
        DB::table('settings_kit_values')->insert([
            'key' => 'per_page', 'settable_type' => '', 'settable_id' => '', 'value' => '"not a number"',
        ]);

        $this->assertSame(20, Settings::get('per_page'));
    }
}
