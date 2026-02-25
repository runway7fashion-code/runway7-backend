<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'brand_name', 'collection_name', 'website',
        'instagram', 'bio', 'country',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
