<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerDisplay extends Model
{
    use HasFactory;

    protected $fillable = [
        'designer_id', 'event_id', 'show_id',
        'background_video_url', 'music_audio_url', 'status', 'notes',
    ];

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function show() { return $this->belongsTo(Show::class); }
}
