<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesAuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'description', 'changes'];

    protected $casts = ['changes' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $entityType, ?int $entityId, string $description, ?array $changes = null): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'changes' => $changes,
        ]);
    }
}
