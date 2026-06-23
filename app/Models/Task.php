<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'notes',
        'estimated_minutes',
        'status', // 'queued', 'active', 'paused', 'completed'
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who owns this task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all focus intervals logged for this task.
     */
    public function focusSessions(): HasMany
    {
        return $this->hasMany(FocusSession::class);
    }

    /**
     * Accessor: Calculate total actual minutes spent on this task dynamically.
     * Usage: $task->actual_minutes
     */
    public function getActualMinutesAttribute(): int
    {
        $totalSeconds = $this->focusSessions()->sum('duration_seconds');
        return (int) round($totalSeconds / 60);
    }

    /**
     * Accessor: Count how many full sprints were successfully completed.
     * Usage: $task->completed_sprints_count
     */
    public function getCompletedSprintsCountAttribute(): int
    {
        return $this->focusSessions()->where('is_completed_sprint', true)->count();
    }

    /**
     * Accessor: Check if the user went over their original estimate.
     * Usage: $task->is_overrun
     */
    public function getIsOverrunAttribute(): bool
    {
        return $this->actual_minutes > $this->estimated_minutes;
    }
}