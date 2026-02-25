<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ShowDesigner extends Pivot
{
    protected $table = 'show_designer';

    public function show() { return $this->belongsTo(Show::class); }
    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
}
