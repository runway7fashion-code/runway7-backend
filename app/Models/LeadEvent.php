<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadEvent extends Model
{
    protected $fillable = ['lead_id', 'event_id', 'status'];

    public function lead()
    {
        return $this->belongsTo(DesignerLead::class, 'lead_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
