<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // Role constants grouped by category
    const ROLES_INTERNAL = ['admin', 'accounting', 'operation', 'tickets_manager', 'marketing', 'public_relations', 'sales', 'creative'];
    const ROLES_PARTICIPANT = ['designer', 'model', 'media', 'volunteer', 'staff', 'assistant'];
    const ROLES_ATTENDEE = ['attendee', 'vip', 'influencer', 'press', 'sponsor', 'complementary'];

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'password',
        'role', 'status', 'sales_type', 'is_available', 'last_login_at', 'welcome_email_sent_at', 'sms_sent_at', 'profile_picture',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'last_login_at'           => 'datetime',
            'welcome_email_sent_at'   => 'datetime',
            'sms_sent_at'             => 'datetime',
            'password' => 'hashed',
        ];
    }

    // --- Accessors ---
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getRoleCategoryAttribute(): string
    {
        return $this->getRoleCategory();
    }

    // --- Role category helper ---
    public function getRoleCategory(): string
    {
        if (in_array($this->role, self::ROLES_INTERNAL)) return 'internal';
        if (in_array($this->role, self::ROLES_PARTICIPANT)) return 'participant';
        return 'attendee';
    }

    // --- Role checks: Internal ---
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isAccounting(): bool { return $this->role === 'accounting'; }
    public function isOperation(): bool { return $this->role === 'operation'; }
    public function isTicketsManager(): bool { return $this->role === 'tickets_manager'; }
    public function isMarketing(): bool { return $this->role === 'marketing'; }
    public function isPublicRelations(): bool { return $this->role === 'public_relations'; }
    public function isSales(): bool { return $this->role === 'sales'; }
    public function isInternalTeam(): bool { return in_array($this->role, self::ROLES_INTERNAL); }

    // --- Role checks: Participants ---
    public function isModel(): bool { return $this->role === 'model'; }
    public function isDesigner(): bool { return $this->role === 'designer'; }
    public function isMedia(): bool { return $this->role === 'media'; }
    public function isVolunteer(): bool { return $this->role === 'volunteer'; }
    public function isAssistant(): bool { return $this->role === 'assistant'; }
    public function isEventParticipant(): bool { return in_array($this->role, self::ROLES_PARTICIPANT); }

    // --- Role checks: Attendees ---
    public function isAttendee(): bool { return $this->role === 'attendee'; }
    public function isVip(): bool { return $this->role === 'vip'; }
    public function isInfluencer(): bool { return $this->role === 'influencer'; }
    public function isPress(): bool { return $this->role === 'press'; }
    public function isSponsor(): bool { return $this->role === 'sponsor'; }
    public function isAttendeeType(): bool { return in_array($this->role, self::ROLES_ATTENDEE); }

    // Backward compat
    public function fullName(): string { return $this->full_name; }

    // --- Scopes ---
    public function scopeRole($query, string $role) { return $query->where('role', $role); }
    public function scopeActive($query) { return $query->where('status', 'active'); }
    public function scopeModels($query) { return $query->where('role', 'model'); }
    public function scopeDesigners($query) { return $query->where('role', 'designer'); }
    public function scopeMedia($query) { return $query->where('role', 'media'); }
    public function scopeAdmins($query) { return $query->where('role', 'admin'); }
    public function scopeInternalTeam($query) { return $query->whereIn('role', self::ROLES_INTERNAL); }
    public function scopeParticipants($query) { return $query->whereIn('role', self::ROLES_PARTICIPANT); }
    public function scopeAttendees($query) { return $query->whereIn('role', self::ROLES_ATTENDEE); }
    public function scopeVips($query) { return $query->where('role', 'vip'); }
    public function scopePress($query) { return $query->where('role', 'press'); }
    public function scopeSponsors($query) { return $query->where('role', 'sponsor'); }
    public function scopeByCategory($query, string $category)
    {
        return match($category) {
            'internal' => $query->whereIn('role', self::ROLES_INTERNAL),
            'participant' => $query->whereIn('role', self::ROLES_PARTICIPANT),
            'attendee' => $query->whereIn('role', self::ROLES_ATTENDEE),
            default => $query,
        };
    }

    // --- Profiles ---
    public function modelProfile() { return $this->hasOne(ModelProfile::class); }
    public function designerProfile() { return $this->hasOne(DesignerProfile::class); }
    public function volunteerProfile() { return $this->hasOne(VolunteerProfile::class); }
    public function volunteerSchedules() { return $this->hasMany(VolunteerSchedule::class); }
    public function mediaProfile() { return $this->hasOne(MediaProfile::class); }
    public function mediaAssistants() { return $this->hasMany(MediaAssistant::class, 'media_id'); }
    public function pressProfile() { return $this->hasOne(PressProfile::class); }
    public function sponsorProfile() { return $this->hasOne(SponsorProfile::class); }

    // --- Communication Logs ---
    public function communicationLogs() { return $this->hasMany(CommunicationLog::class); }

    // --- Payment Plans ---
    public function paymentPlans() { return $this->hasMany(DesignerPaymentPlan::class, 'designer_id'); }

    // --- Support Cases ---
    public function supportCases() { return $this->hasMany(SupportCase::class, 'designer_id'); }
    public function contactEmails() { return $this->hasMany(DesignerContactEmail::class, 'designer_id'); }

    // --- Products & Commerce ---
    public function designerProducts() { return $this->hasMany(DesignerProduct::class, 'designer_id'); }
    public function orders() { return $this->hasMany(Order::class); }
    public function deviceTokens() { return $this->hasMany(DeviceToken::class); }

    // --- Events ---
    public function eventsAsModel()
    {
        return $this->belongsToMany(Event::class, 'event_model', 'model_id', 'event_id')
            ->withPivot(['participation_number', 'status', 'checked_in_at'])
            ->withTimestamps();
    }

    public function eventsAsDesigner()
    {
        return $this->belongsToMany(Event::class, 'event_designer', 'designer_id', 'event_id')
            ->withPivot(['status', 'package_id', 'looks', 'assistants', 'model_casting_enabled', 'media_package', 'custom_background', 'courtesy_tickets', 'package_price', 'notes'])
            ->withTimestamps();
    }

    public function salesRegistrations() { return $this->hasMany(SalesRegistration::class, 'designer_id'); }
    public function designerAssistants() { return $this->hasMany(DesignerAssistant::class, 'designer_id'); }
    public function designerMaterials() { return $this->hasMany(DesignerMaterial::class, 'designer_id'); }
    public function designerDisplays() { return $this->hasMany(DesignerDisplay::class, 'designer_id'); }
    public function fittingAssignments() { return $this->hasMany(FittingAssignment::class, 'designer_id'); }
    public function eventPasses() { return $this->hasMany(EventPass::class, 'user_id'); }
    public function checkins()    { return $this->hasMany(Checkin::class); }

    public function eventsAsStaff()
    {
        return $this->belongsToMany(Event::class, 'event_staff', 'user_id', 'event_id')
            ->withPivot(['assigned_role', 'status', 'checked_in_at', 'notes', 'area'])
            ->withTimestamps();
    }

    public function eventsAsVolunteer()
    {
        return $this->belongsToMany(Event::class, 'event_volunteer', 'volunteer_id', 'event_id')
            ->withPivot(['assigned_role', 'status', 'checked_in_at', 'notes', 'area'])
            ->withTimestamps();
    }

    public function eventsAsMedia()
    {
        return $this->belongsToMany(Event::class, 'event_media', 'media_id', 'event_id')
            ->withPivot(['status', 'checked_in_at', 'notes'])
            ->withTimestamps();
    }

    public function shows()
    {
        return $this->belongsToMany(Show::class, 'show_model', 'model_id', 'show_id')
            ->withPivot(['status', 'walk_order', 'confirmed_at', 'notes', 'rejection_reason', 'requested_at', 'responded_at', 'designer_id'])
            ->withTimestamps();
    }

    public function showRequests()
    {
        return $this->belongsToMany(Show::class, 'show_model', 'model_id', 'show_id')
            ->withPivot(['status', 'walk_order', 'confirmed_at', 'notes', 'rejection_reason', 'requested_at', 'responded_at', 'designer_id'])
            ->withTimestamps();
    }

    public function eventsAsModelWithCasting()
    {
        return $this->belongsToMany(Event::class, 'event_model', 'model_id', 'event_id')
            ->withPivot(['participation_number', 'casting_time', 'casting_checked_in_at', 'casting_status', 'status', 'checked_in_at', 'shopify_order_number', 'model_tag'])
            ->withTimestamps();
    }

    public function designedShows()
    {
        return $this->belongsToMany(Show::class, 'show_designer', 'designer_id', 'show_id')
            ->withPivot(['order', 'collection_name', 'status', 'notes'])
            ->withTimestamps();
    }
}
