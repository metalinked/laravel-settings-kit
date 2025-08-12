<?php

namespace Metalinked\LaravelSettingsKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model {
    protected $fillable = [
        'preference_id',
        'user_id',
        'value',
    ];

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
        $this->table = config('settings-kit.tables.user_preferences', 'user_preferences');
    }

    /**
     * Get the preference that owns this user preference.
     */
    public function preference(): BelongsTo {
        return $this->belongsTo(Preference::class);
    }

    /**
     * Get the user that owns this preference.
     */
    public function user(): BelongsTo {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('settings-kit.user_model', 'App\Models\User');

        return $this->belongsTo($userModel);
    }
}
