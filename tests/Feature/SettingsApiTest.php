<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class SettingsApiTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        
        // Enable API for testing
        config(['settings-kit.api.enabled' => true]);
        config(['settings-kit.api.auth_mode' => 'token']);
        config(['settings-kit.api.token' => 'test-token']);
        config(['settings-kit.api.prefix' => 'api/settings-kit']);
    }

    public function test_api_disabled_returns_404() {
        config(['settings-kit.api.enabled' => false]);
        
        $response = $this->getJson('/api/settings-kit', [
            'Authorization' => 'Bearer test-token'
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
            'Authorization' => 'Bearer invalid-token'
        ]);
        
        $response->assertStatus(401)
                ->assertJson(['error' => 'Invalid or missing token']);
    }

    public function test_can_get_all_settings() {
        Settings::createWithTranslations('test_setting', [
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'test'
        ], [
            'en' => ['title' => 'Test Setting', 'description' => 'Test description']
        ]);

        $response = $this->getJson('/api/settings-kit?locale=en', [
            'Authorization' => 'Bearer test-token'
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
                            'key'
                        ]
                    ],
                    'meta'
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'test_setting' => [
                            'label' => 'Test Setting',
                            'description' => 'Test description'
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_setting() {
        Settings::create([
            'key' => 'specific_setting',
            'type' => 'string',
            'default_value' => 'test_value',
            'category' => 'test'
        ]);

        $response = $this->getJson('/api/settings-kit/specific_setting', [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'key' => 'specific_setting',
                        'value' => 'test_value'
                    ]
                ]);
    }

    public function test_get_nonexistent_setting_returns_404() {
        $response = $this->getJson('/api/settings-kit/nonexistent', [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(404)
                ->assertJson(['error' => 'Setting not found']);
    }

    public function test_can_update_setting() {
        Settings::create([
            'key' => 'updatable_setting',
            'type' => 'boolean',
            'default_value' => '0',
            'category' => 'test'
        ]);

        $response = $this->postJson('/api/settings-kit/updatable_setting', [
            'value' => true
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting updated successfully',
                    'data' => [
                        'key' => 'updatable_setting',
                        'value' => true
                    ]
                ]);

        $this->assertTrue(Settings::get('updatable_setting'));
    }

    public function test_can_create_setting_with_auto_create() {
        $response = $this->postJson('/api/settings-kit/new_setting', [
            'value' => 'new_value',
            'auto_create' => true
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting updated successfully'
                ]);

        $this->assertTrue(Settings::has('new_setting'));
        $this->assertEquals('new_value', Settings::get('new_setting'));
    }

    public function test_cannot_update_nonexistent_setting_without_auto_create() {
        $response = $this->postJson('/api/settings-kit/nonexistent', [
            'value' => 'some_value'
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Setting not found',
                    'message' => 'Use auto_create=true to create the setting automatically'
                ]);
    }

    public function test_can_reset_setting() {
        Settings::create([
            'key' => 'resetable_setting',
            'type' => 'string',
            'default_value' => 'default',
            'category' => 'test'
        ]);
        
        Settings::set('resetable_setting', 'changed');

        $response = $this->deleteJson('/api/settings-kit/resetable_setting', [], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Setting reset to default successfully'
                ]);

        $this->assertEquals('default', Settings::get('resetable_setting'));
    }

    public function test_can_get_categories() {
        Settings::create([
            'key' => 'cat1_setting',
            'type' => 'string',
            'default_value' => 'test',
            'category' => 'category1'
        ]);

        Settings::create([
            'key' => 'cat2_setting',
            'type' => 'string',
            'default_value' => 'test',
            'category' => 'category2'
        ]);

        $response = $this->getJson('/api/settings-kit/categories', [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'meta' => ['count']
                ])
                ->assertJson([
                    'success' => true
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
                    'description' => 'Created via API'
                ]
            ]
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Preference created successfully',
                    'data' => [
                        'key' => 'api_created',
                        'type' => 'boolean',
                        'translations_count' => 1
                    ]
                ]);

        $this->assertTrue(Settings::has('api_created'));
        $this->assertEquals('API Created Setting', Settings::label('api_created', 'en'));
    }

    public function test_validation_errors_return_422() {
        $response = $this->postJson('/api/settings-kit/preferences', [
            'key' => '', // Invalid key
            'type' => 'invalid_type', // Invalid type
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'error',
                    'errors'
                ])
                ->assertJson([
                    'success' => false,
                    'error' => 'Validation failed'
                ]);
    }

    public function test_can_filter_settings_by_category() {
        Settings::create(['key' => 'setting1', 'type' => 'string', 'default_value' => 'test', 'category' => 'cat1']);
        Settings::create(['key' => 'setting2', 'type' => 'string', 'default_value' => 'test', 'category' => 'cat2']);

        $response = $this->getJson('/api/settings-kit?category=cat1', [
            'Authorization' => 'Bearer test-token'
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
            'category' => 'user'
        ]);

        // Set user-specific value
        $response = $this->postJson('/api/settings-kit/user_setting', [
            'value' => true,
            'user_id' => 123
        ], [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200);

        // Get user-specific value
        $response = $this->getJson('/api/settings-kit/user_setting?user_id=123', [
            'Authorization' => 'Bearer test-token'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'value' => true,
                        'user_id' => 123
                    ]
                ]);
    }
}
