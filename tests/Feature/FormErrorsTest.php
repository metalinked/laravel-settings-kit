<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class FormErrorsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        Settings::define('points.enabled')->default(true);
        Settings::define('mail.port')->integer()->default(25)->rules('max:65535')->label('Port');
        Settings::define('mail.scheme')->select(['auto' => 'Automatic', 'smtps' => 'SSL'])->default('auto');
        Settings::define('mail.from')->string()->nullable();
    }

    public function test_an_empty_string_is_not_a_boolean(): void {
        foreach (['', ' ', null] as $value) {
            $this->assertFalse(Validator::make(['on' => $value], Settings::rules(['on' => 'points.enabled']))->passes());
        }

        $this->assertSame(
            ['points.enabled' => ['The Enabled setting is required.']],
            $this->errors(fn () => Settings::fill(['points.enabled' => '']))
        );
    }

    public function test_input_that_is_not_an_array_is_a_validation_error(): void {
        foreach (['abc', null] as $input) {
            $this->assertSame(['settings'], array_keys($this->errors(fn () => Settings::fill($input))));
        }
    }

    public function test_fill_and_set_name_the_setting_by_its_label(): void {
        $expected = ['mail.port' => ['The Port setting must be an integer.']];

        $this->assertSame($expected, $this->errors(fn () => Settings::fill(['mail.port' => 'x'])));
        $this->assertSame($expected, $this->errors(fn () => Settings::set('mail.port', 'x')));
    }

    public function test_select_errors_list_the_option_labels(): void {
        $this->assertSame(
            ['mail.scheme' => ['The Scheme setting must be one of: Automatic, SSL.']],
            $this->errors(fn () => Settings::set('mail.scheme', 'tls'))
        );
    }

    public function test_rules_use_the_custom_messages_and_attribute_names_of_the_validator(): void {
        $rules = Settings::rules(['port' => 'mail.port']);

        $this->assertSame(
            ['CUSTOM'],
            Validator::make(['port' => 70000], $rules, ['port.max' => 'CUSTOM'])->errors()->all()
        );
        $this->assertSame(
            ['NaN ATTR'],
            Validator::make(['port' => 'x'], $rules, ['port.integer' => 'NaN :attribute'], ['port' => 'ATTR'])->errors()->all()
        );
        $this->assertStringContainsString(
            'ATTR',
            Validator::make(['port' => 70000], $rules, [], ['port' => 'ATTR'])->errors()->first()
        );
    }

    public function test_fill_reads_values_by_field_name(): void {
        Settings::fill(
            ['port' => '587', 'title' => 'ignored'],
            ['port' => 'mail.port', 'from' => 'mail.from']
        );

        $this->assertSame(['mail.port' => 587], Settings::stored());
        $this->assertSame(
            ['port'],
            array_keys($this->errors(fn () => Settings::fill(['port' => 'x'], ['port' => 'mail.port'])))
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function errors(callable $action): array {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Expected a ValidationException.');
    }
}
