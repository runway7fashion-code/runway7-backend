<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialMoodboardItem extends Model
{
    protected $fillable = [
        'material_id', 'uploaded_by', 'drive_file_id', 'drive_url',
        'image_name', 'response_text', 'responded_at', 'order',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function material() { return $this->belongsTo(DesignerMaterial::class, 'material_id'); }
    public function uploader()  { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function files()     { return $this->hasMany(MoodboardItemFile::class, 'moodboard_item_id'); }
}
