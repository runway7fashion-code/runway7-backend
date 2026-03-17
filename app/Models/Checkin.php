<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    protected $fillable = [
        'user_id', 'event_id', 'event_day_id',
        'type', 'checked_at', 'method', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function user()     { return $this->belongsTo(User::class); }
    public function event()    { return $this->belongsTo(Event::class); }
    public function eventDay() { return $this->belongsTo(EventDay::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }

    /** Roles que registran entrada Y salida por día */
    public static function rolesWithEntryExit(): array
    {
        return ['volunteer', 'staff'];
    }

    public static function needsEntryExit(User $user): bool
    {
        return in_array($user->role, self::rolesWithEntryExit());
    }
}
