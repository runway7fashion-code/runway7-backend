<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'designer_id', 'event_id', 'show_id', 'name',
        'description', 'drive_link', 'status', 'type', 'order',
    ];

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function show() { return $this->belongsTo(Show::class); }
}
