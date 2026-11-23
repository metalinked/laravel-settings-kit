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

    public function test_encrypted_settings_are_not_exported_or_listed_by_default(): void {
        Settings::define('api_key')->string()->nullable()->encrypted();
        Settings::set(['site_name' => 'Live', 'api_key' => 'secret-123']);
        $path = "{$this->tmp}/settings.json";

        $this->artisan('settings:export', ['path' => $path])
            ->expectsOutputToContain('api_key')
            ->assertSuccessful();
        $this->assertSame(['site_name' => 'Live'], json_decode(File::get($path), true));

        $this->artisan('settings:export', ['path' => $path, '--with-secrets' => true])->assertSuccessful();
        $this->assertSame(['api_key' => 'secret-123', 'site_name' => 'Live'], json_decode(File::get($path), true));

        $this->artisan('settings:list')
            ->expectsTable(
                ['Key', 'Group', 'Type', 'Default', 'Global value', 'Overridable', 'Overrides'],
                [
                    ['site_name', 'general', 'string', 'My App', 'Live', 'no', 0],
                    ['per_page', 'general', 'integer', '20', '—', 'yes', 0],
                    ['api_key', 'general', 'string', 'null', '(encrypted)', 'no', 0],
                ]
            )
            ->assertSuccessful();
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

    public function test_upgrade_from_v1_dry_run_writes_nothing(): void {
        $this->createV1Tables();
        $user = User::create(['name' => 'Anna']);
        $id = DB::table('preferences')->insertGetId($this->v1Preference('theme', 'string', 'light', customizable: true));
        DB::table('preference_contents')->insert(['preference_id' => $id, 'locale' => 'ca', 'title' => 'Tema', 'text' => null]);
        DB::table('user_preferences')->insert(['preference_id' => $id, 'user_id' => $user->id, 'value' => 'dark']);

        $this->app->useLangPath("{$this->tmp}/lang");

        $this->artisan('settings:upgrade-from-v1', [
            '--user-model' => User::class,
            '--definitions' => "{$this->tmp}/definitions.php",
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertFileDoesNotExist("{$this->tmp}/definitions.php");
        $this->assertFileDoesNotExist("{$this->tmp}/lang/ca/settings.php");
        $this->assertSame(0, DB::table('settings_kit_values')->count());
    }

    public function test_upgrade_from_v1_skips_orphans_and_rows_without_a_user(): void {
        $this->createV1Tables();
        $user = User::create(['name' => 'Anna']);
        $id = DB::table('preferences')->insertGetId($this->v1Preference('theme', 'string', 'light', customizable: true));
        DB::table('user_preferences')->insert([
            ['preference_id' => $id, 'user_id' => $user->id, 'value' => 'dark'],
            ['preference_id' => 999, 'user_id' => $user->id, 'value' => 'ghost'],
        ]);
        DB::table('user_preferences')->insert(['preference_id' => $id, 'user_id' => null, 'value' => 'nobody']);
        DB::table('preference_contents')->insert(['preference_id' => 999, 'locale' => 'ca', 'title' => 'Orfe', 'text' => null]);

        $this->app->useLangPath("{$this->tmp}/lang");

        $this->artisan('settings:upgrade-from-v1', [
            '--user-model' => User::class,
            '--definitions' => "{$this->tmp}/definitions.php",
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('settings_kit_values')->count());
        $this->assertSame('"dark"', DB::table('settings_kit_values')->value('value'));
        $this->assertFileDoesNotExist("{$this->tmp}/lang/ca/settings.php");
    }

    public function test_upgrade_from_v1_keeps_an_existing_definitions_file_when_not_confirmed(): void {
        $this->createV1Tables();
        DB::table('preferences')->insert($this->v1Preference('theme', 'string', 'light'));
        $definitionsPath = "{$this->tmp}/definitions.php";
        File::put($definitionsPath, '<?php return ["mine" => true];');

        $this->app->useLangPath("{$this->tmp}/lang");

        $this->artisan('settings:upgrade-from-v1', ['--user-model' => User::class, '--definitions' => $definitionsPath])
            ->expectsConfirmation("{$definitionsPath} already exists. Overwrite it?", 'no')
            ->assertSuccessful();

        $this->assertSame('<?php return ["mine" => true];', File::get($definitionsPath));
    }

    public function test_upgrade_from_v1_never_overwrites_an_existing_language_file(): void {
        $this->createV1Tables();
        $id = DB::table('preferences')->insertGetId($this->v1Preference('theme', 'string', 'light'));
        DB::table('preference_contents')->insert(['preference_id' => $id, 'locale' => 'ca', 'title' => 'Tema', 'text' => '']);

        $this->app->useLangPath("{$this->tmp}/lang");
        File::ensureDirectoryExists("{$this->tmp}/lang/ca");
        File::put("{$this->tmp}/lang/ca/settings.php", '<?php return ["mine" => true];');

        $this->artisan('settings:upgrade-from-v1', [
            '--user-model' => User::class,
            '--definitions' => "{$this->tmp}/definitions.php",
        ])->assertSuccessful();

        $this->assertSame('<?php return ["mine" => true];', File::get("{$this->tmp}/lang/ca/settings.php"));
        $this->assertSame(['theme' => ['label' => 'Tema']], require "{$this->tmp}/lang/ca/settings.from-v1.php");
    }

    public function test_upgrade_from_v1_converts_types_and_nullable_defaults(): void {
        $this->createV1Tables();
        DB::table('preferences')->insert([
            $this->v1Preference('empty_select', 'select', 'a', options: null),
            $this->v1Preference('count', 'integer', '15'),
            $this->v1Preference('flag', 'boolean', 'yes'),
            $this->v1Preference('note', 'string', null),
            $this->v1Preference('broken_json', 'json', 'not json'),
        ]);

        $this->app->useLangPath("{$this->tmp}/lang");
        $definitionsPath = "{$this->tmp}/definitions.php";

        $this->artisan('settings:upgrade-from-v1', ['--user-model' => User::class, '--definitions' => $definitionsPath])
            ->assertSuccessful();

        $definitions = require $definitionsPath;

        $this->assertSame('string', $definitions['empty_select']['type']);
        $this->assertSame(15, $definitions['count']['default']);
        $this->assertTrue($definitions['flag']['default']);
        $this->assertNull($definitions['note']['default']);
        $this->assertTrue($definitions['note']['nullable']);
        $this->assertSame([], $definitions['broken_json']['default']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function v1Preference(string $key, string $type, ?string $default, bool $customizable = false, ?string $options = null): array {
        return [
            'key' => $key, 'type' => $type, 'default_value' => $default, 'options' => $options,
            'category' => null, 'role' => null, 'required' => false, 'is_user_customizable' => $customizable,
        ];
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
            // Nullable until 1.x migration 000004 made it NOT NULL; older installs may still have NULL rows.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('value');
            $table->timestamps();
        });
    }
}
