<?php

namespace App\Models\Sponsorship;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $table = 'sponsorship_registrations';

    protected $fillable = [
        'lead_id',
        'sponsor_user_id',
        'company_id',
        'event_id',
        'package_id',
        'agreed_price',
        'downpayment',
        'installments_count',
        'notes',
        'status',
        'created_by_user_id',
        'onboarding_email_sent_at',
    ];

    protected $casts = [
        'agreed_price'             => 'decimal:2',
        'downpayment'              => 'decimal:2',
        'installments_count'       => 'integer',
        'onboarding_email_sent_at' => 'datetime',
    ];

    public const STATUSES = ['registered', 'onboarded', 'confirmed', 'cancelled'];

    public function lead()       { return $this->belongsTo(Lead::class, 'lead_id'); }
    public function sponsor()    { return $this->belongsTo(User::class, 'sponsor_user_id'); }
    public function company()    { return $this->belongsTo(Company::class, 'company_id'); }
    public function event()      { return $this->belongsTo(Event::class, 'event_id'); }
    public function package()    { return $this->belongsTo(Package::class, 'package_id'); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by_user_id'); }

    public function documents()
    {
        return $this->hasMany(RegistrationDocument::class, 'registration_id');
    }
}
