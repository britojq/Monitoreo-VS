<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryComponentHistory;
use App\Models\MaintenanceTicket;
use App\Models\MonitoredSite;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceTicketController extends Controller
{
    /**
     * Listado general de Órdenes de Servicio y Mantenimiento Técnico.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('type');
        $priorityFilter = $request->query('priority');
        $search = trim((string)$request->query('search', ''));

        $query = MaintenanceTicket::with(['asset.monitoredSite', 'technician'])
            ->orderBy('id', 'desc');

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        if (!empty($priorityFilter)) {
            $query->where('priority', $priorityFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('asset', function ($qa) use ($search) {
                      $qa->where('name', 'like', "%{$search}%")
                         ->orWhere('serial_number', 'like', "%{$search}%")
                         ->orWhere('asset_tag', 'like', "%{$search}%");
                  })
                  ->orWhereHas('technician', function ($qt) use ($search) {
                      $qt->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $tickets = $query->paginate(15)->withQueryString();

        // KPIs de Mantenimiento
        $stats = [
            'scheduled' => MaintenanceTicket::where('status', 'scheduled')->count(),
            'in_progress' => MaintenanceTicket::where('status', 'in_progress')->count(),
            'completed' => MaintenanceTicket::where('status', 'completed')->count(),
            'total_cost' => (float) MaintenanceTicket::where('status', 'completed')->sum('cost'),
        ];

        return view('admin.inventory.maintenance.index', compact('tickets', 'stats', 'statusFilter', 'typeFilter', 'priorityFilter', 'search'));
    }

    /**
     * Formulario para abrir una nueva orden de servicio o programar mantenimiento.
     */
    public function create(Request $request): View
    {
        $preselectedAssetId = $request->query('asset_id');
        $preselectedAsset = null;
        if ($preselectedAssetId) {
            $preselectedAsset = InventoryAsset::find($preselectedAssetId);
        }

        $assets = InventoryAsset::orderBy('name')->get();
        $technicians = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'role']);

        return view('admin.inventory.maintenance.create', compact('assets', 'preselectedAsset', 'technicians'));
    }

    /**
     * Guarda la nueva orden de servicio técnico.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_asset_id' => 'required|exists:inventory_assets,id',
            'type' => 'required|in:preventive,corrective',
            'priority' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'technician_user_id' => 'nullable|exists:users,id',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $year = date('Y');
        $prefix = "MNT-{$year}";
        $count = MaintenanceTicket::where('ticket_code', 'like', "{$prefix}-%")->count() + 1;
        $code = sprintf("%s-%04d", $prefix, $count);

        $asset = InventoryAsset::findOrFail($validated['inventory_asset_id']);

        DB::beginTransaction();
        try {
            $ticket = MaintenanceTicket::create([
                'ticket_code' => $code,
                'inventory_asset_id' => $asset->id,
                'type' => $validated['type'],
                'status' => 'scheduled',
                'priority' => $validated['priority'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : now(),
                'technician_user_id' => $validated['technician_user_id'] ?? Auth::id(),
                'cost' => $validated['cost'] ?? 0.00,
            ]);

            // Si es mantenimiento correctivo o de alta prioridad, marcar el activo en mantenimiento
            if ($validated['type'] === 'corrective' || in_array($validated['priority'], ['high', 'critical'])) {
                $asset->update(['status' => 'in_maintenance']);
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Sistema',
                'user_email' => Auth::user()->email ?? 'system@local',
                'user_role' => Auth::user()->role ?? 'admin',
                'event' => 'created',
                'module' => 'inventory',
                'auditable_type' => MaintenanceTicket::class,
                'auditable_id' => $ticket->id,
                'entity_name' => $ticket->ticket_code,
                'entity_label' => $ticket->title,
                'description' => "Ticket {$code} creado para activo [{$asset->asset_tag}] ({$validated['type']}): {$validated['title']}",
                'new_values' => $ticket->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();

            // Notificación al chat privado del Administrador
            if (class_exists(\App\Services\TelegramNotificationService::class)) {
                app(\App\Services\TelegramNotificationService::class)->notifyMaintenanceTicketOpened($ticket);
            }

            return redirect()->route('admin.inventory.maintenance.show', $ticket->id)
                ->with('success', "Ticket {$code} registrado exitosamente.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', "Error al registrar el ticket: {$e->getMessage()}");
        }
    }

    /**
     * Ficha de la Orden de Servicio con bitácora técnica y resolución.
     */
    public function show(MaintenanceTicket $ticket): View
    {
        $ticket->load(['asset.components', 'technician']);

        return view('admin.inventory.maintenance.show', compact('ticket'));
    }

    /**
     * Formulario de edición y actualización de estado técnico.
     */
    public function edit(MaintenanceTicket $ticket): View
    {
        $ticket->load(['asset', 'technician']);
        $technicians = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email', 'role']);

        return view('admin.inventory.maintenance.edit', compact('ticket', 'technicians'));
    }

    /**
     * Actualiza el ticket, registra resolución, repuestos y costos.
     */
    public function update(Request $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,in_progress,completed,canceled',
            'priority' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'technician_user_id' => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'warranty_applied' => 'nullable|boolean',
            'parts_replaced' => 'nullable|array',
            'parts_replaced.*.name' => 'required|string',
            'parts_replaced.*.serial' => 'nullable|string',
            'parts_replaced.*.cost' => 'nullable|numeric',
        ]);

        DB::beginTransaction();
        try {
            $oldStatus = $ticket->status;
            $newStatus = $validated['status'];

            $updateData = [
                'status' => $newStatus,
                'priority' => $validated['priority'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : $ticket->scheduled_at,
                'technician_user_id' => $validated['technician_user_id'] ?? $ticket->technician_user_id,
                'resolution_notes' => $validated['resolution_notes'] ?? null,
                'cost' => $validated['cost'] ?? $ticket->cost,
                'warranty_applied' => $request->boolean('warranty_applied'),
                'parts_replaced_json' => $validated['parts_replaced'] ?? $ticket->parts_replaced_json,
            ];

            if ($newStatus === 'in_progress' && !$ticket->started_at) {
                $updateData['started_at'] = now();
                $ticket->asset->update(['status' => 'in_maintenance']);
            }

            if ($newStatus === 'completed') {
                if (!$ticket->completed_at) {
                    $updateData['completed_at'] = now();
                }
                // Si el activo estaba en mantenimiento, restaurar a activo o asignado
                if ($ticket->asset->status === 'in_maintenance') {
                    $targetStatus = $ticket->asset->custodian_name ? 'assigned' : 'active';
                    $ticket->asset->update(['status' => $targetStatus]);
                }

                // Registrar reemplazo de partes en la auditoría forense de componentes si aplica
                if (!empty($validated['parts_replaced'])) {
                    foreach ($validated['parts_replaced'] as $part) {
                        InventoryComponentHistory::create([
                            'inventory_asset_id' => $ticket->inventory_asset_id,
                            'action' => 'replaced',
                            'component_type' => 'maintenance_part',
                            'old_value' => null,
                            'new_value' => $part,
                            'reason' => "Sustituido en Ticket {$ticket->ticket_code}: {$part['name']}",
                            'registered_by_user_id' => Auth::id(),
                            'recorded_at' => now(),
                        ]);
                    }
                }
            }

            if ($newStatus === 'canceled' && $ticket->asset->status === 'in_maintenance') {
                $targetStatus = $ticket->asset->custodian_name ? 'assigned' : 'active';
                $ticket->asset->update(['status' => $targetStatus]);
            }

            $ticket->update($updateData);

            AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Sistema',
                'user_email' => Auth::user()->email ?? 'system@local',
                'user_role' => Auth::user()->role ?? 'admin',
                'event' => 'updated',
                'module' => 'inventory',
                'auditable_type' => MaintenanceTicket::class,
                'auditable_id' => $ticket->id,
                'entity_name' => $ticket->ticket_code,
                'entity_label' => $ticket->title,
                'description' => "Ticket {$ticket->ticket_code} actualizado ({$oldStatus} -> {$newStatus})",
                'new_values' => $ticket->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('admin.inventory.maintenance.show', $ticket->id)
                ->with('success', "Ticket {$ticket->ticket_code} actualizado correctamente.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', "Error al actualizar ticket: {$e->getMessage()}");
        }
    }

    /**
     * Renderizado y descarga de la Orden Oficial de Servicio Técnico en PDF.
     */
    public function pdf(Request $request, MaintenanceTicket $ticket)
    {
        $ticket->load(['asset.components', 'technician']);

        $empresaPath = public_path('assets/img/inventory/empresa_logo.png');
        $empresaLogoBase64 = file_exists($empresaPath) ? base64_encode(file_get_contents($empresaPath)) : null;

        if ($request->has('download')) {
            $pdf = Pdf::loadView('admin.inventory.pdf.orden_servicio', [
                'ticket' => $ticket,
                'empresaLogoBase64' => $empresaLogoBase64,
                'isPdf' => true,
            ]);
            $pdf->setPaper('letter', 'portrait');
            return $pdf->download("ORDEN-SERVICIO-{$ticket->ticket_code}.pdf");
        }

        return view('admin.inventory.pdf.orden_servicio', [
            'ticket' => $ticket,
            'empresaLogoBase64' => $empresaLogoBase64,
            'isPdf' => false,
        ]);
    }

    /**
     * Elimina un ticket de mantenimiento.
     */
    public function destroy(MaintenanceTicket $ticket): RedirectResponse
    {
        $code = $ticket->ticket_code;
        $ticket->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name ?? 'Sistema',
            'user_email' => Auth::user()->email ?? 'system@local',
            'user_role' => Auth::user()->role ?? 'admin',
            'event' => 'deleted',
            'module' => 'inventory',
            'auditable_type' => MaintenanceTicket::class,
            'auditable_id' => $ticket->id,
            'entity_name' => $code,
            'description' => "Ticket de mantenimiento {$code} eliminado.",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.inventory.maintenance.index')
            ->with('success', "Ticket {$code} eliminado.");
    }
}
