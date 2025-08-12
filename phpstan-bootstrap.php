<?php

// PHPStan bootstrap file to help with Eloquent model resolution

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

if (!function_exists('resolveModel')) {
    function resolveModel(): void {
        // Help PHPStan understand Eloquent methods
    }
}
