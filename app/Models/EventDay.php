<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'date', 'label', 'type',
        'start_time', 'end_time', 'order', 'status', 'description',
        'fitting_start', 'fitting_end', 'fitting_interval',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function event() { return $this->belongsTo(Event::class); }
    public function shows() { return $this->hasMany(Show::class); }
    public function ticketTypes() { return $this->hasMany(TicketType::class); }
    public function castingSlots() { return $this->hasMany(CastingSlot::class)->orderBy('time'); }
    public function fittingSlots() { return $this->hasMany(FittingSlot::class)->orderBy('time'); }

    public function isShowDay(): bool { return $this->type === 'show_day'; }
    public function isCasting(): bool { return $this->type === 'casting'; }
    public function isSetup(): bool { return $this->type === 'setup'; }
    public function isCeremony(): bool { return $this->type === 'ceremony'; }
    public function isFitting(): bool { return $this->type === 'fitting'; }
    public function hasFitting(): bool { return $this->fitting_start && $this->fitting_end; }
}
