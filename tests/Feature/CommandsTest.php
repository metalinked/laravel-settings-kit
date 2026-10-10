<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\SettingsRegistry;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class CommandsTest extends TestCase {
    protected string $tmp;

    protected function setUp(): void {
        parent::setUp();

        Settings::define('site_name')->default('My App');
        Settings::define('per_page')->default(20)->overridable();

        $this->tmp = sys_get_temp_dir() . '/settings-kit-' . uniqid();
        File::ensureDirectoryExists($this->tmp);
    }

    protected function tearDown(): void {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_list_shows_definitions_and_orphans(): void {
        Settings::set('site_name', 'Live');
        Settings::set('old_key', 1);

        $this->artisan('settings:list')
            ->expectsTable(
                ['Key', 'Group', 'Type', 'Default', 'Global value', 'Overridable', 'Overrides'],
                [
                    ['site_name', 'general', 'string', 'My App', 'Live', 'no', 0],
                    ['per_page', 'general', 'integer', '20', '—', 'yes', 0],
                ]
            )
            ->expectsOutputToContain('old_key')
            ->assertSuccessful();
    }

    public function test_export_and_import_round_trip(): void {
        Settings::set(['site_name' => 'Exported', 'per_page' => 50]);
        $path = "{$this->tmp}/settings.json";

        $this->artisan('settings:export', ['path' => $path])->assertSuccessful();
        $this->assertSame(['per_page' => 50, 'site_name' => 'Exported'], json_decode(File::get($path), true));

        Settings::forget(['site_name', 'per_page']);
        $this->artisan('settings:import', ['path' => $path])->assertSuccessful();

        $this->assertSame('Exported', Settings::get('site_name'));
        $this->assertSame(50, Settings::get('per_page'));
    }

    public function test_import_dry_run_saves_nothing(): void {
        $path = "{$this->tmp}/settings.json";
        File::put($path, json_encode(['site_name' => 'Dry']));

        $this->artisan('settings:import', ['path' => $path, '--dry-run' => true])->assertSuccessful();

        $this->assertSame('My App', Settings::get('site_name'));
    }

    public function test_import_rejects_invalid_values_without_writing(): void {
        $path = "{$this->tmp}/settings.json";
        File::put($path, json_encode(['site_name' => 'Ok', 'per_page' => 'many']));

        $this->artisan('settings:import', ['path' => $path])->assertFailed();

        $this->assertSame('My App', Settings::get('site_name'));
    }

    public function test_prune_removes_undefined_keys_and_disallowed_overrides(): void {
        $user = User::create(['name' => 'Anna']);
        Settings::set('old_key', 1);
        $user->settings()->set('per_page', 30);
        DB::table('settings_kit_values')->insert([
            'key' => 'site_name', 'settable_type' => $user->getMorphClass(), 'settable_id' => (string) $user->id, 'value' => '"x"',
        ]);
        Settings::set('site_name', 'Keep me');

        $this->artisan('settings:prune', ['--force' => true])->assertSuccessful();

        $this->assertSame(
            [['key' => 'per_page', 'settable_type' => $user->getMorphClass()], ['key' => 'site_name', 'settable_type' => '']],
            DB::table('settings_kit_values')->orderBy('key')->get(['key', 'settable_type'])->map(fn ($r) => (array) $r)->all()
        );
    }

    public function test_upgrade_from_v1(): void {
        $this->createV1Tables();
        $user = User::create(['name' => 'Anna']);

        $themeId = DB::table('preferences')->insertGetId([
            'key' => 'theme', 'type' => 'select', 'default_value' => 'light', 'options' => json_encode(['light', 'dark']),
            'category' => 'appearance', 'role' => null, 'required' => false, 'is_user_customizable' => true,
        ]);
        DB::table('preferences')->insert([
            ['key' => 'maintenance', 'type' => 'boolean', 'default_value' => '0', 'options' => null,
                'category' => 'system', 'role' => 'admin', 'required' => true, 'is_user_customizable' => false],
            ['key' => 'dashboard', 'type' => 'json', 'default_value' => '{"widgets":["a"]}', 'options' => null,
                'category' => null, 'role' => null, 'required' => false, 'is_user_customizable' => true],
        ]);
        DB::table('preference_contents')->insert([
            'preference_id' => $themeId, 'locale' => 'ca', 'title' => 'Tema', 'text' => 'Colors de la interfície',
        ]);
        DB::table('user_preferences')->insert(['preference_id' => $themeId, 'user_id' => $user->id, 'value' => 'dark']);

        $this->app->useLangPath("{$this->tmp}/lang");
        $definitionsPath = "{$this->tmp}/definitions.php";

        $this->artisan('settings:upgrade-from-v1', [
            '--user-model' => User::class,
            '--definitions' => $definitionsPath,
        ])->assertSuccessful();

        $definitions = require $definitionsPath;

        $this->assertSame([
            'type' => 'select', 'options' => ['light', 'dark'], 'default' => 'light', 'overridable' => true, 'group' => 'appearance',
        ], $definitions['theme']);
        $this->assertSame([
            'type' => 'boolean', 'default' => false, 'group' => 'system', 'meta' => ['role' => 'admin', 'required' => true],
        ], $definitions['maintenance']);
        $this->assertSame(['widgets' => ['a']], $definitions['dashboard']['default']);

        $this->assertSame(
            ['theme' => ['label' => 'Tema', 'description' => 'Colors de la interfície']],
            require "{$this->tmp}/lang/ca/settings.php"
        );

        // The generated definitions work and the user override was carried over.
        app(SettingsRegistry::class)->load($definitions);
        $this->app->forgetScopedInstances();

        $this->assertSame('dark', $user->settings()->get('theme'));
        $this->assertSame('light', Settings::get('theme'));
        $this->assertSame(['widgets' => ['a']], $user->settings()->get('dashboard'));
    }

    public function test_upgrade_from_v1_without_v1_tables_fails_cleanly(): void {
        $this->artisan('settings:upgrade-from-v1')->assertFailed();
    }

    protected function createV1Tables(): void {
        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->string('role')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->default('string');
            $table->boolean('required')->default(false);
            $table->string('key')->unique();
            $table->text('default_value')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_user_customizable')->default(false);
            $table->timestamps();
        });

        Schema::create('preference_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preference_id');
            $table->string('locale', 5);
            $table->string('title');
            $table->text('text')->nullable();
            $table->timestamps();
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preference_id');
            $table->unsignedBigInteger('user_id');
            $table->text('value');
            $table->timestamps();
        });
    }
}
