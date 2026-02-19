<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_id', 'assigned_to', 'created_by', 'type',
        'status', 'title', 'description', 'due_date',
        'completed_at', 'reminder_sent',
    ];

    protected $casts = [
        'due_date'      => 'datetime',
        'completed_at'  => 'datetime',
        'reminder_sent' => 'boolean',
    ];

    // Relationships
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', 'pending')
                     ->whereDate('due_date', today());
    }

    public function scopeForAgent($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }
}
