<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'title',
        'description',
        'scheduled_at',
        'ends_at',
        'completed_at',
        'status',
        'file_path',
        'file_name',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ends_at'      => 'datetime',
        'completed_at' => 'datetime',
    ];

    const TYPES = [
        'call'          => ['label' => 'Call',           'icon' => 'phone',    'color' => '#3B82F6'],
        'email'         => ['label' => 'Email',          'icon' => 'envelope', 'color' => '#8B5CF6'],
        'meeting'       => ['label' => 'Meeting',        'icon' => 'users',    'color' => '#10B981'],
        'note'          => ['label' => 'Note',           'icon' => 'pencil',   'color' => '#6B7280'],
        'status_change' => ['label' => 'Status Change',  'icon' => 'refresh',  'color' => '#F97316'],
        'assignment'    => ['label' => 'Assignment',     'icon' => 'user',     'color' => '#EAB308'],
        'system'        => ['label' => 'System',         'icon' => 'cog',      'color' => '#9CA3AF'],
    ];

    public function lead()
    {
        return $this->belongsTo(DesignerLead::class, 'lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(LeadActivityFile::class, 'activity_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }
}
