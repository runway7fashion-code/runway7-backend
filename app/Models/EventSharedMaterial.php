<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSharedMaterial extends Model
{
    protected $fillable = [
        'event_id', 'material_name', 'drive_file_id', 'drive_url',
        'file_name', 'mime_type', 'file_size', 'uploaded_by',
    ];

    public function event()    { return $this->belongsTo(Event::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
