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
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->buildFilteredQuery($request);
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

    public function export(Request $request): StreamedResponse
    {
        $format = strtolower($request->input('format', 'csv'));
        $query = $this->buildFilteredQuery($request);
        $totalCount = (clone $query)->count();
        $timestamp = Carbon::now()->format('Y-m-d_His');

        // Registro de auditoría de la exportación
        AuditService::log(
            event: 'exported',
            module: 'auth',
            description: "Exportación de registros de auditoría en formato " . strtoupper($format) . " ({$totalCount} registros)",
            auditable: null,
            entityName: 'Auditoría',
            entityLabel: "Exportación " . strtoupper($format)
        );

        if ($format === 'json') {
            $filename = "auditoria_monitoreo_{$timestamp}.json";

            return response()->streamDownload(function () use ($query) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "[\n");
                $first = true;

                $query->chunk(500, function ($logs) use ($handle, &$first) {
                    foreach ($logs as $log) {
                        $item = [
                            'id' => $log->id,
                            'fecha_hora' => $log->created_at->format('Y-m-d H:i:s'),
                            'usuario' => $log->user_name ?: 'Anónimo / Sistema',
                            'email' => $log->user_email,
                            'rol' => $log->user_role,
                            'evento' => $log->event,
                            'evento_label' => $log->event_label,
                            'modulo' => $log->module,
                            'modulo_label' => $log->module_label,
                            'entidad_tipo' => $log->auditable_type,
                            'entidad_id' => $log->auditable_id,
                            'entidad_nombre' => $log->entity_name,
                            'entidad_valor' => $log->entity_label,
                            'descripcion' => $log->description,
                            'campos_modificados' => $log->changed_fields,
                            'valores_anteriores' => $log->old_values,
                            'valores_nuevos' => $log->new_values,
                            'ip_origen' => $log->ip_address,
                            'user_agent' => $log->user_agent,
                        ];

                        $json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        if (!$first) {
                            fwrite($handle, ",\n");
                        }
                        fwrite($handle, $json);
                        $first = false;
                    }
                });

                fwrite($handle, "\n]\n");
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // CSV (Compatible con Excel mediante BOM UTF-8)
        $filename = "auditoria_monitoreo_{$timestamp}.csv";

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Escribir BOM UTF-8 para visualización directa en Microsoft Excel / LibreOffice
            fwrite($handle, "\xEF\xBB\xBF");

            // Encabezados
            fputcsv($handle, [
                'ID',
                'Fecha y Hora',
                'Usuario / Actor',
                'Correo Electrónico',
                'Rol',
                'Evento',
                'Módulo',
                'Entidad / Objeto',
                'Identificador Entidad',
                'Descripción del Evento',
                'Campos Modificados',
                'Dirección IP',
                'User Agent / Navegador'
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    $changedFieldsStr = '';
                    if (!empty($log->changed_fields) && is_array($log->changed_fields)) {
                        $labels = array_map(fn($f) => AuditService::getFieldLabel($f), $log->changed_fields);
                        $changedFieldsStr = implode(', ', $labels);
                    }

                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user_name ?: 'Anónimo / Sistema',
                        $log->user_email ?: 'N/A',
                        $log->user_role ?: 'N/A',
                        $log->event_label,
                        $log->module_label,
                        $log->entity_name ? ($log->entity_name . ': ' . $log->entity_label) : ($log->entity_label ?: 'N/A'),
                        $log->auditable_id ?: 'N/A',
                        $log->description,
                        $changedFieldsStr ?: 'N/A',
                        $log->ip_address ?: '127.0.0.1',
                        $log->user_agent ?: 'N/A'
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function buildFilteredQuery(Request $request)
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

        return $query;
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
