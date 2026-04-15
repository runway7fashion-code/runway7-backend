<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialBioContent extends Model
{
    protected $table = 'material_bio_content';

    protected $fillable = [
        'material_id', 'biography', 'collection_description',
        'additional_notes', 'contact_info',
    ];

    public function material() { return $this->belongsTo(DesignerMaterial::class, 'material_id'); }
}
