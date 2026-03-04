<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ActivityLog::with(['user', 'performedBy']);

        if ($request->filled('role')) {
            $query->forRole($request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($sub) use ($search) {
                    $sub->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                })
                ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('action')) {
            $query->forAction($request->action);
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'action_color' => $log->action->color(),
                'description' => $log->description,
                'metadata' => $log->metadata,
                'user_id' => $log->user_id,
                'user_name' => $log->user ? $log->user->first_name . ' ' . $log->user->last_name : null,
                'user_role' => $log->user?->role,
                'performed_by_name' => $log->performedBy ? $log->performedBy->first_name . ' ' . $log->performedBy->last_name : 'Sistema',
                'created_at' => $log->created_at->setTimezone('America/Lima')->format('Y-m-d H:i'),
            ]);

        $actions = collect(ActivityAction::cases())->map(fn ($a) => [
            'value' => $a->value,
            'label' => $a->label(),
        ]);

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $request->only(['role', 'search', 'action', 'date_from', 'date_to']),
        ]);
    }
}
