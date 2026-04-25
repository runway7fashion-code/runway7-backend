<?php

namespace App\Models\Sponsorship;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    protected $table = 'sponsorship_lead_activities';

    protected $fillable = [
        'lead_id',
        'created_by_user_id',
        'assigned_to_user_id',
        'edited_by_user_id',
        'type',
        'title',
        'description',
        'scheduled_at',
        'completed_at',
        'edited_at',
        'status',
        'is_contract',
        'mailgun_message_id',
        'delivery_status',
        'delivery_error',
        'delivered_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'edited_at'    => 'datetime',
        'delivered_at' => 'datetime',
        'is_contract'  => 'boolean',
    ];

    public const TYPES = [
        'call'          => ['label' => 'Call',          'color' => '#3B82F6'],
        'email'         => ['label' => 'Email',         'color' => '#8B5CF6'],
        'meeting'       => ['label' => 'Meeting',       'color' => '#10B981'],
        'note'          => ['label' => 'Note',          'color' => '#6B7280'],
        'status_change' => ['label' => 'Status Change', 'color' => '#F97316'],
        'assignment'    => ['label' => 'Assignment',    'color' => '#EAB308'],
        'system'        => ['label' => 'System',        'color' => '#9CA3AF'],
    ];

    public const STATUSES = ['pending', 'completed', 'cancelled', 'not_completed'];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }

    public function files()
    {
        return $this->hasMany(LeadActivityFile::class, 'activity_id');
    }
}
