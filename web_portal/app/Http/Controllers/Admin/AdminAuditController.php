<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        // Filtro por búsqueda de texto
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('entity_label', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Filtro por evento
        if ($event = $request->input('event')) {
            if ($event !== 'all') {
                $query->where('event', $event);
            }
        }

        // Filtro por módulo
        if ($module = $request->input('module')) {
            if ($module !== 'all') {
                $query->where('module', $module);
            }
        }

        // Filtro por usuario
        if ($userId = $request->input('user_id')) {
            if ($userId !== 'all') {
                $query->where('user_id', $userId);
            }
        }

        // Filtro por fecha
        $dateFilter = $request->input('date', 'all');
        if ($dateFilter === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($dateFilter === 'yesterday') {
            $query->whereDate('created_at', Carbon::yesterday());
        } elseif ($dateFilter === 'week') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($dateFilter === 'month') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        } elseif ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }
        }

        $logs = $query->paginate(25)->withQueryString();

        // KPIs
        $today = Carbon::today();
        $totalLogs = AuditLog::count();
        $loginsToday = AuditLog::whereIn('event', ['login'])->whereDate('created_at', $today)->count();
        $changesToday = AuditLog::whereIn('event', ['created', 'updated', 'deleted', 'toggled'])->whereDate('created_at', $today)->count();
        $failedLoginsToday = AuditLog::where('event', 'login_failed')->whereDate('created_at', $today)->count();

        // Lista de usuarios para el filtro
        $users = User::select('id', 'name', 'username', 'email')->orderBy('name')->get();

        return view('admin.audit.index', compact(
            'logs',
            'totalLogs',
            'loginsToday',
            'changesToday',
            'failedLoginsToday',
            'users'
        ));
    }

    public function show(AuditLog $audit): JsonResponse
    {
        $audit->load('user');

        // Formatear diferencias con nombres legibles
        $formattedDiff = [];
        $old = $audit->old_values ?? [];
        $new = $audit->new_values ?? [];
        $changedFields = $audit->changed_fields ?? array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($changedFields as $field) {
            $formattedDiff[] = [
                'field' => $field,
                'label' => AuditService::getFieldLabel($field),
                'old' => $old[$field] ?? null,
                'new' => $new[$field] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'audit' => [
                'id' => $audit->id,
                'event' => $audit->event,
                'event_label' => $audit->event_label,
                'event_badge' => $audit->event_badge_class,
                'event_icon' => $audit->event_icon,
                'module' => $audit->module,
                'module_label' => $audit->module_label,
                'module_icon' => $audit->module_icon,
                'user_name' => $audit->user_name,
                'user_email' => $audit->user_email,
                'user_role' => $audit->user_role,
                'entity_name' => $audit->entity_name,
                'entity_label' => $audit->entity_label,
                'description' => $audit->description,
                'ip_address' => $audit->ip_address,
                'user_agent' => $audit->user_agent,
                'created_at' => $audit->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $audit->created_at->diffForHumans(),
                'diff' => $formattedDiff,
            ]
        ]);
    }
}
