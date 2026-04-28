<?php

namespace App\Models\Sponsorship;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{

    protected $table = 'sponsorship_leads';

    protected $fillable = [
        'company_id',
        'first_name', 'last_name', 'phone', 'charge',
        'linkedin_url', 'website_url', 'instagram',
        'category_id',
        'status', 'source', 'source_detail',
        'registered_by_user_id', 'assigned_to_user_id',
        'is_contract_winner', 'converted_user_id',
        'notes',
        'last_contacted_at', 'last_email_sent_at', 'last_email_status', 'last_email_type',
    ];

    protected $casts = [
        'is_contract_winner'  => 'boolean',
        'last_contacted_at'   => 'datetime',
        'last_email_sent_at'  => 'datetime',
    ];

    public const STATUSES = [
        'nuevo'               => ['label' => 'New',             'color' => '#3B82F6'],
        'contactado'          => ['label' => 'Contacted',       'color' => '#8B5CF6'],
        'interesado'          => ['label' => 'Interested',      'color' => '#EAB308'],
        'contrato'            => ['label' => 'Contract sent',   'color' => '#F97316'],
        'cerrado'             => ['label' => 'Closed',          'color' => '#10B981'],
        'siguiente_temporada' => ['label' => 'Next season',     'color' => '#6366F1'],
        'rechazado'           => ['label' => 'Rejected',        'color' => '#EF4444'],
        'perdido'             => ['label' => 'Lost',            'color' => '#64748B'],
    ];

    public const SOURCES = [
        'website', 'manual', 'referral', 'event',
        'instagram', 'facebook', 'linkedin', 'tiktok', 'ads', 'other',
    ];

    public const EMAIL_STATUSES = ['sent', 'failed'];

    /**
     * Tipos de email para outreach (single-send y bulk).
     * Se persisten en sponsorship_lead_activities.email_type y se cachea
     * el último en sponsorship_leads.last_email_type para mostrarlo en la tabla.
     */
    public const EMAIL_TYPES = [
        'intro_email' => 'Intro Email',
        'reminder_1'  => 'Reminder 1',
        'reminder_2'  => 'Reminder 2',
        'reminder_3'  => 'Reminder 3',
        'reminder_4'  => 'Reminder 4',
        'reminder_5'  => 'Reminder 5',
        'follow_up'   => 'Follow up',
    ];

    // --- Relaciones ---
    public function company()        { return $this->belongsTo(Company::class, 'company_id'); }
    public function category()       { return $this->belongsTo(Category::class, 'category_id'); }
    public function assignedTo()     { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
    public function registeredBy()   { return $this->belongsTo(User::class, 'registered_by_user_id'); }
    public function convertedUser()  { return $this->belongsTo(User::class, 'converted_user_id'); }

    public function emails()
    {
        return $this->hasMany(LeadEmail::class, 'lead_id');
    }

    public function primaryEmail()
    {
        return $this->hasOne(LeadEmail::class, 'lead_id')->where('is_primary', true);
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'sponsorship_lead_events', 'lead_id', 'event_id')
            ->withTimestamps();
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'sponsorship_lead_tag', 'lead_id', 'tag_id')
            ->withTimestamps();
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class, 'lead_id')->orderByDesc('created_at');
    }
}
