<?php

namespace App\Http\Middleware;

use App\Models\SalesAuditLog;
use Closure;
use Illuminate\Http\Request;

class LogSalesAction
{
    public function handle(Request $request, Closure $next)
    {
        // For DELETEs, snapshot the entity BEFORE the controller deletes it
        $deleteSnapshot = null;
        if ($request->method() === 'DELETE') {
            $deleteSnapshot = $this->snapshotBeforeDelete($request);
        }

        $response = $next($request);

        // Only log mutating requests from sales users
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $response;
        }

        $user = $request->user();
        if (!$user || !in_array($user->role, ['sales', 'admin'])) {
            return $response;
        }

        // Only log successful responses — skip errors, validation failures, and exceptions
        $status = $response->getStatusCode();
        if ($status >= 400) {
            return $response;
        }
        // Skip redirects that carry error bags (validation/exception errors)
        if (session()->has('errors')) {
            return $response;
        }

        $path = $request->path();
        $method = $request->method();

        // Determine action, entity type and entity id from the route
        $action = match ($method) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => $method,
        };

        // Parse entity from URL
        $entityType = $this->resolveEntityType($path);
        $entityId = $this->resolveEntityId($path);
        $description = $this->buildDescription($action, $entityType, $path, $request);

        // Refine action from path context
        if (str_contains($path, '/status')) $action = 'status_changed';
        elseif (str_contains($path, '/assign')) $action = 'assigned';
        elseif (str_contains($path, '/event-status')) $action = 'event_status_changed';
        elseif (str_contains($path, '/activity')) $action = 'activity_added';
        elseif (str_contains($path, '/complete')) $action = 'activity_completed';
        elseif (str_contains($path, '/cancel')) $action = 'activity_cancelled';
        elseif (str_contains($path, '/not-completed')) $action = 'activity_not_completed';
        elseif (str_contains($path, '/tags')) $action = 'tags_synced';
        elseif (str_contains($path, '/add-event')) $action = 'event_added';
        elseif (str_contains($path, '/remove-event')) $action = 'event_removed';
        elseif (str_contains($path, '/documents')) $action = 'document_uploaded';
        elseif (str_contains($path, '/toggle-availability')) $action = 'availability_toggled';
        elseif (str_contains($path, '/bot/')) return $response; // skip bot messages

        try {
            SalesAuditLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'changes' => $deleteSnapshot ?? $this->safeChanges($request),
            ]);
        } catch (\Throwable $e) {
            // Never break the request for logging failures
            \Log::warning('Sales audit log failed: ' . $e->getMessage());
        }

        return $response;
    }

    private function resolveEntityType(string $path): string
    {
        if (str_contains($path, 'sales/leads')) return 'lead';
        if (str_contains($path, 'sales/designers') || str_contains($path, 'sales/documents')) return 'registration';
        if (str_contains($path, 'sales/tags')) return 'tag';
        if (str_contains($path, 'sales/activities') || str_contains($path, '/activity')) return 'activity';
        if (str_contains($path, 'sales/packages')) return 'package';
        if (str_contains($path, 'sales/calendar')) return 'calendar';
        if (str_contains($path, 'toggle-availability')) return 'availability';
        return 'sales';
    }

    private function resolveEntityId(string $path): ?int
    {
        // Match numeric segments in the path
        if (preg_match('/\/(\d+)/', $path, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function buildDescription(string $action, string $entityType, string $path, Request $request): string
    {
        $user = $request->user();
        $name = $user->first_name . ' ' . $user->last_name;

        return match ($entityType) {
            'lead' => "{$name} {$action} a lead",
            'registration' => "{$name} {$action} a designer registration",
            'tag' => "{$name} {$action} a tag",
            'activity' => "{$name} {$action} an activity",
            'package' => "{$name} {$action} a package",
            'availability' => "{$name} toggled availability",
            default => "{$name} performed {$action} on {$entityType}",
        };
    }

    private function safeChanges(Request $request): ?array
    {
        $data = $request->except(['_token', '_method', 'password', 'password_confirmation']);
        // Remove file data
        foreach ($data as $key => $value) {
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $data[$key] = '[file]';
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if ($v instanceof \Illuminate\Http\UploadedFile) {
                        $data[$key][$k] = '[file]';
                    }
                }
            }
        }
        return !empty($data) ? $data : null;
    }

    private function snapshotBeforeDelete(Request $request): ?array
    {
        $path = $request->path();
        $entityType = $this->resolveEntityType($path);
        $entityId = $this->resolveEntityId($path);

        if (!$entityId) return null;

        $modelMap = [
            'lead'         => \App\Models\DesignerLead::class,
            'tag'          => \App\Models\LeadTag::class,
            'package'      => \App\Models\DesignerPackage::class,
            'activity'     => \App\Models\LeadActivity::class,
            'registration' => \App\Models\SalesRegistration::class,
        ];

        $modelClass = $modelMap[$entityType] ?? null;
        if (!$modelClass) return null;

        try {
            $record = $modelClass::find($entityId);
            if (!$record) return null;

            // Pick only relevant human-readable fields
            $fieldMap = [
                'lead'         => ['first_name', 'last_name', 'email', 'phone', 'company_name', 'country', 'status', 'source'],
                'tag'          => ['name', 'color'],
                'package'      => ['name', 'description', 'price', 'default_looks', 'default_assistants'],
                'activity'     => ['title', 'type', 'description', 'status', 'scheduled_at'],
                'registration' => ['status', 'agreed_price', 'downpayment', 'event_id', 'package_id'],
            ];

            $fields = $fieldMap[$entityType] ?? [];
            $snapshot = [];
            foreach ($fields as $field) {
                $val = $record->$field;
                if ($val !== null && $val !== '') {
                    $snapshot[$field] = $val instanceof \Carbon\Carbon ? $val->format('Y-m-d H:i') : $val;
                }
            }

            // Add related names for context
            if ($entityType === 'registration') {
                if ($record->designer) $snapshot['designer'] = $record->designer->first_name . ' ' . $record->designer->last_name;
                if ($record->event) $snapshot['event'] = $record->event->name;
                if ($record->package) $snapshot['package'] = $record->package->name;
            }

            return !empty($snapshot) ? $snapshot : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
