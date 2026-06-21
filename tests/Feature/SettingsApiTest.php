<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class SettingsApiTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        // Enable API for testing
        config(['settings-kit.api.enabled' => true]);
        config(['settings-kit.api.auth_mode' => 'token']);
        config(['settings-kit.api.token' => 'test-token']);
        config(['settings-kit.api.prefix' => 'api/settings-kit']);
        // Disable development bypass by default for proper auth testing
        config(['settings-kit.api.disable_auth_in_development' => false]);
    }

    public function test_api_disabled_returns_404() {
        config(['settings-kit.api.enabled' => false]);

        $response = $this->getJson('/api/settings-kit', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404)
                ->assertJson(['error' => 'API not enabled']);
    }

    public function test_missing_token_returns_401() {
        $response = $this->getJson('/api/settings-kit');

        $response->assertStatus(401)
                ->assertJson(['error' => 'Invalid or missing token']);
    }

    public function test_invalid_token_returns_401() {
        $response = $this->getJson('/api/settings-kit', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401)
                ->assertJson(['error' => 'Invalid or missing token']);
    }

    public function test_development_bypass_allows_access_without_token() {
        // Enable development bypass
        config(['settings-kit.api.disable_auth_in_development' => true]);

        // Force local environment
        app()['env'] = 'local';

        Settings::create([
            'key' => 'test_dev_setting',
            'type' => 'string',
            'default_value' => 'test_value',
            'category' => 'test',
        ]);

        // Should work without any authorization header
        $response = $this->getJson('/api/settings-kit/test_dev_setting');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'key' => 'test_dev_setting',
                        'value' => 'test_value',
                    ],
                ]);
    }

    public function test_development_bypass_disabled_in_production() {
        config(['settings-kit.api.disable_auth_in_development' => true]);

        // Simulate production environment
        app()['env'] = 'production';

        // Should require authentication even with bypass enabled, because env is not local/testing
        $response = $this->getJson('/api/settings-kit');

        $response->assertStatus(401)
                ->assertJson(['error' => 'Invalid or missing token']);

        // Restore environment for subsequent tests
        app()['env'] = 'testing';
    }

    public function test_development_bypass_works_in_testing_environment() {
        // Enable development bypass
        config(['settings-kit.api.disable_auth_in_development' => true]);

        // Keep testing environment (already set)
        app()['env'] = 'testing';

        Settings::create([
            'key' => 'test_env_setting',
            'type' => 'string',
            'default_value' => 'test_value',
            'category' => 'test',
        ]);

        // Should work without any authorization header
        $response = $this->getJson('/api/settings-kit/test_env_setting');

        $response->assertStatus(200);
    }

    public function test_development_bypass_disabled_still_requires_auth() {
        // Explicitly disable development bypass
        config(['settings-kit.api.disable_auth_in_development' => false]);

        // Force local environment
        app()['env'] = 'local';

        // Should still require authentication even in local environment
        $response = $this->getJson('/api/settings-kit');

        $response->assertStatus(401)
                ->assertJson(['error' => 'Invalid or missing token']);
    }

    public function test_can_access_global_settings_endpoint() {
        Settings::create([
            'key' => 'global_test_setting',
            'type' => 'string',
            'default_value' => 'global_value',
            'category' => 'test',
            'is_user_customizable' => false,
        ]);

        $response = $this->getJson('/api/settings-kit/global/global_test_setting', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'key' => 'global_test_setting',
                        'value' => 'global_value',
                        'type' => 'global',
                    ],
                ]);
    }

    public function test_can_access_user_settings_endpoint() {
        Settings::create([
            'key' => 'user_test_setting',
            'type' => 'string',
            'default_value' => 'default_value',
            'category' => 'test',
            'is_user_customizable' => true,
        ]);

        // Set a user-specific value
        Settings::set('user_test_setting', 'user_value', 1);

        $response = $this->getJson('/api/settings-kit/user/user_test_setting?user_id=1', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'key' => 'user_test_setting',
                        'value' => 'user_value',
                        'user_id' => 1,
                        'type' => 'user',
                    ],
                ]);
    }

    public function test_can_update_global_settings() {
        Settings::create([
            'key' => 'updateable_global',
            'type' => 'string',
            'default_value' => 'old_value',
            'category' => 'test',
            'is_user_customizable' => false,
        ]);

        $response = $this->postJson('/api/settings-kit/global/updateable_global', [
            'value' => 'new_global_value',
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Global setting updated successfully',
                    'data' => [
                        'key' => 'updateable_global',
                        'value' => 'new_global_value',
                        'type' => 'global',
                    ],
                ]);
    }

    public function test_user_settings_require_user_id() {
        $response = $this->getJson('/api/settings-kit/user/some_setting', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(400)
                ->assertJson(['success' => false, 'error' => 'User ID required']);
    }

    public function test_can_get_all_settings() {
        Settings::createWithTranslations('test_setting', [
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'test',
        ], [
            'en' => ['title' => 'Test Setting', 'description' => 'Test description'],
        ]);

        $response = $this->getJson('/api/settings-kit?locale=en', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'test_setting' => [
                            'value',
                            'type',
                            'category',
                            'label',
                            'description',
                            'key',
                        ],
                    ],
                    'meta',
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'test_setting' => [
                            'label' => 'Test Setting',
                            'description' => 'Test description',
                        ],
                    ],
                ]);
    }

    public function test_can_get_specific_setting() {
        Settings::create([
            'key' => 'specific_setting',
            'type' => 'string',
            'default_value' => 'test_value',
            'category' => 'test',
        ]);

        $response = $this->getJson('/api/settings-kit/specific_setting', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'key' => 'specific_setting',
                        'value' => 'test_value',
                    ],
                ]);
    }

    public function test_get_nonexistent_setting_returns_404() {
        $response = $this->getJson('/api/settings-kit/nonexistent', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404)
                ->assertJson(['error' => 'Setting not found']);
    }

    public function test_can_update_setting() {
        Settings::create([
            'key' => 'updatable_setting',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'test',
        ]);

        $response = $this->postJson('/api/settings-kit/updatable_setting', [
            'value' => true,
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting updated successfully',
                    'data' => [
                        'key' => 'updatable_setting',
                        'value' => true,
                    ],
                ]);

        $this->assertTrue(Settings::get('updatable_setting'));
    }

    public function test_can_create_setting_with_auto_create() {
        $response = $this->postJson('/api/settings-kit/new_setting', [
            'value' => 'new_value',
            'auto_create' => true,
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting created and updated successfully',
                ]);

        $this->assertTrue(Settings::has('new_setting'));
        $this->assertEquals('new_value', Settings::get('new_setting'));
    }

    public function test_cannot_update_nonexistent_setting_without_auto_create() {
        $response = $this->postJson('/api/settings-kit/nonexistent', [
            'value' => 'some_value',
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => 'Setting not found',
                ]);
    }

    public function test_can_reset_setting() {
        Settings::create([
            'key' => 'resetable_setting',
            'type' => 'string',
            'default_value' => 'default',
            'category' => 'test',
            'is_user_customizable' => true,  // Allow user customization
        ]);

        // Set user-specific value instead of global change
        Settings::set('resetable_setting', 'user_value', 1);

        $response = $this->deleteJson('/api/settings-kit/resetable_setting?user_id=1', [], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting reset to default successfully',
                ]);

        // User should now get the default value
        $this->assertEquals('default', Settings::get('resetable_setting', 1));
    }

    public function test_can_get_categories() {
        Settings::create([
            'key' => 'cat1_setting',
            'type' => 'string',
            'default_value' => 'test',
            'category' => 'category1',
        ]);

        Settings::create([
            'key' => 'cat2_setting',
            'type' => 'string',
            'default_value' => 'test',
            'category' => 'category2',
        ]);

        $response = $this->getJson('/api/settings-kit/categories', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'meta' => ['count'],
                ])
                ->assertJson([
                    'success' => true,
                ]);

        $this->assertContains('category1', $response->json('data'));
        $this->assertContains('category2', $response->json('data'));
    }

    public function test_can_create_preference_via_api() {
        $response = $this->postJson('/api/settings-kit/preferences', [
            'key' => 'api_created',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'test',
            'translations' => [
                'en' => [
                    'title' => 'API Created Setting',
                    'text' => 'Created via API',
                ],
            ],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Preference created successfully',
                    'data' => [
                        'key' => 'api_created',
                        'type' => 'boolean',
                        'translations_count' => 1,
                    ],
                ]);

        $this->assertTrue(Settings::has('api_created'));
        $this->assertEquals('API Created Setting', Settings::label('api_created', 'en'));
    }

    public function test_validation_errors_return_422() {
        $response = $this->postJson('/api/settings-kit/preferences', [
            'key' => '', // Invalid key
            'type' => 'invalid_type', // Invalid type
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'error',
                    'errors',
                ])
                ->assertJson([
                    'success' => false,
                    'error' => 'Validation failed',
                ]);
    }

    public function test_can_filter_settings_by_category() {
        Settings::create(['key' => 'setting1', 'type' => 'string', 'default_value' => 'test', 'category' => 'cat1']);
        Settings::create(['key' => 'setting2', 'type' => 'string', 'default_value' => 'test', 'category' => 'cat2']);

        $response = $this->getJson('/api/settings-kit?category=cat1', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('setting1', $data);
        $this->assertArrayNotHasKey('setting2', $data);
    }

    public function test_can_handle_user_specific_settings() {
        Settings::create([
            'key' => 'user_setting',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'user',
            'is_user_customizable' => true,  // Allow user customization
        ]);

        // Set user-specific value
        $response = $this->postJson('/api/settings-kit/user_setting', [
            'value' => true,
            'user_id' => 123,
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);

        // Get user-specific value
        $response = $this->getJson('/api/settings-kit/user_setting?user_id=123', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'value' => true,
                        'user_id' => 123,
                    ],
                ]);
    }

    public function test_api_auto_create_setting_when_configured() {
        // Enable auto-creation via config
        config(['settings-kit.api.auto_create_missing_settings' => true]);

        // Try to set a non-existent setting without auto_create parameter
        $response = $this->postJson('/api/settings-kit/new_auto_setting', [
            'value' => 'auto_created_value',
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting created and updated successfully',
                    'data' => [
                        'key' => 'new_auto_setting',
                        'value' => 'auto_created_value',
                    ],
                ]);

        // Verify the setting was created
        $this->assertTrue(Settings::has('new_auto_setting'));
        $this->assertEquals('auto_created_value', Settings::get('new_auto_setting'));
    }

    public function test_api_manual_auto_create_with_parameter() {
        // Disable auto-creation via config
        config(['settings-kit.api.auto_create_missing_settings' => false]);

        // Try to set a non-existent setting WITH auto_create=true parameter
        $response = $this->postJson('/api/settings-kit/manual_auto_setting', [
            'value' => 'manual_created_value',
            'auto_create' => true,
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting created and updated successfully',
                    'data' => [
                        'key' => 'manual_auto_setting',
                        'value' => 'manual_created_value',
                    ],
                ]);

        // Verify the setting was created
        $this->assertTrue(Settings::has('manual_auto_setting'));
        $this->assertEquals('manual_created_value', Settings::get('manual_auto_setting'));
    }

    public function test_api_fails_when_setting_not_found_and_auto_create_disabled() {
        // Disable auto-creation via config
        config(['settings-kit.api.auto_create_missing_settings' => false]);

        // Try to set a non-existent setting without auto_create parameter
        $response = $this->postJson('/api/settings-kit/nonexistent_setting', [
            'value' => 'some_value',
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => 'Setting not found',
                ]);

        // Verify the setting was NOT created
        $this->assertFalse(Settings::has('nonexistent_setting'));
    }

    public function test_destroy_global_deletes_preference() {
        Settings::create(['key' => 'to_delete', 'type' => 'string', 'default_value' => 'x']);

        $response = $this->deleteJson('/api/settings-kit/global/to_delete', [], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'message' => 'Preference deleted successfully']);
        $this->assertFalse(Settings::has('to_delete'));
    }

    public function test_destroy_global_returns_404_for_missing_key() {
        $response = $this->deleteJson('/api/settings-kit/global/nonexistent', [], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_store_global_returns_404_for_missing_key() {
        $response = $this->postJson('/api/settings-kit/global/nonexistent', ['value' => 'x'], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404)->assertJson(['success' => false, 'error' => 'Setting not found']);
    }

    public function test_store_user_returns_404_for_missing_key() {
        $response = $this->postJson('/api/settings-kit/user/nonexistent?user_id=1', ['value' => 'x'], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404)->assertJson(['success' => false, 'error' => 'Setting not found']);
    }

    public function test_can_get_all_user_settings() {
        Settings::create(['key' => 'user_index_a', 'type' => 'string', 'default_value' => 'global_a', 'is_user_customizable' => true]);
        Settings::create(['key' => 'user_index_b', 'type' => 'string', 'default_value' => 'global_b', 'is_user_customizable' => true]);
        Settings::create(['key' => 'non_custom', 'type' => 'string', 'default_value' => 'x', 'is_user_customizable' => false]);
        Settings::set('user_index_a', 'my_value', 42);

        $response = $this->getJson('/api/settings-kit/user?user_id=42', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertArrayHasKey('user_index_a', $data);
        $this->assertArrayHasKey('user_index_b', $data);
        $this->assertArrayNotHasKey('non_custom', $data);

        $this->assertEquals('my_value', $data['user_index_a']['value']);
        $this->assertTrue($data['user_index_a']['is_overridden']);
        $this->assertEquals('global_b', $data['user_index_b']['value']);
        $this->assertFalse($data['user_index_b']['is_overridden']);
    }

    public function test_can_batch_update_user_settings() {
        Settings::create(['key' => 'batch_x', 'type' => 'string', 'default_value' => 'old', 'is_user_customizable' => true]);
        Settings::create(['key' => 'batch_y', 'type' => 'string', 'default_value' => 'old', 'is_user_customizable' => true]);

        $response = $this->postJson('/api/settings-kit/user/batch', [
            'user_id' => 5,
            'settings' => ['batch_x' => 'new_x', 'batch_y' => 'new_y'],
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['user_id' => 5, 'updated' => 2]]);

        $this->assertEquals('new_x', Settings::get('batch_x', 5));
        $this->assertEquals('new_y', Settings::get('batch_y', 5));
    }

    public function test_can_reset_all_user_overrides() {
        Settings::create(['key' => 'reset_all_a', 'type' => 'string', 'default_value' => 'global', 'is_user_customizable' => true]);
        Settings::create(['key' => 'reset_all_b', 'type' => 'string', 'default_value' => 'global', 'is_user_customizable' => true]);
        Settings::set('reset_all_a', 'custom', 8);
        Settings::set('reset_all_b', 'custom', 8);

        $response = $this->deleteJson('/api/settings-kit/user?user_id=8', [], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['user_id' => 8, 'reset_count' => 2]]);

        $this->assertEquals('global', Settings::get('reset_all_a', 8));
        $this->assertEquals('global', Settings::get('reset_all_b', 8));
    }

    public function test_all_responses_have_success_field() {
        // Verify consistent response format for error cases
        $notFound = $this->getJson('/api/settings-kit/global/nonexistent', ['Authorization' => 'Bearer test-token']);
        $notFound->assertJson(['success' => false]);

        $badUser = $this->getJson('/api/settings-kit/user?user_id=0', ['Authorization' => 'Bearer test-token']);
        $this->assertArrayHasKey('success', $badUser->json());
    }

    public function test_api_auto_create_respects_is_user_customizable() {
        // Enable auto-creation
        config(['settings-kit.api.auto_create_missing_settings' => true]);

        // Create global unique setting via API
        $response = $this->postJson('/api/settings-kit/global_auto_setting', [
            'value' => true,
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);

        // Check that it was created as user customizable by default (since no explicit type specified)
        $preference = \Metalinked\LaravelSettingsKit\Models\Preference::where('key', 'global_auto_setting')->first();
        $this->assertNotNull($preference);
        $this->assertFalse($preference->is_user_customizable); // Default should be false for auto-created settings

        // Try to set user-specific value - should fail for global unique setting
        $response = $this->postJson('/api/settings-kit/global_auto_setting', [
            'value' => false,
            'user_id' => 123,
        ], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(422); // Should fail with InvalidArgumentException converted to 422
    }
}
