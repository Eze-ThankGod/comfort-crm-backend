<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'source', 'status',
        'assigned_to', 'created_by', 'property_type',
        'budget_min', 'budget_max', 'budget', 'location',
        'preferred_location', 'intent', 'finishing_type',
        'inspection_at', 'notes', 'tags', 'portal_id',
        'last_contacted_at',
    ];

    protected $casts = [
        'tags'               => 'array',
        'budget_min'         => 'decimal:2',
        'budget_max'         => 'decimal:2',
        'budget'             => 'decimal:2',
        'inspection_at'      => 'datetime',
        'last_contacted_at'  => 'datetime',
    ];

    // Relationships
    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function callLogs()
    {
        return $this->hasMany(CallLog::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    // Scopes
    public function scopeForAgent($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('location', 'like', "%{$term}%")
              ->orWhere('preferred_location', 'like', "%{$term}%");
        });
    }
}
