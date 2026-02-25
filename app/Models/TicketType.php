<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_day_id', 'name', 'zone', 'price', 'capacity', 'sold', 'status',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function eventDay() { return $this->belongsTo(EventDay::class); }
    public function tickets() { return $this->hasMany(Ticket::class); }

    public function getAvailableAttribute(): int
    {
        return max(0, $this->capacity - $this->sold);
    }
}
