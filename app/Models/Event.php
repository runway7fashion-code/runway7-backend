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
        'name', 'slug', 'city', 'venue', 'venue_address', 'venue_latitude', 'venue_longitude',
        'timezone', 'start_date', 'end_date', 'status', 'settings', 'description',
        'model_number_start', 'call_time', 'hmua_address', 'materials_deadline_default',
        'shared_runway_logo_folder_id', 'shared_hair_moodboard_folder_id', 'shared_makeup_moodboard_folder_id',
        'casting_invitation_expiration_hours',
    ];

    /**
     * Effective materials deadline for a (designer, event) pair.
     * Returns the per-designer override if set, otherwise the event default.
     * Date string (Y-m-d) or null.
     */
    public static function effectiveMaterialsDeadline(int $designerId, int $eventId): ?string
    {
        $row = \DB::table('event_designer as ed')
            ->join('events as e', 'e.id', '=', 'ed.event_id')
            ->where('ed.designer_id', $designerId)
            ->where('ed.event_id', $eventId)
            ->selectRaw('COALESCE(ed.materials_deadline, e.materials_deadline_default) as deadline')
            ->value('deadline');

        return $row ?: null;
    }

    protected function casts(): array
    {
        return [
            'start_date'                 => 'date',
            'end_date'                   => 'date',
            'materials_deadline_default' => 'date',
            'settings'                   => 'array',
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
            ->withPivot(['status', 'package_id', 'looks', 'model_casting_enabled', 'media_package', 'custom_background', 'courtesy_tickets', 'package_price', 'notes'])
            ->withTimestamps();
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'event_staff', 'event_id', 'user_id')
            ->withPivot(['assigned_role', 'status', 'checked_in_at', 'notes', 'area'])
            ->withTimestamps();
    }

    public function volunteers()
    {
        return $this->belongsToMany(User::class, 'event_volunteer', 'event_id', 'volunteer_id')
            ->withPivot(['assigned_role', 'status', 'checked_in_at', 'notes', 'area'])
            ->withTimestamps();
    }

    public function mediaUsers()
    {
        return $this->belongsToMany(User::class, 'event_media', 'event_id', 'media_id')
            ->withPivot(['status', 'checked_in_at', 'notes'])
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

    public function showsWithDesignersCount(): int
    {
        $showIds = Show::whereIn('event_day_id', $this->eventDays()->pluck('id'))->pluck('id');
        return \Illuminate\Support\Facades\DB::table('show_designer')
            ->whereIn('show_id', $showIds)
            ->distinct('show_id')
            ->count('show_id');
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
