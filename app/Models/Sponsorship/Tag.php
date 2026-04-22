<?php

namespace App\Models\Sponsorship;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'sponsorship_tags';

    protected $fillable = ['name', 'color'];
}
