<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FittingAssignment extends Model
{
    protected $fillable = [
        'fitting_slot_id', 'designer_id', 'notes',
    ];

    public function fittingSlot() { return $this->belongsTo(FittingSlot::class); }
    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
}
