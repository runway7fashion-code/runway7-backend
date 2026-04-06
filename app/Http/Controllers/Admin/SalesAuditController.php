<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesAuditController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesAuditLog::with('user:id,first_name,last_name,sales_type')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(30)->withQueryString();

        $salesUsers = User::where('role', 'sales')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'sales_type']);

        $actions = SalesAuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $entityTypes = SalesAuditLog::select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');

        return Inertia::render('Admin/Sales/AuditLogs', [
            'logs' => $logs,
            'salesUsers' => $salesUsers,
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'filters' => $request->only(['user_id', 'action', 'entity_type', 'from', 'to']),
        ]);
    }
}
