<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeOrdered($query) { return $query->orderBy('order'); }

    public function designerProfiles() { return $this->hasMany(DesignerProfile::class, 'category_id'); }
}
