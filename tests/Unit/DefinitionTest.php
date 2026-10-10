<?php

namespace Metalinked\LaravelSettingsKit\Tests\Unit;

use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;
use Metalinked\LaravelSettingsKit\Definition;
use Metalinked\LaravelSettingsKit\Exceptions\InvalidSettingValue;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\Team;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\Theme;
use Metalinked\LaravelSettingsKit\Tests\Fixtures\User;
use Metalinked\LaravelSettingsKit\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DefinitionTest extends TestCase {
    public function test_type_is_inferred_from_the_default(): void {
        $this->assertSame('boolean', (new Definition('a'))->default(true)->getType());
        $this->assertSame('integer', (new Definition('a'))->default(5)->getType());
        $this->assertSame('float', (new Definition('a'))->default(1.5)->getType());
        $this->assertSame('array', (new Definition('a'))->default(['x'])->getType());
        $this->assertSame('string', (new Definition('a'))->default('x')->getType());
        $this->assertSame('enum', (new Definition('a'))->default(Theme::Dark)->getType());
    }

    public function test_explicit_type_wins_over_inference(): void {
        $definition = (new Definition('a'))->float()->default(3);

        $this->assertSame('float', $definition->getType());
        $this->assertSame(3.0, $definition->getDefault());
    }

    public static function validValues(): array {
        return [
            'boolean from bool' => ['boolean', true, true],
            'boolean from "0"' => ['boolean', '0', false],
            'boolean from "true"' => ['boolean', 'true', true],
            'boolean from "off"' => ['boolean', 'off', false],
            'integer from int' => ['integer', 7, 7],
            'integer from string' => ['integer', '42', 42],
            'integer from whole float' => ['integer', 3.0, 3],
            'float from string' => ['float', '2.5', 2.5],
            'float from int' => ['float', 2, 2.0],
            'string from int' => ['string', 12, '12'],
            'array' => ['array', ['a' => 1], ['a' => 1]],
        ];
    }

    #[DataProvider('validValues')]
    public function test_normalize_accepts_and_coerces(string $type, mixed $input, mixed $expected): void {
        $this->assertSame($expected, (new Definition('a'))->type($type)->normalize($input));
    }

    public static function invalidValues(): array {
        return [
            'boolean from "maybe"' => ['boolean', 'maybe'],
            'boolean from 2' => ['boolean', 2],
            'integer from "abc"' => ['integer', 'abc'],
            'integer from 1.5' => ['integer', 1.5],
            'integer from bool' => ['integer', true],
            'float from "x"' => ['float', 'x'],
            'string from array' => ['string', ['a']],
            'array from string' => ['array', 'a'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_normalize_rejects_invalid_values(string $type, mixed $input): void {
        $this->expectException(InvalidSettingValue::class);

        (new Definition('a'))->type($type)->normalize($input);
    }

    public function test_null_is_only_accepted_when_nullable(): void {
        $this->assertNull((new Definition('a'))->nullable()->normalize(null));

        $this->expectException(InvalidSettingValue::class);
        (new Definition('a'))->normalize(null);
    }

    public function test_select_returns_the_option_with_its_original_type(): void {
        $definition = (new Definition('per_page'))->select([10, 25, 50]);

        $this->assertSame(25, $definition->normalize('25'));

        $this->expectException(InvalidSettingValue::class);
        $definition->normalize(30);
    }

    public function test_enum_accepts_cases_and_backing_values(): void {
        $definition = (new Definition('theme'))->enum(Theme::class);

        $this->assertSame(Theme::Dark, $definition->normalize('dark'));
        $this->assertSame(Theme::Light, $definition->normalize(Theme::Light));

        $this->expectException(InvalidSettingValue::class);
        $definition->normalize('purple');
    }

    public function test_enum_requires_a_backed_enum(): void {
        $this->expectException(InvalidArgumentException::class);

        (new Definition('a'))->enum(\stdClass::class);
    }

    public function test_encode_and_decode_round_trip(): void {
        $cases = [
            [(new Definition('a'))->boolean(), false],
            [(new Definition('a'))->float(), 1.0],
            [(new Definition('a'))->array(), ['x' => ['y' => 1]]],
            [(new Definition('a'))->enum(Theme::class), Theme::HighContrast],
            [(new Definition('a'))->string(), 'àèìòù'],
        ];

        foreach ($cases as [$definition, $value]) {
            $this->assertSame($value, $definition->decode($definition->encode($value)));
        }
    }

    public function test_encrypted_values_are_not_stored_in_plain_text(): void {
        $definition = (new Definition('api_key'))->encrypted();
        $raw = $definition->encode('secret-123');

        $this->assertStringNotContainsString('secret-123', $raw);
        $this->assertSame('secret-123', $definition->decode($raw));
    }

    public function test_decode_falls_back_when_the_stored_value_no_longer_fits(): void {
        $definition = (new Definition('per_page'))->select([10, 25]);

        $this->assertSame(10, $definition->decode('50', 10));
        $this->assertSame('fallback', (new Definition('a'))->encrypted()->decode('"not encrypted"', 'fallback'));
        $this->assertSame('fallback', (new Definition('a'))->decode('{broken', 'fallback'));
    }

    public function test_from_config_short_form(): void {
        $definition = Definition::fromConfig('site_name', 'My App');

        $this->assertSame('string', $definition->getType());
        $this->assertSame('My App', $definition->getDefault());
        $this->assertFalse($definition->isOverridable());
    }

    public function test_from_config_full_form(): void {
        $definition = Definition::fromConfig('appearance.theme', [
            'options' => ['light' => 'Light', 'dark' => 'Dark'],
            'default' => 'light',
            'overridable' => [User::class],
            'rules' => 'required',
            'meta' => ['icon' => 'moon'],
        ]);

        $this->assertSame('select', $definition->getType());
        $this->assertSame('appearance', $definition->getGroup());
        $this->assertTrue($definition->isOverridableBy(User::class));
        $this->assertFalse($definition->isOverridableBy(Team::class));
        $this->assertSame(['required'], $definition->getRules());
        $this->assertSame(['icon' => 'moon'], $definition->getMeta());
        $this->assertSame([
            ['value' => 'light', 'label' => 'Light'],
            ['value' => 'dark', 'label' => 'Dark'],
        ], $definition->getOptions());
    }

    public function test_from_config_rejects_unknown_options(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('defualt');

        Definition::fromConfig('a', ['type' => 'string', 'defualt' => 'x']);
    }

    public function test_default_must_match_the_type(): void {
        $this->expectException(InvalidArgumentException::class);

        (new Definition('a'))->integer()->default('abc')->getDefault();
    }

    public function test_labels_fall_back_from_translation_to_readable_key(): void {
        $definition = new Definition('appearance.font_size');

        $this->assertSame('Font Size', $definition->getLabel());
        $this->assertNull($definition->getDescription());

        Lang::addLines([
            'settings.appearance.font_size.label' => 'Mida de lletra',
            'settings.appearance.font_size.description' => 'En píxels',
        ], 'ca');
        app()->setLocale('ca');

        $this->assertSame('Mida de lletra', $definition->getLabel());
        $this->assertSame('En píxels', $definition->getDescription());
    }

    public function test_enum_option_labels(): void {
        $options = (new Definition('theme'))->enum(Theme::class)->getOptions();

        $this->assertSame(['value' => 'high-contrast', 'label' => 'High Contrast'], $options[2]);
    }

    public function test_group_defaults(): void {
        $this->assertSame('general', (new Definition('site_name'))->getGroup());
        $this->assertSame('mail', (new Definition('mail.from'))->getGroup());
        $this->assertSame('custom', (new Definition('mail.from'))->group('custom')->getGroup());
    }
}
