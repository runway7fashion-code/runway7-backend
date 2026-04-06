<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerAssistant extends Model
{
    use HasFactory;

    protected $fillable = [
        'designer_id', 'user_id', 'event_id', 'first_name', 'last_name',
        'document_id', 'phone', 'email', 'status', 'checked_in_at',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function designer()   { return $this->belongsTo(User::class, 'designer_id'); }
    public function userAccount() { return $this->belongsTo(User::class, 'user_id'); }
    public function event()      { return $this->belongsTo(Event::class); }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
