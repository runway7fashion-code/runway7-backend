<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialFile extends Model
{
    protected $fillable = [
        'material_id', 'uploaded_by', 'drive_file_id', 'drive_url',
        'file_name', 'file_type', 'mime_type', 'file_size', 'note', 'is_final',
    ];

    protected function casts(): array
    {
        return ['is_final' => 'boolean'];
    }

    public function material() { return $this->belongsTo(DesignerMaterial::class, 'material_id'); }
    public function uploader()  { return $this->belongsTo(User::class, 'uploaded_by'); }
}
