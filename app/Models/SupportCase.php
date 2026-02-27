<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCase extends Model
{
    protected $fillable = [
        'case_number', 'designer_id', 'event_id', 'channel', 'case_type',
        'contact_email', 'claim_date', 'status', 'created_by',
    ];

    protected $casts = [
        'claim_date' => 'date',
    ];

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function messages() { return $this->hasMany(SupportCaseMessage::class)->orderBy('created_at'); }
    public function latestMessage() { return $this->hasOne(SupportCaseMessage::class)->latestOfMany(); }

    public function getChannelLabelAttribute(): string
    {
        return match($this->channel) {
            'email' => 'Email',
            'sms' => 'SMS',
            'phone' => 'Llamada',
            'whatsapp' => 'WhatsApp',
            'dm' => 'DM',
            default => $this->channel,
        };
    }

    public function getCaseTypeLabelAttribute(): string
    {
        return match($this->case_type) {
            'claim' => 'Reclamo',
            'complaint' => 'Queja',
            'payment' => 'Pagos',
            'refund' => 'Devolución',
            default => $this->case_type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'open' => 'Abierto',
            'in_progress' => 'En Proceso',
            'resolved' => 'Resuelto',
            'closed' => 'Cerrado',
            default => $this->status,
        };
    }

    public static function generateCaseNumber(): string
    {
        $lastCase = static::orderByDesc('id')->first();
        $nextNumber = $lastCase ? intval(substr($lastCase->case_number, 5)) + 1 : 1;
        return 'CASO-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
