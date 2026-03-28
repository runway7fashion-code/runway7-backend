<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DesignerLead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'country',
        'company_name',
        'retail_category',
        'website_url',
        'instagram',
        'designs_ready',
        'budget',
        'past_shows',
        'event_id',
        'preferred_contact_time',
        'status',
        'assigned_to',
        'converted_designer_id',
        'source',
        'notes',
        'last_contacted_at',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
    ];

    const STATUSES = [
        'new'        => ['label' => 'Nuevo',       'color' => '#3B82F6'],
        'contacted'  => ['label' => 'Contactado',  'color' => '#EAB308'],
        'follow_up'  => ['label' => 'Seguimiento', 'color' => '#F97316'],
        'negotiating'=> ['label' => 'Negociando',  'color' => '#8B5CF6'],
        'converted'  => ['label' => 'Venta',       'color' => '#10B981'],
        'no_response'=> ['label' => 'No Responde', 'color' => '#9CA3AF'],
        'no_contact' => ['label' => 'Sin Contacto','color' => '#6B7280'],
        'lost'       => ['label' => 'No Venta',    'color' => '#EF4444'],
        'spam'       => ['label' => 'Spam',         'color' => '#1F2937'],
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'lead_events', 'lead_id', 'event_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function leadEvents()
    {
        return $this->hasMany(LeadEvent::class, 'lead_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedDesigner()
    {
        return $this->belongsTo(User::class, 'converted_designer_id');
    }

    public function tags()
    {
        return $this->belongsToMany(LeadTag::class, 'lead_tag', 'lead_id', 'tag_id')->withTimestamps();
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class, 'lead_id')->orderBy('created_at', 'desc');
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status]['color'] ?? '#9CA3AF';
    }
}
