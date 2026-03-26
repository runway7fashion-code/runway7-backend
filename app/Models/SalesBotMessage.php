<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesBotMessage extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message',
        'action_url', 'action_label', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    const TYPES = [
        'new_lead'  => ['icon' => '🆕', 'color' => '#3B82F6'],
        'reminder'  => ['icon' => '⏰', 'color' => '#F97316'],
        'overdue'   => ['icon' => '🔴', 'color' => '#EF4444'],
        'alert'     => ['icon' => '⚠️', 'color' => '#EAB308'],
        'info'      => ['icon' => 'ℹ️', 'color' => '#6B7280'],
        'converted' => ['icon' => '🎉', 'color' => '#10B981'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
