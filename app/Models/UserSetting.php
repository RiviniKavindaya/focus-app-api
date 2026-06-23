<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
   protected $fillable = [
        'user_id',
        'preferred_sprint_duration',
        'short_break_duration',
        'long_break_duration',
        'sprints_before_long_break',
        'daily_target_minutes',
        'weekly_target_minutes',
        'default_ambient_sound',
        'default_ambient_volume',
        'sound_effects_enabled',
        'theme',
    ];

    /**
     * Get the user that owns the settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
