<?php

namespace App\Models\Sponsorship;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LeadDocument extends Model
{
    protected $table = 'sponsorship_lead_documents';

    protected $fillable = [
        'lead_id', 'uploaded_by_user_id',
        'original_name', 'path', 'mime_type', 'size', 'note',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
