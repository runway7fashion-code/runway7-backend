<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_type', 'product_id', 'product_name',
        'quantity', 'unit_price', 'total_price', 'size', 'color',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function order() { return $this->belongsTo(Order::class); }

    public function product()
    {
        if ($this->product_type === 'designer_product') {
            return $this->belongsTo(DesignerProduct::class, 'product_id');
        }
        return $this->belongsTo(MerchProduct::class, 'product_id');
    }
}
