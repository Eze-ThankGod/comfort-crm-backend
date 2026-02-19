<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    protected $fillable = [
        'name', 'description', 'trigger_type',
        'conditions', 'actions', 'is_active', 'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions'    => 'array',
        'is_active'  => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('trigger_type', $trigger);
    }
}
