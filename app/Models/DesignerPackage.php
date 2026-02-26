<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'default_looks',
        'default_assistants', 'features', 'order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'features'   => 'array',
            'is_active'  => 'boolean',
        ];
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeOrdered($query) { return $query->orderBy('order'); }
}
