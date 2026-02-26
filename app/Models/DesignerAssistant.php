<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignerAssistant extends Model
{
    use HasFactory;

    protected $fillable = [
        'designer_id', 'event_id', 'full_name', 'document_id',
        'phone', 'email', 'status', 'checked_in_at',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event() { return $this->belongsTo(Event::class); }
}
