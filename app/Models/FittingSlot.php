<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FittingSlot extends Model
{
    protected $fillable = [
        'event_day_id', 'time', 'capacity',
    ];

    public function eventDay() { return $this->belongsTo(EventDay::class); }
    public function assignments() { return $this->hasMany(FittingAssignment::class)->orderBy('created_at'); }
    public function designers() { return $this->belongsToMany(User::class, 'fitting_assignments', 'fitting_slot_id', 'designer_id')->withTimestamps(); }

    public function assignedCount(): int { return $this->assignments()->count(); }
    public function isAvailable(): bool { return $this->assignedCount() < $this->capacity; }
    public function availableSpots(): int { return max(0, $this->capacity - $this->assignedCount()); }
}
