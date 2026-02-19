<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $fillable = [
        'lead_id', 'agent_id', 'outcome',
        'duration', 'notes', 'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'duration'  => 'integer',
    ];

    // Relationships
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('called_at', today());
    }

    public function scopeForAgent($query, int $userId)
    {
        return $query->where('agent_id', $userId);
    }
}
