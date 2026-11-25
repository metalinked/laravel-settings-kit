<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Metalinked\LaravelSettingsKit\Exceptions\SettingNotOverridableException;
use Metalinked\LaravelSettingsKit\Exceptions\UndefinedSettingException;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\Theme;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RulesTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        Settings::define('points.conversion')->float()->default(1.0)->rules('min:0.5')->label('Conversion rate');
        Settings::define('enabled')->default(true);
        Settings::define('theme')->enum(Theme::class)->default(Theme::Light)->overridable();
        Settings::define('reply_to')->string()->nullable()->rules('email');
        Settings::define('password')->string()->encrypted()->default('')->rules('min:8');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    protected function errors(array $data, string $field, string $key): array {
        return Validator::make($data, Settings::rules([$field => $key]))->errors()->toArray();
    }

    public function test_errors_use_the_field_name_of_the_form(): void {
        $errors = $this->errors(['conversion' => '0.1'], 'conversion', 'points.conversion');

        $this->assertSame(['conversion'], array_keys($errors));
        $this->assertStringContainsString('Conversion rate', $errors['conversion'][0]);
    }

    public function test_type_errors_name_the_setting_by_its_label(): void {
        $errors = $this->errors(['conversion' => 'abc'], 'conversion', 'points.conversion');

        $this->assertSame(['The Conversion rate setting must be a number.'], $errors['conversion']);
    }

    /**
     * Input the package accepts must pass, even where Laravel's own rule for
     * the type would reject it.
     */
    #[DataProvider('accepted')]
    public function test_it_accepts_what_fill_accepts(string $key, mixed $value): void {
        $this->assertSame([], $this->errors(['field' => $value], 'field', $key));

        Settings::fill([$key => $value]);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function accepted(): array {
        return [
            'checkbox on' => ['enabled', 'on'],
            'enum value' => ['theme', 'dark'],
            'empty nullable' => ['reply_to', ''],
            'null nullable' => ['reply_to', null],
            'empty secret' => ['password', ''],
            'null secret' => ['password', null],
            'numeric string' => ['points.conversion', '2'],
        ];
    }

    #[DataProvider('rejected')]
    public function test_it_rejects_what_fill_rejects(string $key, mixed $value): void {
        $this->assertArrayHasKey('field', $this->errors(['field' => $value], 'field', $key));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function rejected(): array {
        return [
            'not a boolean' => ['enabled', 'maybe'],
            'empty number' => ['points.conversion', ''],
            'null boolean' => ['enabled', null],
            'unknown case' => ['theme', 'pink'],
            'invalid email' => ['reply_to', 'nope'],
            'short secret' => ['password', 'abc'],
        ];
    }

    public function test_a_missing_field_is_rejected_unless_it_may_be_empty(): void {
        $this->assertArrayHasKey('field', $this->errors([], 'field', 'enabled'));
        $this->assertSame([], $this->errors([], 'field', 'reply_to'));
    }

    public function test_sometimes_makes_a_field_optional(): void {
        $rules = Settings::rules(['field' => 'enabled']);

        $this->assertTrue(Validator::make([], ['field' => ['sometimes', ...$rules['field']]])->passes());
    }

    public function test_validated_data_includes_the_field_and_nothing_is_saved(): void {
        $data = Validator::make(['conversion' => '2'], Settings::rules(['conversion' => 'points.conversion']))->validate();

        $this->assertSame(['conversion' => '2'], $data);
        $this->assertFalse(Settings::has('points.conversion'));
    }

    public function test_a_list_of_keys_is_refused(): void {
        $this->expectException(InvalidArgumentException::class);

        Settings::rules(['points.conversion']);
    }

    public function test_an_unknown_key_is_refused(): void {
        $this->expectException(UndefinedSettingException::class);

        Settings::rules(['field' => 'nope']);
    }

    public function test_a_model_scope_refuses_keys_it_may_not_override(): void {
        $user = User::create(['name' => 'Anna']);
        $this->assertArrayHasKey('theme', $user->settings()->rules(['theme' => 'theme']));

        $this->expectException(SettingNotOverridableException::class);
        $user->settings()->rules(['field' => 'enabled']);
    }
}
