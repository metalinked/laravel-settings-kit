<?php

namespace Metalinked\LaravelSettingsKit\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Metalinked\LaravelSettingsKit\Facades\Settings;
use Metalinked\LaravelSettingsKit\Tests\TestCase;

class CrossFieldRulesTest extends TestCase {
    public function test_rules_on_another_field_can_be_added_to_the_request(): void {
        Settings::define('payments.card')->default(false);
        Settings::define('payments.terminal')->string()->nullable();
        $rules = Settings::rules(['card' => 'payments.card', 'terminal' => 'payments.terminal']);
        $rules['terminal'][] = 'required_if_accepted:card';

        $this->assertTrue(Validator::make(['card' => '1', 'terminal' => ''], $rules)->errors()->has('terminal'));
        $this->assertTrue(Validator::make(['card' => 'on'], $rules)->errors()->has('terminal'));
        $this->assertTrue(Validator::make(['card' => '0', 'terminal' => ''], $rules)->passes());
        $this->assertTrue(Validator::make(['card' => '1', 'terminal' => '001'], $rules)->passes());
    }

    public function test_the_panel_lists_the_rules_written_as_strings(): void {
        Settings::define('code')->string()->rules(['min:3', fn () => null, 'max:9']);

        $this->assertSame(['min:3', 'max:9'], Settings::panel()[0]['rules']);
    }
}
