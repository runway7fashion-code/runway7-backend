<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoodboardItemFile extends Model
{
    protected $fillable = [
        'moodboard_item_id', 'drive_file_id', 'drive_url',
        'file_name', 'mime_type', 'file_size', 'uploaded_by',
    ];

    public function moodboardItem() { return $this->belongsTo(MaterialMoodboardItem::class, 'moodboard_item_id'); }
    public function uploader()      { return $this->belongsTo(User::class, 'uploaded_by'); }
}
