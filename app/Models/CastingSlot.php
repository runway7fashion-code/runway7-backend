<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CastingSlot extends Model
{
    protected $fillable = ['event_day_id', 'time', 'capacity', 'booked', 'slot_type'];

    protected function casts(): array
    {
        return ['time' => 'string'];
    }

    public function eventDay() { return $this->belongsTo(EventDay::class); }

    public function isAvailable(): bool { return $this->booked < $this->capacity; }

    public function availableSpots(): int { return $this->capacity - $this->booked; }
}
