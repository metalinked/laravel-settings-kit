<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    public function test_can_create_preference()
    {
        $preference = Settings::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'general',
        ]);

        $this->assertInstanceOf(Preference::class, $preference);
        $this->assertEquals('test_setting', $preference->key);
        $this->assertEquals('boolean', $preference->type);
        $this->assertTrue(Settings::exists('test_setting'));
    }

    public function test_can_get_global_setting()
    {
        Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        $value = Settings::get('allow_comments');
        $this->assertTrue($value);
    }

    public function test_can_set_global_setting()
    {
        Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        Settings::set('allow_comments', false);
        
        $value = Settings::get('allow_comments');
        $this->assertFalse($value);
    }

    public function test_can_get_user_specific_setting()
    {
        $preference = Preference::create([
            'key' => 'email_notifications',
            'type' => 'boolean',
            'default_value' => '1',
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

    public function test_is_enabled_method()
    {
        Preference::create([
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'default_value' => '0',
        ]);

        $this->assertFalse(Settings::isEnabled('maintenance_mode'));
        
        Settings::set('maintenance_mode', true);
        $this->assertTrue(Settings::isEnabled('maintenance_mode'));
    }

    public function test_can_get_translated_label()
    {
        $preference = Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'lang' => 'en',
            'title' => 'Allow Comments',
            'text' => 'Enable or disable comments',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'lang' => 'es',
            'title' => 'Permitir Comentarios',
            'text' => 'Activar o desactivar comentarios',
        ]);

        $this->assertEquals('Allow Comments', Settings::label('allow_comments', 'en'));
        $this->assertEquals('Permitir Comentarios', Settings::label('allow_comments', 'es'));
    }

    public function test_can_get_translated_description()
    {
        $preference = Preference::create([
            'key' => 'allow_comments',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'lang' => 'en',
            'title' => 'Allow Comments',
            'text' => 'Enable or disable comments on posts',
        ]);

        $this->assertEquals('Enable or disable comments on posts', Settings::description('allow_comments', 'en'));
    }

    public function test_can_get_all_settings()
    {
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

    public function test_can_get_settings_by_category()
    {
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

    public function test_can_forget_setting()
    {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        // Set user value
        Settings::set('test_setting', false, 1);
        $this->assertFalse(Settings::get('test_setting', 1));

        // Forget user value
        Settings::forget('test_setting', 1);
        
        // Should return default value now
        $this->assertTrue(Settings::get('test_setting', 1));
    }

    public function test_handles_different_data_types()
    {
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

    public function test_role_based_settings()
    {
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
}
