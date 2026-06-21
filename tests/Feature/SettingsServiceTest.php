<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Metalinked\LaravelSettingsKit\Events\SettingUpdated;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class SettingsServiceTest extends TestCase {
    public function test_can_create_preference() {
        $preference = Settings::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'general',
            'is_user_customizable' => false,
        ]);

        $this->assertInstanceOf(Preference::class, $preference);
        $this->assertEquals('test_setting', $preference->key);
        $this->assertEquals('boolean', $preference->type);
        $this->assertFalse($preference->is_user_customizable);
        $this->assertTrue(Settings::exists('test_setting'));
        $this->assertTrue(Settings::has('test_setting')); // Test alias
    }

    public function test_has_method_works() {
        $this->assertFalse(Settings::has('nonexistent_setting'));

        Settings::create([
            'key' => 'existing_setting',
            'type' => 'string',
            'default_value' => 'test',
        ]);

        $this->assertTrue(Settings::has('existing_setting'));
    }

    public function test_create_if_not_exists() {
        // Should create since it doesn't exist
        $preference = Settings::createIfNotExists('new_setting', [
            'type' => 'boolean',
            'default_value' => true,
            'category' => 'test',
        ]);

        $this->assertInstanceOf(Preference::class, $preference);
        $this->assertEquals('new_setting', $preference->key);

        // Should return null since it already exists
        $result = Settings::createIfNotExists('new_setting', [
            'type' => 'string',
            'default_value' => 'different',
        ]);

        $this->assertNull($result);
    }

    public function test_set_with_auto_create() {
        $this->assertFalse(Settings::has('auto_created_setting'));

        // This should create the preference automatically
        Settings::setWithAutoCreate('auto_created_setting', true);

        $this->assertTrue(Settings::has('auto_created_setting'));
        $this->assertTrue(Settings::get('auto_created_setting'));
    }

    public function test_set_with_auto_create_different_types() {
        // Boolean
        Settings::setWithAutoCreate('bool_setting', false);
        $this->assertEquals('boolean', Preference::where('key', 'bool_setting')->first()->type);

        // Integer
        Settings::setWithAutoCreate('int_setting', 42);
        $this->assertEquals('integer', Preference::where('key', 'int_setting')->first()->type);

        // Array (JSON)
        Settings::setWithAutoCreate('array_setting', ['key' => 'value']);
        $this->assertEquals('json', Preference::where('key', 'array_setting')->first()->type);

        // String
        Settings::setWithAutoCreate('string_setting', 'test');
        $this->assertEquals('string', Preference::where('key', 'string_setting')->first()->type);
    }

    public function test_set_throws_exception_when_preference_not_found() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Preference with key 'nonexistent' not found");

        Settings::set('nonexistent', 'value');
    }

    public function test_set_with_auto_create_parameter() {
        $this->assertFalse(Settings::has('param_test'));

        // Should create when autoCreate is true
        Settings::set('param_test', 'value', null, true);

        $this->assertTrue(Settings::has('param_test'));
        $this->assertEquals('value', Settings::get('param_test'));
    }

    public function test_create_with_translations() {
        $preference = Settings::createWithTranslations('multilingual_setting', [
            'type' => 'string',
            'default_value' => 'test',
            'category' => 'test',
        ], [
            'en' => ['title' => 'English Title', 'description' => 'English description'],
            'es' => ['title' => 'Título Español', 'description' => 'Descripción en español'],
            'ca' => ['title' => 'Títol Català', 'description' => 'Descripció en català'],
        ]);

        $this->assertInstanceOf(\Metalinked\LaravelSettingsKit\Models\Preference::class, $preference);
        $this->assertEquals('multilingual_setting', $preference->key);

        // Check translations were created
        $this->assertEquals('English Title', Settings::label('multilingual_setting', 'en'));
        $this->assertEquals('Título Español', Settings::label('multilingual_setting', 'es'));
        $this->assertEquals('Títol Català', Settings::label('multilingual_setting', 'ca'));

        $this->assertEquals('English description', Settings::description('multilingual_setting', 'en'));
        $this->assertEquals('Descripción en español', Settings::description('multilingual_setting', 'es'));
        $this->assertEquals('Descripció en català', Settings::description('multilingual_setting', 'ca'));
    }

    public function test_add_translations_to_existing_setting() {
        // Create setting without translations
        Settings::create([
            'key' => 'existing_setting',
            'type' => 'string',
            'default_value' => 'test',
        ]);

        // Add translations
        Settings::addTranslations('existing_setting', [
            'en' => ['title' => 'Added English', 'description' => 'Added English description'],
            'fr' => ['title' => 'Titre Français', 'description' => 'Description française'],
        ]);

        $this->assertEquals('Added English', Settings::label('existing_setting', 'en'));
        $this->assertEquals('Titre Français', Settings::label('existing_setting', 'fr'));
        $this->assertEquals('Added English description', Settings::description('existing_setting', 'en'));
    }

    public function test_all_with_translations() {
        // Create a setting with translations
        Settings::createWithTranslations('test_multilingual', [
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'test',
        ], [
            'en' => ['title' => 'Test Setting', 'description' => 'Test description'],
        ]);

        $settings = Settings::allWithTranslations('en');

        $this->assertArrayHasKey('test_multilingual', $settings);
        $this->assertEquals('Test Setting', $settings['test_multilingual']['label']);
        $this->assertEquals('Test description', $settings['test_multilingual']['description']);
        $this->assertEquals('test_multilingual', $settings['test_multilingual']['key']);
    }

    public function test_add_translations_to_nonexistent_setting_throws_exception() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Preference with key 'nonexistent' not found");

        Settings::addTranslations('nonexistent', [
            'en' => ['title' => 'Title', 'description' => 'Description'],
        ]);
    }

    public function test_can_get_global_setting() {
        Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        $value = Settings::get('allow_comments');
        $this->assertTrue($value);
    }

    public function test_can_set_global_setting() {
        Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        Settings::set('allow_comments', false);

        $value = Settings::get('allow_comments');
        $this->assertFalse($value);
    }

    public function test_can_get_user_specific_setting() {
        $preference = Preference::create([
            'key' => 'email_notifications',
            'type' => 'boolean',
            'default_value' => '1',
            'is_user_customizable' => true, // Allow user customization
        ]);

        // User specific value
        Settings::set('email_notifications', false, 1);

        // Check user value
        $userValue = Settings::get('email_notifications', 1);
        $this->assertFalse($userValue);

        // Check global default
        $globalValue = Settings::get('email_notifications');
        $this->assertTrue($globalValue);
    }

    public function test_is_enabled_method() {
        Preference::create([
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'default_value' => '0',
        ]);

        $this->assertFalse(Settings::isEnabled('maintenance_mode'));

        Settings::set('maintenance_mode', true);
        $this->assertTrue(Settings::isEnabled('maintenance_mode'));
    }

    public function test_can_get_translated_label() {
        $preference = Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'locale' => 'en',
            'title' => 'Allow Comments',
            'text' => 'Enable or disable comments',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'locale' => 'es',
            'title' => 'Permitir Comentarios',
            'text' => 'Activar o desactivar comentarios',
        ]);

        $this->assertEquals('Allow Comments', Settings::label('allow_comments', 'en'));
        $this->assertEquals('Permitir Comentarios', Settings::label('allow_comments', 'es'));
    }

    public function test_can_get_translated_description() {
        $preference = Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'locale' => 'en',
            'title' => 'Allow Comments',
            'text' => 'Enable or disable comments on posts',
        ]);

        $this->assertEquals('Enable or disable comments on posts', Settings::description('allow_comments', 'en'));
    }

    public function test_can_get_all_settings() {
        Preference::create([
            'key' => 'setting1',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'general',
        ]);

        Preference::create([
            'key' => 'setting2',
            'type' => 'string',
            'default_value' => 'test',
            'category' => 'general',
        ]);

        $settings = Settings::all();

        $this->assertCount(2, $settings);
        $this->assertArrayHasKey('setting1', $settings);
        $this->assertArrayHasKey('setting2', $settings);
        $this->assertTrue($settings['setting1']['value']);
        $this->assertEquals('test', $settings['setting2']['value']);
    }

    public function test_can_get_settings_by_category() {
        Preference::create([
            'key' => 'general_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'general',
        ]);

        Preference::create([
            'key' => 'notification_setting',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'notifications',
        ]);

        $generalSettings = Settings::getByCategory('general');
        $notificationSettings = Settings::getByCategory('notifications');

        $this->assertCount(1, $generalSettings);
        $this->assertCount(1, $notificationSettings);
        $this->assertArrayHasKey('general_setting', $generalSettings);
        $this->assertArrayHasKey('notification_setting', $notificationSettings);
    }

    public function test_can_forget_setting() {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'is_user_customizable' => true, // Allow user customization
        ]);

        // Set user value
        Settings::set('test_setting', false, 1);
        $this->assertFalse(Settings::get('test_setting', 1));

        // Forget user value
        Settings::forget('test_setting', 1);

        // Should return default value now
        $this->assertTrue(Settings::get('test_setting', 1));
    }

    public function test_handles_different_data_types() {
        // String
        Preference::create([
            'key' => 'string_setting',
            'type' => 'string',
            'default_value' => 'hello',
        ]);

        // Integer
        Preference::create([
            'key' => 'integer_setting',
            'type' => 'integer',
            'default_value' => '42',
        ]);

        // JSON
        Preference::create([
            'key' => 'json_setting',
            'type' => 'json',
            'default_value' => json_encode(['key' => 'value']),
        ]);

        $this->assertEquals('hello', Settings::get('string_setting'));
        $this->assertEquals(42, Settings::get('integer_setting'));
        $this->assertEquals(['key' => 'value'], Settings::get('json_setting'));
    }

    public function test_role_based_settings() {
        // Global setting
        Preference::create([
            'key' => 'global_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'role' => null,
        ]);

        // Admin only setting
        Preference::create([
            'key' => 'admin_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'role' => 'admin',
        ]);

        $globalSettings = Settings::all();
        $adminSettings = Settings::all('admin');

        // Global should only include global settings
        $this->assertCount(1, $globalSettings);
        $this->assertArrayHasKey('global_setting', $globalSettings);

        // Admin should include both global and admin settings
        $this->assertCount(2, $adminSettings);
        $this->assertArrayHasKey('global_setting', $adminSettings);
        $this->assertArrayHasKey('admin_setting', $adminSettings);
    }

    public function test_global_unique_setting_modifies_default_value_directly() {
        // Create a global unique setting (not user customizable)
        Settings::create([
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'default_value' => '0',
            'is_user_customizable' => false,
        ]);

        // Initially returns default
        $this->assertEquals('0', Settings::get('maintenance_mode'));

        // Set global value - should modify default_value directly
        Settings::set('maintenance_mode', '1');

        // Check that default_value in preferences table was modified
        $preference = Preference::where('key', 'maintenance_mode')->first();
        $this->assertEquals('1', $preference->default_value);

        // Verify get returns the new value
        $this->assertEquals('1', Settings::get('maintenance_mode'));

        // Verify no UserPreference was created
        $this->assertEquals(0, $preference->userPreferences()->count());
    }

    public function test_user_customizable_setting_uses_simple_default_modification() {
        // Create a user customizable setting
        Settings::create([
            'key' => 'theme',
            'type' => 'string',
            'default_value' => 'light',
            'is_user_customizable' => true,
        ]);

        // Initially returns default
        $this->assertEquals('light', Settings::get('theme'));

        // Set global value - should modify default_value directly
        Settings::set('theme', 'dark');

        // Check that default_value was modified
        $preference = Preference::where('key', 'theme')->first();
        $this->assertEquals('dark', $preference->default_value);

        // All users without custom values should see the new default
        $this->assertEquals('dark', Settings::get('theme'));
        $this->assertEquals('dark', Settings::get('theme', 456)); // Any user without customization

        // Verify no UserPreference with null user_id was created
        $this->assertEquals(0, $preference->userPreferences()->whereNull('user_id')->count());

        // User can still customize (this creates UserPreference)
        Settings::set('theme', 'custom', 123);
        $this->assertEquals('custom', Settings::get('theme', 123));  // User's custom value
        $this->assertEquals('dark', Settings::get('theme'));         // Global default unchanged
        $this->assertEquals('dark', Settings::get('theme', 456));    // Other users get global default

        // Verify UserPreference was created for the user
        $this->assertEquals(1, $preference->userPreferences()->where('user_id', 123)->count());
    }

    public function test_cannot_set_user_specific_value_for_global_unique_setting() {
        // Create a global unique setting
        Settings::create([
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'default_value' => '0',
            'is_user_customizable' => false,
        ]);

        // Should throw exception when trying to set user-specific value
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot set user-specific value for global unique setting 'maintenance_mode'");

        Settings::set('maintenance_mode', '1', 123);
    }

    public function test_user_customizable_setting_reset_functionality() {
        // Create user customizable setting
        Settings::create([
            'key' => 'notifications',
            'type' => 'boolean',
            'default_value' => '1',
            'is_user_customizable' => true,
        ]);

        // Change global default
        Settings::set('notifications', '0');
        $this->assertEquals('0', Settings::get('notifications'));

        // User customizes
        Settings::set('notifications', '1', 123);
        $this->assertEquals('1', Settings::get('notifications', 123));  // User's custom value
        $this->assertEquals('0', Settings::get('notifications'));        // Global default

        // Reset user preference
        Settings::forget('notifications', 123);
        $this->assertEquals('0', Settings::get('notifications', 123)); // User now gets global default
    }

    public function test_auto_create_user_customizable_setting() {
        $userId = 1;
        $key = 'user_theme';

        $this->assertNull(\Metalinked\LaravelSettingsKit\Models\Preference::where('key', $key)->first());

        \Metalinked\LaravelSettingsKit\Facades\Settings::set($key, 'dark', $userId, true);

        $preference = \Metalinked\LaravelSettingsKit\Models\Preference::where('key', $key)->first();
        $this->assertNotNull($preference);
        $this->assertTrue($preference->is_user_customizable);
    }

    public function test_get_multiple_settings() {
        Preference::create(['key' => 'multi_a', 'type' => 'string', 'default_value' => 'alpha']);
        Preference::create(['key' => 'multi_b', 'type' => 'integer', 'default_value' => '42']);
        Preference::create(['key' => 'multi_c', 'type' => 'boolean', 'default_value' => '1']);

        $result = Settings::getMultiple(['multi_a', 'multi_b', 'multi_c', 'nonexistent']);

        $this->assertEquals('alpha', $result['multi_a']);
        $this->assertEquals(42, $result['multi_b']);
        $this->assertTrue($result['multi_c']);
        $this->assertNull($result['nonexistent']);
    }

    public function test_get_multiple_with_user_override() {
        Preference::create(['key' => 'multi_user', 'type' => 'string', 'default_value' => 'global', 'is_user_customizable' => true]);

        Settings::set('multi_user', 'personal', 7);

        $result = Settings::getMultiple(['multi_user'], 7);
        $this->assertEquals('personal', $result['multi_user']);

        $result = Settings::getMultiple(['multi_user']);
        $this->assertEquals('global', $result['multi_user']);
    }

    public function test_remember_returns_existing_value() {
        Preference::create(['key' => 'existing_key', 'type' => 'string', 'default_value' => 'stored']);

        $value = Settings::remember('existing_key', 'fallback');

        $this->assertEquals('stored', $value);
        $this->assertEquals('string', Preference::where('key', 'existing_key')->first()->type);
    }

    public function test_remember_creates_and_returns_default_when_missing() {
        $this->assertFalse(Settings::has('new_remember_key'));

        $value = Settings::remember('new_remember_key', 'created_default');

        $this->assertEquals('created_default', $value);
        $this->assertTrue(Settings::has('new_remember_key'));
        $this->assertEquals('created_default', Settings::get('new_remember_key'));
    }

    public function test_delete_removes_preference_and_cascades() {
        $preference = Preference::create([
            'key' => 'to_delete',
            'type' => 'string',
            'default_value' => 'bye',
            'is_user_customizable' => true,
        ]);
        Settings::set('to_delete', 'custom', 1);

        $this->assertTrue(Settings::delete('to_delete'));
        $this->assertFalse(Settings::has('to_delete'));
        $this->assertEquals(0, \Metalinked\LaravelSettingsKit\Models\UserPreference::where('preference_id', $preference->id)->count());
    }

    public function test_delete_returns_false_for_nonexistent_key() {
        $this->assertFalse(Settings::delete('does_not_exist'));
    }

    public function test_count_returns_total_settings() {
        Preference::create(['key' => 'count_a', 'type' => 'string', 'default_value' => 'x', 'category' => 'cat1']);
        Preference::create(['key' => 'count_b', 'type' => 'string', 'default_value' => 'y', 'category' => 'cat2']);
        Preference::create(['key' => 'count_c', 'type' => 'string', 'default_value' => 'z', 'category' => 'cat1']);

        $this->assertEquals(3, Settings::count());
        $this->assertEquals(2, Settings::count('cat1'));
        $this->assertEquals(1, Settings::count('cat2'));
    }

    public function test_all_supports_category_filter() {
        Preference::create(['key' => 'sys_setting', 'type' => 'string', 'default_value' => 'x', 'category' => 'system']);
        Preference::create(['key' => 'gen_setting', 'type' => 'string', 'default_value' => 'y', 'category' => 'general']);

        $system = Settings::all(null, null, 'system');

        $this->assertArrayHasKey('sys_setting', $system);
        $this->assertArrayNotHasKey('gen_setting', $system);
    }

    public function test_set_global_stores_prepared_value() {
        Preference::create(['key' => 'bool_global', 'type' => 'boolean', 'default_value' => '0']);

        Settings::set('bool_global', true);

        $stored = Preference::where('key', 'bool_global')->first();
        $this->assertEquals('1', $stored->default_value);

        Settings::set('bool_global', false);
        $stored->refresh();
        $this->assertEquals('0', $stored->default_value);
    }

    public function test_set_dispatches_setting_updated_event() {
        Event::fake();

        Preference::create(['key' => 'event_setting', 'type' => 'string', 'default_value' => 'old']);

        Settings::set('event_setting', 'new_value');

        Event::assertDispatched(SettingUpdated::class, function ($event) {
            return $event->key === 'event_setting'
                && $event->value === 'new_value'
                && $event->userId === null;
        });
    }

    public function test_set_user_dispatches_setting_updated_event_with_user_id() {
        Event::fake();

        Preference::create(['key' => 'user_event', 'type' => 'string', 'default_value' => 'default', 'is_user_customizable' => true]);

        Settings::set('user_event', 'user_value', 42);

        Event::assertDispatched(SettingUpdated::class, function ($event) {
            return $event->key === 'user_event'
                && $event->value === 'user_value'
                && $event->userId === 42;
        });
    }

    public function test_forget_resets_user_value_to_global_default() {
        Preference::create(['key' => 'forget_test', 'type' => 'string', 'default_value' => 'default', 'is_user_customizable' => true]);
        Settings::set('forget_test', 'custom', 5);

        $this->assertEquals('custom', Settings::get('forget_test', 5));

        Settings::forget('forget_test', 5);

        $this->assertEquals('default', Settings::get('forget_test', 5));
    }

    public function test_forget_without_user_id_has_no_effect() {
        Preference::create(['key' => 'global_forget', 'type' => 'string', 'default_value' => 'value']);

        Settings::forget('global_forget');

        $this->assertEquals('value', Settings::get('global_forget'));
    }

    public function test_cannot_set_null_as_user_override() {
        Preference::create(['key' => 'null_test', 'type' => 'string', 'default_value' => 'default', 'is_user_customizable' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot store null");

        Settings::set('null_test', null, 1);
    }

    public function test_select_validation_rejects_invalid_value() {
        Preference::create([
            'key' => 'select_test',
            'type' => 'select',
            'default_value' => 'light',
            'options' => ['light', 'dark', 'auto'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid value for select setting");

        Settings::set('select_test', 'blue');
    }

    public function test_select_validation_allows_valid_value() {
        Preference::create([
            'key' => 'select_valid',
            'type' => 'select',
            'default_value' => 'light',
            'options' => ['light', 'dark', 'auto'],
        ]);

        Settings::set('select_valid', 'dark');
        $this->assertEquals('dark', Settings::get('select_valid'));
    }

    public function test_forget_dispatches_setting_updated_event() {
        Event::fake();

        Preference::create(['key' => 'forget_event', 'type' => 'string', 'default_value' => 'x', 'is_user_customizable' => true]);
        Settings::set('forget_event', 'custom', 5);

        Settings::forget('forget_event', 5);

        Event::assertDispatched(SettingUpdated::class, function ($event) {
            return $event->key === 'forget_event' && $event->value === null && $event->userId === 5;
        });
    }

    public function test_forget_without_user_dispatches_no_event() {
        Event::fake();

        Preference::create(['key' => 'forget_no_event', 'type' => 'string', 'default_value' => 'x']);

        Settings::forget('forget_no_event');

        Event::assertNotDispatched(SettingUpdated::class);
    }

    public function test_set_multiple_updates_several_settings() {
        Preference::create(['key' => 'batch_a', 'type' => 'string', 'default_value' => 'old', 'is_user_customizable' => true]);
        Preference::create(['key' => 'batch_b', 'type' => 'integer', 'default_value' => '0', 'is_user_customizable' => true]);

        Settings::setMultiple(['batch_a' => 'new', 'batch_b' => 42], 1);

        $this->assertEquals('new', Settings::get('batch_a', 1));
        $this->assertEquals(42, Settings::get('batch_b', 1));
    }

    public function test_all_for_user_returns_resolved_values_with_override_flag() {
        Preference::create(['key' => 'user_pref_a', 'type' => 'string', 'default_value' => 'global_a', 'is_user_customizable' => true]);
        Preference::create(['key' => 'user_pref_b', 'type' => 'string', 'default_value' => 'global_b', 'is_user_customizable' => true]);
        Preference::create(['key' => 'non_customizable', 'type' => 'string', 'default_value' => 'x', 'is_user_customizable' => false]);

        Settings::set('user_pref_a', 'custom_a', 7);

        $result = Settings::allForUser(7);

        // Only user-customisable settings appear
        $this->assertArrayHasKey('user_pref_a', $result);
        $this->assertArrayHasKey('user_pref_b', $result);
        $this->assertArrayNotHasKey('non_customizable', $result);

        // Overridden setting
        $this->assertEquals('custom_a', $result['user_pref_a']['value']);
        $this->assertTrue($result['user_pref_a']['is_overridden']);

        // Non-overridden falls back to global default
        $this->assertEquals('global_b', $result['user_pref_b']['value']);
        $this->assertFalse($result['user_pref_b']['is_overridden']);
    }

    public function test_get_user_overrides_returns_only_overridden_settings() {
        Preference::create(['key' => 'override_a', 'type' => 'string', 'default_value' => 'default', 'is_user_customizable' => true]);
        Preference::create(['key' => 'override_b', 'type' => 'string', 'default_value' => 'default', 'is_user_customizable' => true]);

        Settings::set('override_a', 'custom', 3);

        $overrides = Settings::getUserOverrides(3);

        $this->assertArrayHasKey('override_a', $overrides);
        $this->assertEquals('custom', $overrides['override_a']);
        $this->assertArrayNotHasKey('override_b', $overrides);
    }

    public function test_forget_all_removes_all_user_overrides() {
        Preference::create(['key' => 'fa_a', 'type' => 'string', 'default_value' => 'global', 'is_user_customizable' => true]);
        Preference::create(['key' => 'fa_b', 'type' => 'string', 'default_value' => 'global', 'is_user_customizable' => true]);

        Settings::set('fa_a', 'custom', 9);
        Settings::set('fa_b', 'custom', 9);

        $count = Settings::forgetAll(9);

        $this->assertEquals(2, $count);
        $this->assertEquals('global', Settings::get('fa_a', 9));
        $this->assertEquals('global', Settings::get('fa_b', 9));
        $this->assertEmpty(Settings::getUserOverrides(9));
    }

    public function test_forget_all_dispatches_events_for_each_override() {
        Event::fake();

        Preference::create(['key' => 'fa_event_a', 'type' => 'string', 'default_value' => 'x', 'is_user_customizable' => true]);
        Preference::create(['key' => 'fa_event_b', 'type' => 'string', 'default_value' => 'y', 'is_user_customizable' => true]);

        Settings::set('fa_event_a', 'custom', 11);
        Settings::set('fa_event_b', 'custom', 11);

        Event::fake();
        Settings::forgetAll(11);

        Event::assertDispatched(SettingUpdated::class, 2);
    }

    public function test_get_by_category_includes_category_and_key_fields() {
        Preference::create(['key' => 'cat_key', 'type' => 'string', 'default_value' => 'val', 'category' => 'testcat']);

        $result = Settings::getByCategory('testcat');

        $this->assertArrayHasKey('cat_key', $result);
        $this->assertArrayHasKey('category', $result['cat_key']);
        $this->assertArrayHasKey('key', $result['cat_key']);
        $this->assertEquals('testcat', $result['cat_key']['category']);
        $this->assertEquals('cat_key', $result['cat_key']['key']);
    }

    public function test_global_change_is_immediately_visible_to_users_without_override() {
        config(['settings-kit.cache.enabled' => true]);
        $this->app->forgetInstance('settings-kit');
        Settings::clearResolvedInstance('settings-kit');

        Preference::create([
            'key' => 'cached_theme',
            'type' => 'string',
            'default_value' => 'light',
            'is_user_customizable' => true,
        ]);

        // User 99 has no override — reads from global cache
        $this->assertEquals('light', Settings::get('cached_theme', 99));

        // Change global default
        Settings::set('cached_theme', 'dark');

        // User without override must immediately see the new global value (no stale cache)
        $this->assertEquals('dark', Settings::get('cached_theme', 99));

        // User with an explicit override is isolated from the global change
        Settings::set('cached_theme', 'custom', 99);
        Settings::set('cached_theme', 'midnight');

        $this->assertEquals('custom', Settings::get('cached_theme', 99));
        $this->assertEquals('midnight', Settings::get('cached_theme'));
    }
}
