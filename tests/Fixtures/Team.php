<?php

namespace Metalinked\LaravelSettingsKit\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Metalinked\LaravelSettingsKit\Concerns\HasSettings;

class Team extends Model {
    use HasSettings;
    use HasUuids;
    use SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];
}
