<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'default_looks',
        'default_assistants', 'order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeOrdered($query) { return $query->orderBy('order'); }
}
