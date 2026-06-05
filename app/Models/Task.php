<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'notes',
        'estimated_minutes',
        'actual_minutes',
        'status',
        'queue_order',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function focusSessions()
    {
        return $this->hasMany(FocusSession::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['queued', 'active', 'paused']);
    }

    // ── Helpers ────────────────────────────────────────────────────
    public function sprintCount(): int
    {
        return (int) ceil($this->estimated_minutes / 25);
    }
}