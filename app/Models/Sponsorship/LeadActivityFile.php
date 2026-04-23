<?php

namespace App\Models\Sponsorship;

use Illuminate\Database\Eloquent\Model;

class LeadActivityFile extends Model
{
    protected $table = 'sponsorship_lead_activity_files';

    protected $fillable = [
        'activity_id',
        'file_path',
        'file_name',
        'size',
        'mime_type',
    ];

    public function activity()
    {
        return $this->belongsTo(LeadActivity::class, 'activity_id');
    }
}
