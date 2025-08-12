<?php

namespace Metalinked\LaravelSettingsKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreferenceContent extends Model {
    protected $fillable = [
        'preference_id',
        'lang',
        'title',
        'text',
    ];

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
        $this->table = config('settings-kit.tables.preference_contents', 'preference_contents');
    }

    /**
     * Get the preference that owns this content.
     */
    public function preference(): BelongsTo {
        return $this->belongsTo(Preference::class);
    }
}
