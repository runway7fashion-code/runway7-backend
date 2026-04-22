<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplementaryGuest extends Model
{
    protected $table = 'complementary_guests';

    protected $fillable = [
        'guest_user_id',
        'host_user_id',
        'type',
        'event_id',
        'event_day_id',
        'show_id',
        'notes',
    ];

    public const TYPES = ['sponsor_guest', 'designer_guest', 'giveaway', 'other'];

    public function guest() { return $this->belongsTo(User::class, 'guest_user_id'); }
    public function host()  { return $this->belongsTo(User::class, 'host_user_id'); }
    public function event() { return $this->belongsTo(Event::class, 'event_id'); }
    public function eventDay() { return $this->belongsTo(EventDay::class, 'event_day_id'); }
    public function show()  { return $this->belongsTo(Show::class, 'show_id'); }
}
