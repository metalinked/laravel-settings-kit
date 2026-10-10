<?php

namespace Metalinked\LaravelSettingsKit\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Metalinked\LaravelSettingsKit\Concerns\HasSettings;

class User extends Authenticatable {
    use HasSettings;

    public $timestamps = false;

    protected $guarded = [];
}
