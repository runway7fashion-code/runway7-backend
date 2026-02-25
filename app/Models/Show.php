<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Show extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_day_id', 'name', 'scheduled_time',
        'order', 'status', 'model_slots', 'notes',
    ];

    protected $appends = ['formatted_time'];

    public function eventDay() { return $this->belongsTo(EventDay::class); }

    public function designers()
    {
        return $this->belongsToMany(User::class, 'show_designer', 'show_id', 'designer_id')
            ->using(ShowDesigner::class)
            ->withPivot(['order', 'collection_name', 'status', 'notes'])
            ->withTimestamps()
            ->orderByPivot('order');
    }

    public function models()
    {
        return $this->belongsToMany(User::class, 'show_model', 'show_id', 'model_id')
            ->withPivot(['designer_id', 'status', 'walk_order', 'requested_by', 'confirmed_at',
                         'requested_at', 'responded_at', 'rejection_reason', 'notes'])
            ->withTimestamps();
    }

    public function modelsForDesigner(int $designerId)
    {
        return $this->models()->wherePivot('designer_id', $designerId);
    }

    public function getFormattedTimeAttribute(): string
    {
        if (!$this->scheduled_time) return '';
        try {
            return Carbon::createFromFormat('H:i:s', $this->scheduled_time)->format('g:i A');
        } catch (\Exception $e) {
            return Carbon::createFromFormat('H:i', $this->scheduled_time)->format('g:i A');
        }
    }

    public function designerCount(): int
    {
        return $this->designers()->count();
    }

    public function previousShow(): ?Show
    {
        return Show::where('event_day_id', $this->event_day_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }

    public function nextShow(): ?Show
    {
        return Show::where('event_day_id', $this->event_day_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }
}
