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
        'completed_at',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const TYPES = [
        'call'          => ['label' => 'Llamada',          'icon' => 'phone',    'color' => '#3B82F6'],
        'email'         => ['label' => 'Email',            'icon' => 'envelope', 'color' => '#8B5CF6'],
        'meeting'       => ['label' => 'Reunión',          'icon' => 'users',    'color' => '#10B981'],
        'note'          => ['label' => 'Nota',             'icon' => 'pencil',   'color' => '#6B7280'],
        'status_change' => ['label' => 'Cambio de Estado', 'icon' => 'refresh',  'color' => '#F97316'],
        'assignment'    => ['label' => 'Asignación',       'icon' => 'user',     'color' => '#EAB308'],
        'system'        => ['label' => 'Sistema',          'icon' => 'cog',      'color' => '#9CA3AF'],
    ];

    public function lead()
    {
        return $this->belongsTo(DesignerLead::class, 'lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }
}
