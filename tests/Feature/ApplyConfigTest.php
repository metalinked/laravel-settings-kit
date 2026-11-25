<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use InvalidArgumentException;
use Metalinked\LaravelSettingsKit\Exceptions\UndefinedSettingException;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\SettingsRegistry;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\Theme;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class ApplyConfigTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        config(['mail.from.address' => 'env@example.com', 'app.name' => 'Env App']);

        app(SettingsRegistry::class)->load([
            'mail.from_address' => ['type' => 'string', 'nullable' => true, 'config' => 'mail.from.address', 'overridable' => true],
            'site_name' => ['default' => 'Default App', 'config' => 'app.name'],
        ]);
    }

    public function test_config_is_untouched_when_nothing_is_stored(): void {
        Settings::applyConfig();

        $this->assertSame('env@example.com', config('mail.from.address'));
        $this->assertSame('Env App', config('app.name'));
    }

    public function test_stored_values_are_written_to_config(): void {
        Settings::set(['mail.from_address' => 'saved@example.com', 'site_name' => 'Saved']);
        $this->assertSame('env@example.com', config('mail.from.address'), 'Saving alone does not touch config.');

        Settings::applyConfig();

        $this->assertSame('saved@example.com', config('mail.from.address'));
        $this->assertSame('Saved', config('app.name'));
    }

    public function test_only_the_given_keys_are_applied(): void {
        Settings::set(['mail.from_address' => 'saved@example.com', 'site_name' => 'Saved']);

        Settings::applyConfig(['mail.from_address']);

        $this->assertSame('saved@example.com', config('mail.from.address'));
        $this->assertSame('Env App', config('app.name'));
    }

    public function test_removing_a_value_restores_the_original_config(): void {
        Settings::set('mail.from_address', 'first@example.com');
        Settings::applyConfig();
        Settings::set('mail.from_address', 'second@example.com');
        Settings::applyConfig();
        Settings::forget('mail.from_address');

        $this->app->forgetScopedInstances(); // the next job of a queue worker
        Settings::applyConfig();

        $this->assertSame('env@example.com', config('mail.from.address'));
    }

    public function test_a_later_change_by_the_application_is_not_undone(): void {
        Settings::set('mail.from_address', 'saved@example.com');
        Settings::applyConfig();
        config(['mail.from.address' => 'runtime@example.com']);
        Settings::forget('mail.from_address');

        Settings::applyConfig();

        $this->assertSame('runtime@example.com', config('mail.from.address'));
    }

    public function test_a_model_applies_its_own_value_or_the_global_one(): void {
        $anna = User::create(['name' => 'Anna']);
        $bob = User::create(['name' => 'Bob']);
        Settings::set(['mail.from_address' => 'global@example.com', 'site_name' => 'Saved']);
        $anna->settings()->set('mail.from_address', 'anna@example.com');

        $anna->settings()->applyConfig();
        $this->assertSame('anna@example.com', config('mail.from.address'));
        $this->assertSame('Saved', config('app.name'));

        $bob->settings()->applyConfig();
        $this->assertSame('global@example.com', config('mail.from.address'));

        Settings::forget('mail.from_address');
        $bob->settings()->applyConfig();
        $this->assertSame('env@example.com', config('mail.from.address'));
    }

    public function test_a_stored_null_is_applied(): void {
        Settings::set('mail.from_address', null);

        Settings::applyConfig();

        $this->assertNull(config('mail.from.address'));
    }

    public function test_encrypted_and_enum_values_are_applied_as_plain_values(): void {
        Settings::define('secret')->string()->default('')->encrypted()->config('services.x.key');
        Settings::define('theme')->enum(Theme::class)->default(Theme::Light)->config('app.theme');
        Settings::set(['secret' => 'abc', 'theme' => Theme::Dark]);

        Settings::applyConfig();

        $this->assertSame('abc', config('services.x.key'));
        $this->assertSame('dark', config('app.theme'));
    }

    public function test_a_key_without_config_is_refused(): void {
        Settings::define('plain')->default('x');

        $this->expectException(InvalidArgumentException::class);
        Settings::applyConfig(['plain']);
    }

    public function test_an_unknown_key_is_refused(): void {
        $this->expectException(UndefinedSettingException::class);
        Settings::applyConfig(['nope']);
    }
}
