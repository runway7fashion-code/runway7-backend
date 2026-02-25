<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PressProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'media_outlet', 'position', 'website', 'instagram', 'bio',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
