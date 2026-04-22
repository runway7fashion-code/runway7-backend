<?php

namespace App\Models\Sponsorship;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'sponsorship_categories';

    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
