<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FocusSession extends Model
{
   protected $fillable = [
        'task_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'is_completed_sprint',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_completed_sprint' => 'boolean',
    ];

    /**
     * Get the parent task associated with this focus block.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
