<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignerModelFavorite extends Model
{
    protected $fillable = ['designer_id', 'model_id', 'event_id'];

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function model()    { return $this->belongsTo(User::class, 'model_id'); }
    public function event()    { return $this->belongsTo(Event::class); }
}
