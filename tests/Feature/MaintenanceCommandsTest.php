<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class MaintenanceCommandsTest extends TestCase {
    protected User $user;

    protected function setUp(): void {
        parent::setUp();

        $this->user = User::create(['name' => 'Ada']);
    }

    protected function raw(string $key): string {
        return DB::table('settings_kit_values')->where('key', $key)->where('settable_type', '')->value('value');
    }

    protected function useKey(string $current, string ...$previous): void {
        $encode = fn (string $char) => 'base64:' . base64_encode(str_repeat($char, 32));

        config(['app.key' => $encode($current), 'app.previous_keys' => array_map($encode, $previous)]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public function test_rename_moves_global_values_and_overrides(): void {
        Settings::define('old')->default(1)->overridable();
        Settings::set('old', 2);
        $this->user->settings()->set('old', 3);
        Settings::define('new')->default(1)->overridable();
        $this->assertSame(1, Settings::get('new'));

        $this->artisan('settings:rename', ['from' => 'old', 'to' => 'new'])
            ->expectsOutputToContain('2 stored value(s) moved')
            ->assertSuccessful();

        $this->assertSame(2, Settings::get('new'));
        $this->assertSame(3, $this->user->settings()->get('new'));
        $this->assertFalse(Settings::has('old'));

        $this->artisan('settings:rename', ['from' => 'old', 'to' => 'new'])
            ->expectsOutputToContain('0 stored value(s) moved')
            ->assertSuccessful();
        $this->assertSame(2, Settings::get('new'));
    }

    public function test_rename_keeps_values_already_stored_under_the_new_key(): void {
        Settings::define('new')->default(1)->overridable();
        Settings::set(['old' => 2, 'new' => 5]);
        $this->user->settings()->set('old', 3);

        $this->artisan('settings:rename', ['from' => 'old', 'to' => 'new'])
            ->expectsOutputToContain('1 value(s) stay')
            ->assertSuccessful();

        $this->assertSame(5, Settings::get('new'));
        $this->assertSame(3, $this->user->settings()->get('new'));
        $this->assertTrue(Settings::has('old'));

        $this->artisan('settings:rename', ['from' => 'old', 'to' => 'new', '--overwrite' => true])->assertSuccessful();

        $this->assertSame(2, Settings::get('new'));
        $this->assertFalse(Settings::has('old'));
    }

    public function test_rename_refuses_an_undefined_or_equal_key(): void {
        Settings::define('Site');
        Settings::set('old', 'x');

        $this->artisan('settings:rename', ['from' => 'old', 'to' => 'typo'])->assertFailed();
        $this->artisan('settings:rename', ['from' => 'site', 'to' => 'Site', '--overwrite' => true])->assertFailed();

        $this->assertTrue(Settings::has('old'));
    }

    public function test_encrypt_encrypts_plain_values_in_every_scope(): void {
        Settings::define('token')->string()->nullable()->overridable();
        Settings::set('token', 'global-secret');
        $this->user->settings()->set('token', 'user-secret');
        Settings::define('token')->string()->nullable()->overridable()->encrypted();

        $this->artisan('settings:encrypt', ['--dry-run' => true])
            ->expectsOutputToContain('2 encrypted')
            ->assertSuccessful();
        $this->assertSame('"global-secret"', $this->raw('token'));

        $this->artisan('settings:encrypt')->expectsOutputToContain('2 encrypted')->assertSuccessful();

        $this->assertStringNotContainsString('secret', $this->raw('token'));
        $this->assertSame('global-secret', Settings::get('token'));
        $this->assertSame('user-secret', $this->user->settings()->get('token'));
    }

    public function test_encrypt_moves_values_to_the_current_key(): void {
        Settings::define('token')->string()->nullable()->encrypted();
        Settings::set('token', 'secret');

        $this->useKey('n', 'k');
        $this->artisan('settings:encrypt')->expectsOutputToContain('1 re-encrypted')->assertSuccessful();

        $this->useKey('n');
        $this->app->forgetScopedInstances();
        $this->assertSame('secret', Settings::get('token'));
    }

    public function test_encrypt_fails_and_keeps_values_it_cannot_read(): void {
        Settings::define('token')->string()->nullable()->encrypted();
        Settings::set('token', 'secret');
        $before = $this->raw('token');

        $this->useKey('n');
        $this->artisan('settings:encrypt')->expectsOutputToContain('cannot be read')->assertFailed();

        $this->assertSame($before, $this->raw('token'));
    }

    public function test_encrypt_only_decrypts_when_asked(): void {
        Settings::define('token')->string()->nullable()->encrypted();
        Settings::set('token', 'secret');
        Settings::define('token')->string()->nullable();

        $this->artisan('settings:encrypt')->expectsOutputToContain('--decrypt')->assertSuccessful();
        $this->assertNotSame('"secret"', $this->raw('token'));

        $this->artisan('settings:encrypt', ['--decrypt' => true])->expectsOutputToContain('1 decrypted')->assertSuccessful();
        $this->assertSame('"secret"', $this->raw('token'));
        $this->assertSame('secret', Settings::get('token'));
    }
}
