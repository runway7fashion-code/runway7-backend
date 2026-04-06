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

    // Lead status (person/marketing)
    const STATUSES = [
        'new'       => ['label' => 'New',       'color' => '#3B82F6'],
        'qualified' => ['label' => 'Qualified', 'color' => '#8B5CF6'],
        'client'    => ['label' => 'Client',    'color' => '#10B981'],
        'lost'      => ['label' => 'Lost',      'color' => '#EF4444'],
        'spam'      => ['label' => 'Spam',      'color' => '#1F2937'],
    ];

    // Opportunity status (sales/event)
    const OPPORTUNITY_STATUSES = [
        'new'        => ['label' => 'New',         'color' => '#3B82F6'],
        'contacted'  => ['label' => 'Contacted',   'color' => '#EAB308'],
        'follow_up'  => ['label' => 'Follow Up',   'color' => '#F97316'],
        'negotiating'=> ['label' => 'Negotiating',  'color' => '#8B5CF6'],
        'converted'  => ['label' => 'Sale',        'color' => '#10B981'],
        'lost'       => ['label' => 'Lost',        'color' => '#EF4444'],
    ];

    const SOURCES = [
        'website_designers' => 'Web Designers',
        'website_organic'   => 'Web Organic',
        'facebook'          => 'Facebook',
        'instagram'         => 'Instagram',
        'tiktok'            => 'TikTok',
        'google_ads'        => 'Google Ads',
        'referral'          => 'Referral',
        'cold_call'         => 'Cold Call',
        'event'             => 'In-person Event',
        'email_campaign'    => 'Email Campaign',
        'whatsapp'          => 'WhatsApp',
        'manual'            => 'Manual',
        'other'             => 'Other',
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

    /**
     * Recalculate lead status based on event statuses.
     */
    public function recalculateStatus(): void
    {
        $eventStatuses = $this->leadEvents()->pluck('status')->toArray();

        if (empty($eventStatuses)) return;

        // If at least 1 event is converted → client
        if (in_array('converted', $eventStatuses)) {
            if ($this->status !== 'client') {
                $this->update(['status' => 'client']);
            }
            return;
        }

        $negativeStatuses = ['lost'];

        // If ALL events are negative → lost
        $allNegative = collect($eventStatuses)->every(fn($s) => in_array($s, $negativeStatuses));
        if ($allNegative) {
            if ($this->status !== 'lost' && $this->status !== 'spam') {
                $this->update(['status' => 'lost']);
            }
            return;
        }
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
