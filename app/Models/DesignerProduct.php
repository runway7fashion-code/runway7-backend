<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DesignerProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'designer_id', 'event_id', 'name', 'description', 'price', 'compare_price',
        'images', 'sizes_available', 'colors_available', 'category',
        'stock', 'status', 'featured',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'images' => 'array',
            'sizes_available' => 'array',
            'colors_available' => 'array',
            'featured' => 'boolean',
        ];
    }

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id')
            ->where('product_type', 'designer_product');
    }
}
