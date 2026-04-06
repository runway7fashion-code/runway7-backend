<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeCard extends Model
{
    protected $fillable = [
        'title', 'image_url', 'action_type', 'action_value',
        'target_roles', 'event_id', 'order', 'status',
        'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
        ];
    }

    public function event() { return $this->belongsTo(Event::class); }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where(fn($q) => $q->whereNull('target_roles')->orWhereJsonContains('target_roles', $role));
    }
}
