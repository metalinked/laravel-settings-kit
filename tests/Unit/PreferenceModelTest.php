<?php

namespace Metalinked\LaravelSettingsKit\Tests\Unit;

use Metalinked\LaravelSettingsKit\Models\Preference;
use Metalinked\LaravelSettingsKit\Models\PreferenceContent;
use Metalinked\LaravelSettingsKit\Models\UserPreference;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class PreferenceModelTest extends TestCase {
    public function test_can_create_preference() {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'category' => 'general',
        ]);

        $this->assertInstanceOf(Preference::class, $preference);
        $this->assertEquals('test_setting', $preference->key);
        $this->assertEquals('boolean', $preference->type);
        $this->assertEquals('general', $preference->category);
    }

    public function test_can_add_content_translations() {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        $content = PreferenceContent::create([
            'preference_id' => $preference->id,
            'locale' => 'en',
            'title' => 'Test Setting',
            'text' => 'This is a test setting',
        ]);

        $this->assertEquals('Test Setting', $content->title);
        $this->assertEquals('en', $content->locale);
        $this->assertEquals($preference->id, $content->preference_id);
    }

    public function test_can_set_user_preference() {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        $userPref = UserPreference::create([
            'preference_id' => $preference->id,
            'user_id' => 1,
            'value' => '0',
        ]);

        $this->assertEquals('0', $userPref->value);
        $this->assertEquals(1, $userPref->user_id);
        $this->assertEquals($preference->id, $userPref->preference_id);
    }

    public function test_preference_relationships() {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'locale' => 'en',
            'title' => 'Test Setting',
            'text' => 'Description',
        ]);

        UserPreference::create([
            'preference_id' => $preference->id,
            'user_id' => 1,
            'value' => '0',
        ]);

        $this->assertCount(1, $preference->contents);
        $this->assertCount(1, $preference->userPreferences);
    }

    public function test_get_translated_content() {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'locale' => 'en',
            'title' => 'English Title',
            'text' => 'English Description',
        ]);

        PreferenceContent::create([
            'preference_id' => $preference->id,
            'locale' => 'es',
            'title' => 'Spanish Title',
            'text' => 'Spanish Description',
        ]);

        $englishContent = $preference->getTranslatedContent('en');
        $spanishContent = $preference->getTranslatedContent('es');

        $this->assertEquals('English Title', $englishContent->title);
        $this->assertEquals('Spanish Title', $spanishContent->title);
    }

    public function test_get_user_value() {
        $preference = Preference::create([
            'key' => 'test_setting',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        // No user preference - should return default
        $this->assertTrue($preference->getUserValue(1));

        // Set user preference
        $preference->setUserValue(1, false);
        $this->assertFalse($preference->getUserValue(1));
    }

    public function test_value_casting() {
        // Boolean preference
        $boolPreference = Preference::create([
            'key' => 'bool_setting',
            'type' => 'boolean',
            'default_value' => '1',
        ]);

        $this->assertTrue($boolPreference->getDefaultValue());

        // Integer preference
        $intPreference = Preference::create([
            'key' => 'int_setting',
            'type' => 'integer',
            'default_value' => '42',
        ]);

        $this->assertEquals(42, $intPreference->getDefaultValue());

        // JSON preference
        $jsonPreference = Preference::create([
            'key' => 'json_setting',
            'type' => 'json',
            'default_value' => json_encode(['key' => 'value']),
        ]);

        $this->assertEquals(['key' => 'value'], $jsonPreference->getDefaultValue());
    }

    public function test_scopes() {
        Preference::create([
            'key' => 'global_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'role' => null,
            'category' => 'general',
        ]);

        Preference::create([
            'key' => 'admin_setting',
            'type' => 'boolean',
            'default_value' => '1',
            'role' => 'admin',
            'category' => 'admin',
        ]);

        // Test role scope
        $globalSettings = Preference::forRole(null)->get();
        $adminSettings = Preference::forRole('admin')->get();

        $this->assertCount(1, $globalSettings);
        $this->assertCount(1, $adminSettings);

        // Test category scope
        $generalSettings = Preference::forCategory('general')->get();
        $adminCategorySettings = Preference::forCategory('admin')->get();

        $this->assertCount(1, $generalSettings);
        $this->assertCount(1, $adminCategorySettings);
    }
}
