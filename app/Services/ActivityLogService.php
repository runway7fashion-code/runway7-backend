<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogService
{
    public function log(
        ActivityAction $action,
        ?User $user,
        ?User $performedBy,
        string $description,
        array $metadata = []
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $user?->id,
            'performed_by' => $performedBy?->id,
            'action' => $action->value,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }
}
