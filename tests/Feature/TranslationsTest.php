<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\SettingsKitServiceProvider;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TranslationsTest extends TestCase {
    protected function defineRoutes($router): void {
        Route::prefix('api')->group(fn () => Settings::routes());
    }

    protected function setUp(): void {
        parent::setUp();

        Settings::define('items')->integer()->default(20);
    }

    protected function error(): string {
        try {
            Settings::set('items', 'abc');
        } catch (ValidationException $e) {
            return $e->errors()['items'][0];
        }

        $this->fail('Expected a ValidationException.');
    }

    #[DataProvider('locales')]
    public function test_type_errors_follow_the_locale(string $locale, string $message): void {
        app()->setLocale($locale);

        $this->assertSame($message, $this->error());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function locales(): array {
        return [
            'en' => ['en', 'The items setting must be an integer.'],
            'ca' => ['ca', 'El paràmetre items ha de ser un nombre enter.'],
            'es' => ['es', 'El ajuste items debe ser un número entero.'],
        ];
    }

    public function test_english_is_used_when_no_translation_exists(): void {
        app()->setLocale('fr');
        app('translator')->setFallback('de');

        $this->assertSame('The items setting must be an integer.', $this->error());
    }

    public function test_the_fallback_locale_wins_over_english(): void {
        app()->setLocale('fr');
        app('translator')->setFallback('ca');

        $this->assertSame('El paràmetre items ha de ser un nombre enter.', $this->error());
    }

    public function test_an_application_can_override_a_message(): void {
        // Load the group first, or loading it later would replace the added line.
        Lang::get('settings-kit::messages.integer');
        Lang::addLines(['messages.integer' => ':key: whole numbers only.'], 'en', 'settings-kit');

        $this->assertSame('items: whole numbers only.', $this->error());
    }

    public function test_endpoint_errors_are_translated(): void {
        Gate::define('manage-settings', fn () => true);
        app()->setLocale('ca');

        $this->actingAs(User::create(['name' => 'Anna']))
            ->patchJson('/api/settings', ['values' => ['nope' => 1]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nope.0', "El paràmetre 'nope' no existeix.");
    }

    public function test_every_language_file_has_the_same_lines_and_placeholders(): void {
        $english = require __DIR__ . '/../../lang/en/messages.php';

        foreach (['ca', 'es'] as $locale) {
            $lines = require __DIR__ . "/../../lang/{$locale}/messages.php";
            $this->assertSame(array_keys($english), array_keys($lines), $locale);

            foreach ($english as $key => $text) {
                preg_match_all('/:[a-z]+/', $text, $expected);
                preg_match_all('/:[a-z]+/', $lines[$key], $actual);
                $this->assertEqualsCanonicalizing($expected[0], $actual[0], "{$locale}.{$key}");
            }
        }
    }

    public function test_the_language_files_can_be_published(): void {
        $paths = ServiceProvider::pathsToPublish(SettingsKitServiceProvider::class, 'settings-kit-lang');

        $this->assertSame([app()->langPath('vendor/settings-kit')], array_values($paths));
    }
}
