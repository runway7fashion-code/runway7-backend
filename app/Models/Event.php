<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'city', 'venue', 'timezone',
        'start_date', 'end_date', 'status', 'settings', 'description',
        'model_number_start',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'settings'   => 'array',
        ];
    }

    // --- Relations ---
    public function eventDays() { return $this->hasMany(EventDay::class)->orderBy('date')->orderBy('order'); }

    // Alias for legacy code
    public function days() { return $this->eventDays(); }

    public function models()
    {
        return $this->belongsToMany(User::class, 'event_model', 'event_id', 'model_id')
            ->withPivot(['participation_number', 'casting_time', 'casting_checked_in_at', 'casting_status', 'status', 'checked_in_at'])
            ->withTimestamps();
    }

    public function designers()
    {
        return $this->belongsToMany(User::class, 'event_designer', 'event_id', 'designer_id')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'event_staff', 'event_id', 'user_id')
            ->withPivot(['assigned_role', 'status', 'checked_in_at', 'notes'])
            ->withTimestamps();
    }

    // --- Helpers ---
    public function showDays()
    {
        return $this->eventDays()->where('type', 'show_day');
    }

    public function castingDay()
    {
        return $this->eventDays()->where('type', 'casting')->first();
    }

    public function daysCount(): int
    {
        return $this->eventDays()->count();
    }

    public function totalShows(): int
    {
        return Show::whereIn('event_day_id', $this->eventDays()->pluck('id'))->count();
    }

    public function assignedDesignersCount(): int
    {
        $showIds = Show::whereIn('event_day_id', $this->eventDays()->pluck('id'))->pluck('id');
        return \Illuminate\Support\Facades\DB::table('show_designer')
            ->whereIn('show_id', $showIds)
            ->distinct('designer_id')
            ->count('designer_id');
    }

    public function totalAssignedShowSlots(): int
    {
        $showIds = Show::whereIn('event_day_id', $this->eventDays()->pluck('id'))->pluck('id');
        return \Illuminate\Support\Facades\DB::table('show_designer')
            ->whereIn('show_id', $showIds)
            ->count();
    }

    public function isUpcoming(): bool
    {
        return $this->start_date > Carbon::today();
    }

    public function isActive(): bool
    {
        return $this->start_date <= Carbon::today() && $this->end_date >= Carbon::today();
    }

    public function isCompleted(): bool
    {
        return $this->end_date < Carbon::today();
    }
}
