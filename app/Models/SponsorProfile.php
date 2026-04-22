<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_name', 'website', 'logo', 'notes',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
