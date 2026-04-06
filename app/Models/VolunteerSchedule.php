<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunteerSchedule extends Model
{
    protected $fillable = [
        'user_id', 'event_id', 'event_day_id', 'start_time', 'end_time',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'string',
            'end_time'   => 'string',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function eventDay()
    {
        return $this->belongsTo(EventDay::class);
    }
}
