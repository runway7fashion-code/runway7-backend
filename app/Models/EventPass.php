<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventPass extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'user_id', 'issued_by',
        'qr_code', 'pass_type', 'holder_name', 'holder_email',
        'valid_days', 'status', 'checked_in_at', 'check_in_history',
        'notes', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_days'       => 'array',
            'check_in_history' => 'array',
            'checked_in_at'    => 'datetime',
            'issued_at'        => 'datetime',
        ];
    }

    // --- Relationships ---
    public function event()    { return $this->belongsTo(Event::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function issuedBy() { return $this->belongsTo(User::class, 'issued_by'); }

    // --- Scopes ---
    public function scopeForEvent($query, int $eventId)  { return $query->where('event_id', $eventId); }
    public function scopeActive($query)                   { return $query->where('status', 'active'); }
    public function scopeByType($query, string $type)     { return $query->where('pass_type', $type); }

    // --- QR Generation ---
    public static function generateQrCode(): string
    {
        do {
            $code = 'PASS-' . strtoupper(Str::random(6));
        } while (self::where('qr_code', $code)->exists());

        return $code;
    }

    // --- Helpers ---
    public static function passTypes(): array
    {
        return [
            'model'          => 'Modelo',
            'designer'       => 'Diseñador',
            'assistant'      => 'Asistente Diseñador',
            'staff'          => 'Staff',
            'media'          => 'Prensa/Media',
            'volunteer'      => 'Voluntario',
            'vip'            => 'VIP',
            'press'          => 'Prensa',
            'sponsor'        => 'Patrocinador',
            'complementary'  => 'Complementario',
            'guest'          => 'Invitado',
        ];
    }

    public function passTypeLabel(): string
    {
        return self::passTypes()[$this->pass_type] ?? $this->pass_type;
    }

    public function isActive(): bool    { return $this->status === 'active'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isUsed(): bool      { return $this->status === 'used'; }
}
