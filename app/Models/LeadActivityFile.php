<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadActivityFile extends Model
{
    protected $fillable = ['activity_id', 'file_path', 'file_name'];

    public function activity()
    {
        return $this->belongsTo(LeadActivity::class, 'activity_id');
    }
}
