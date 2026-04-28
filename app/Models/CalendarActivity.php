<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarActivity extends Model
{
    protected $fillable = [
        'user_id', 'created_by_user_id',
        'area', 'type', 'title', 'description',
        'scheduled_at', 'completed_at', 'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const TYPES = ['call', 'meeting', 'note'];
    public const STATUSES = ['pending', 'completed', 'cancelled', 'not_completed'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
