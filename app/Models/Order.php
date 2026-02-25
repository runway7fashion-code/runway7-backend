<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'user_id', 'subtotal', 'discount', 'tax', 'total',
        'status', 'payment_status', 'stripe_payment_intent_id', 'stripe_charge_id',
        'promo_code_id', 'shipping_name', 'shipping_address', 'shipping_city',
        'shipping_state', 'shipping_zip', 'shipping_country', 'tracking_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function promoCode() { return $this->belongsTo(PromoCode::class); }
    public function promoCodeUsage() { return $this->hasOne(PromoCodeUsage::class); }
}
