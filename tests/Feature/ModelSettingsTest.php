<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Exceptions\SettingNotOverridableException;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\Team;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class ModelSettingsTest extends TestCase {
    protected User $user;

    protected function setUp(): void {
        parent::setUp();

        Settings::define('site_name')->default('My App');
        Settings::define('theme')->select(['light', 'dark'])->default('light')->overridable();
        Settings::define('locale')->default('en')->overridable(User::class);
        Settings::define('notifications.email')->default(true)->overridable();

        $this->user = User::create(['name' => 'Anna']);
    }

    public function test_model_inherits_the_default_then_the_global_value(): void {
        $this->assertSame('light', $this->user->settings()->get('theme'));

        Settings::set('theme', 'dark');

        $this->assertSame('dark', $this->user->settings()->get('theme'));
        $this->assertFalse($this->user->settings()->has('theme'));
    }

    public function test_model_override_wins_and_forget_restores_inheritance(): void {
        Settings::set('theme', 'dark');
        $this->user->settings()->set('theme', 'light');

        $this->assertSame('light', $this->user->settings()->get('theme'));
        $this->assertSame('dark', Settings::get('theme'));

        $this->user->settings()->forget('theme');

        $this->assertSame('dark', $this->user->settings()->get('theme'));
    }

    public function test_overrides_are_isolated_per_model(): void {
        $other = User::create(['name' => 'Bernat']);

        $this->user->settings()->set('theme', 'dark');

        $this->assertSame('light', $other->settings()->get('theme'));
        $this->assertSame('light', Settings::for($other)->get('theme'));
    }

    public function test_non_overridable_settings_cannot_be_set_on_a_model(): void {
        $this->expectException(SettingNotOverridableException::class);

        $this->user->settings()->set('site_name', 'Mine');
    }

    public function test_non_overridable_settings_ignore_stale_override_rows(): void {
        DB::table('settings_kit_values')->insert([
            'key' => 'site_name', 'settable_type' => $this->user->getMorphClass(),
            'settable_id' => (string) $this->user->id, 'value' => '"Old override"',
        ]);

        $this->assertSame('My App', $this->user->settings()->get('site_name'));
    }

    public function test_overridable_can_be_restricted_to_model_classes(): void {
        $team = Team::create(['name' => 'Core']);

        $this->user->settings()->set('locale', 'ca');
        $this->assertSame('ca', $this->user->settings()->get('locale'));

        $this->expectException(SettingNotOverridableException::class);
        $team->settings()->set('locale', 'es');
    }

    public function test_uuid_models_are_supported(): void {
        $team = Team::create(['name' => 'Core']);

        $team->settings()->set('theme', 'dark');

        $this->assertSame('dark', Team::find($team->id)->settings()->get('theme'));
    }

    public function test_scope_by_morph_type_and_id_uses_the_morph_map(): void {
        Relation::morphMap(['user' => User::class]);

        try {
            Settings::for('user', $this->user->id)->set('locale', 'fr');

            $this->assertSame('fr', $this->user->settings()->get('locale'));
            $this->assertSame('user', DB::table('settings_kit_values')->value('settable_type'));
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function test_unsaved_models_cannot_hold_settings(): void {
        $this->expectException(InvalidArgumentException::class);

        (new User())->settings();
    }

    public function test_stored_and_reset(): void {
        $this->user->settings()->set(['theme' => 'dark', 'notifications.email' => false]);

        $this->assertSame(['notifications.email' => false, 'theme' => 'dark'], $this->user->settings()->stored());
        $this->assertSame(2, $this->user->settings()->reset());
        $this->assertSame([], $this->user->settings()->stored());
    }

    public function test_all_resolves_every_setting_for_the_model(): void {
        Settings::set('site_name', 'Global name');
        $this->user->settings()->set('theme', 'dark');

        $this->assertSame([
            'site_name' => 'Global name',
            'theme' => 'dark',
            'locale' => 'en',
            'notifications.email' => true,
        ], $this->user->settings()->all());
    }

    public function test_events_include_the_model(): void {
        Event::fake([SettingUpdated::class]);

        $this->user->settings()->set('theme', 'dark');

        Event::assertDispatched(SettingUpdated::class, fn ($e) => $e->settable?->is($this->user)
            && $e->scope->id === (string) $this->user->id);
    }

    public function test_deleting_a_model_removes_its_overrides(): void {
        $this->user->settings()->set('theme', 'dark');

        $this->user->delete();

        $this->assertSame(0, DB::table('settings_kit_values')->count());
    }

    public function test_soft_deleting_keeps_overrides_until_force_delete(): void {
        $team = Team::create(['name' => 'Core']);
        $team->settings()->set('theme', 'dark');

        $team->delete();
        $this->assertSame(1, DB::table('settings_kit_values')->count());

        $team->forceDelete();
        $this->assertSame(0, DB::table('settings_kit_values')->count());
    }

    public function test_panel_lists_only_what_the_model_may_change(): void {
        Settings::set('theme', 'dark');
        $this->user->settings()->set('notifications.email', false);

        $panel = collect($this->user->settings()->panel())->keyBy('key');

        $this->assertSame(['theme', 'locale', 'notifications.email'], $panel->keys()->all());
        $this->assertSame([
            'key' => 'theme',
            'group' => 'general',
            'type' => 'select',
            'label' => 'Theme',
            'description' => null,
            'value' => 'dark',
            'default' => 'dark',
            'filled' => true,
            'overridden' => false,
            'encrypted' => false,
            'nullable' => false,
            'options' => [['value' => 'light', 'label' => 'Light'], ['value' => 'dark', 'label' => 'Dark']],
            'meta' => [],
        ], $panel['theme']);
        $this->assertTrue($panel['notifications.email']['overridden']);
        $this->assertSame(['notifications.email'], array_column($this->user->settings()->panel('notifications'), 'key'));
    }

    public function test_global_panel_lists_every_definition(): void {
        $this->assertCount(4, Settings::panel());
    }

    public function test_panel_never_returns_encrypted_values(): void {
        Settings::define('api_token')->string()->nullable()->encrypted()->overridable();
        Settings::set('api_token', 'global-secret');

        $global = collect(Settings::panel())->keyBy('key')['api_token'];
        $this->assertNull($global['value']);
        $this->assertTrue($global['filled']);
        $this->assertTrue($global['encrypted']);

        // The user inherits the global secret: neither value nor fallback may leak.
        $mine = collect($this->user->settings()->panel())->keyBy('key')['api_token'];
        $this->assertNull($mine['value']);
        $this->assertNull($mine['default']);
        $this->assertTrue($mine['filled']);
        $this->assertSame('global-secret', $this->user->settings()->get('api_token'));
    }
}
