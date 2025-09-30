<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

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

        // Ensure the setting does not exist
        $this->assertNull(\Metalinked\LaravelSettingsKit\Models\Preference::where('key', $key)->first());

        // Set with userId and autoCreate
        \Metalinked\LaravelSettingsKit\Facades\Settings::set($key, 'dark', $userId, true);

        // Check that it was created with is_user_customizable = true
        $preference = \Metalinked\LaravelSettingsKit\Models\Preference::where('key', $key)->first();
        $this->assertNotNull($preference);
        $this->assertTrue($preference->is_user_customizable);
    }
}
