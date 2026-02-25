<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_type_id', 'buyer_first_name', 'buyer_last_name',
        'buyer_email', 'buyer_phone', 'qr_code', 'status', 'source',
        'external_order_id', 'check_times', 'first_check_in_at',
    ];

    protected function casts(): array
    {
        return [
            'check_times' => 'array',
            'first_check_in_at' => 'datetime',
        ];
    }

    public function ticketType() { return $this->belongsTo(TicketType::class); }

    public function getBuyerFullNameAttribute(): string
    {
        return "{$this->buyer_first_name} {$this->buyer_last_name}";
    }
}
