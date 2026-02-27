<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignerContactEmail extends Model
{
    protected $fillable = ['designer_id', 'email', 'label'];

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
}
