<?php

namespace App\Models\Sponsorship;

use Illuminate\Database\Eloquent\Model;

class LeadEmail extends Model
{
    protected $table = 'sponsorship_lead_emails';

    protected $fillable = ['lead_id', 'email', 'is_primary'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
