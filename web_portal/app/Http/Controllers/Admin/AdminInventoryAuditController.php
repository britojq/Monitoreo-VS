<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\AssetTimelineInterface;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador para la Consola Centralizada de Auditoría Forense y Trazabilidad ITAM.
 */
class AdminInventoryAuditController extends Controller
{
    public function __construct(
        protected AssetTimelineInterface $timelineService
    ) {}

    /**
     * Vista principal de la Consola de Auditoría ITAM.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'event', 'asset_id', 'date_from', 'date_to']);
        $logs = $this->timelineService->getGlobalItamAuditFeed($filters, 25);
        $kpis = $this->timelineService->getAuditKpis();
        $assets = InventoryAsset::select('id', 'name', 'asset_tag')->orderBy('name')->get();

        return view('admin.inventory.audit.index', compact('logs', 'kpis', 'assets', 'filters'));
    }

    /**
     * Endpoint API JSON que retorna la línea de tiempo consolidada de un activo.
     */
    public function timeline(int $id): JsonResponse
    {
        $asset = InventoryAsset::findOrFail($id);
        $timeline = $this->timelineService->getAssetTimeline($asset);

        return response()->json([
            'success' => true,
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'asset_tag' => $asset->asset_tag,
                'serial_number' => $asset->serial_number,
                'status' => $asset->status,
                'status_label' => $asset->status_label,
            ],
            'timeline' => $timeline,
            'total_events' => $timeline->count(),
        ]);
    }

    /**
     * Exportación forense de registros de auditoría ITAM (CSV / JSON).
     */
    public function export(Request $request): StreamedResponse
    {
        $format = strtolower($request->input('format', 'csv'));
        $filters = $request->only(['search', 'event', 'asset_id', 'date_from', 'date_to']);
        $timestamp = Carbon::now()->format('Y-m-d_His');

        AuditService::log(
            event: 'exported',
            module: 'inventory',
            description: "Exportación de registros de auditoría y trazabilidad ITAM en formato " . strtoupper($format),
            auditable: null,
            entityName: 'Auditoría ITAM',
            entityLabel: "Exportación " . strtoupper($format)
        );

        if ($format === 'json') {
            $filename = "trazabilidad_itam_{$timestamp}.json";

            return response()->streamDownload(function () use ($filters) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "[\n");
                $first = true;

                $query = AuditLog::where(function ($q) {
                    $q->whereIn('module', ['inventory', 'devices', 'lifecycle'])
                      ->orWhereIn('auditable_type', [
                          'App\\Models\\InventoryAsset',
                          'App\\Models\\InventoryAssignment',
                          'App\\Models\\MaintenanceTicket',
                          'App\\Models\\InventoryPeripheral',
                          'App\\Models\\InventoryComponentHistory',
                      ]);
                });

                if (!empty($filters['search'])) {
                    $s = trim($filters['search']);
                    $query->where(function ($q) use ($s) {
                        $q->where('description', 'like', "%{$s}%")
                          ->orWhere('entity_name', 'like', "%{$s}%")
                          ->orWhere('entity_label', 'like', "%{$s}%");
                    });
                }

                if (!empty($filters['event'])) {
                    $query->where('event', $filters['event']);
                }

                $query->orderBy('created_at', 'desc')->chunk(500, function ($logs) use ($handle, &$first) {
                    foreach ($logs as $log) {
                        $item = [
                            'id' => $log->id,
                            'fecha_hora' => $log->created_at->format('Y-m-d H:i:s'),
                            'usuario' => $log->user_name ?: 'Sistema',
                            'email' => $log->user_email,
                            'rol' => $log->user_role,
                            'evento' => $log->event,
                            'modulo' => $log->module,
                            'entidad_tipo' => $log->auditable_type,
                            'entidad_id' => $log->auditable_id,
                            'entidad_nombre' => $log->entity_name,
                            'entidad_etiqueta' => $log->entity_label,
                            'descripcion' => $log->description,
                            'valores_anteriores' => $log->old_values,
                            'valores_nuevos' => $log->new_values,
                            'ip_origen' => $log->ip_address,
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
            }, $filename, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        // CSV por defecto
        $filename = "trazabilidad_itam_{$timestamp}.csv";

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Fecha / Hora',
                'Usuario Responsable',
                'Email',
                'Rol',
                'Evento',
                'Módulo',
                'Entidad',
                'Etiqueta / Serial',
                'Descripción del Cambio',
                'IP Origen',
            ]);

            $query = AuditLog::where(function ($q) {
                $q->whereIn('module', ['inventory', 'devices', 'lifecycle'])
                  ->orWhereIn('auditable_type', [
                      'App\\Models\\InventoryAsset',
                      'App\\Models\\InventoryAssignment',
                      'App\\Models\\MaintenanceTicket',
                      'App\\Models\\InventoryPeripheral',
                      'App\\Models\\InventoryComponentHistory',
                  ]);
            });

            if (!empty($filters['search'])) {
                $s = trim($filters['search']);
                $query->where(function ($q) use ($s) {
                    $q->where('description', 'like', "%{$s}%")
                      ->orWhere('entity_name', 'like', "%{$s}%")
                      ->orWhere('entity_label', 'like', "%{$s}%");
                });
            }

            if (!empty($filters['event'])) {
                $query->where('event', $filters['event']);
            }

            $query->orderBy('created_at', 'desc')->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user_name ?: 'Sistema',
                        $log->user_email ?: 'N/A',
                        $log->user_role ?: 'N/A',
                        $log->event_label,
                        $log->module,
                        $log->entity_name,
                        $log->entity_label,
                        $log->description,
                        $log->ip_address ?: '127.0.0.1',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
