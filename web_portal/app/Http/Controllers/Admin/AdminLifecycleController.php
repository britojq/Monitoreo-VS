<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HardwareLifecycle;
use App\Models\MonitoredNetworkDevice;
use App\Models\SnmpDevice;
use App\Services\ClusterConfigService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminLifecycleController extends Controller
{
    /**
     * Muestra la matriz de ciclo de vida de hardware, garantías y fin de soporte.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('warranty_status');
        $diskFilter = $request->query('disk_status');
        $search = $request->query('search');

        $query = HardwareLifecycle::orderBy('warranty_end_date', 'asc');

        if (!empty($diskFilter)) {
            $query->where('disk_health_status', $diskFilter);
        }

        $items = $query->paginate(20)->withQueryString();

        // Enriquecer con nombres de dispositivos
        $snmpDevices = SnmpDevice::pluck('name', 'id')->toArray();
        $netDevices = MonitoredNetworkDevice::pluck('name', 'id')->toArray();

        foreach ($items as $item) {
            if ($item->entity_type === 'snmp_device') {
                $item->device_name = $snmpDevices[$item->entity_id] ?? ("Dispositivo SNMP #" . $item->entity_id);
            } elseif ($item->entity_type === 'network_device') {
                $item->device_name = $netDevices[$item->entity_id] ?? ("Equipo de Red #" . $item->entity_id);
            } else {
                $item->device_name = "Activo #" . $item->entity_id;
            }
        }

        // Resumen KPIs
        $all = HardwareLifecycle::all();
        $now = Carbon::now();
        $totalTracked = $all->count();
        $warrantyValid = 0;
        $warrantyExpiringSoon = 0;
        $warrantyExpired = 0;
        $eolReached = 0;
        $diskAlerts = 0;

        foreach ($all as $rec) {
            if ($rec->warranty_end_date) {
                $days = $now->diffInDays($rec->warranty_end_date, false);
                if ($days < 0) {
                    $warrantyExpired++;
                } elseif ($days <= 60) {
                    $warrantyExpiringSoon++;
                } else {
                    $warrantyValid++;
                }
            }

            if ($rec->eol_date && $now->diffInDays($rec->eol_date, false) < 0) {
                $eolReached++;
            }

            if (in_array($rec->disk_health_status, ['warning', 'failing'])) {
                $diskAlerts++;
            }
        }

        $availableSnmpDevices = SnmpDevice::orderBy('name')->get();
        $availableNetDevices = MonitoredNetworkDevice::orderBy('name')->get();

        return view('admin.lifecycle.index', compact(
            'items',
            'totalTracked',
            'warrantyValid',
            'warrantyExpiringSoon',
            'warrantyExpired',
            'eolReached',
            'diskAlerts',
            'availableSnmpDevices',
            'availableNetDevices',
            'diskFilter',
            'search'
        ));
    }

    /**
     * Registra o actualiza la información de ciclo de vida de un equipo.
     */
    public function store(Request $request): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de inventario y garantías deben realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $validated = $request->validate([
            'id' => 'nullable|integer|exists:hardware_lifecycle,id',
            'entity_type' => 'required|in:snmp_device,network_device,server',
            'entity_id' => 'required|integer',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date',
            'eol_date' => 'nullable|date',
            'eos_date' => 'nullable|date',
            'battery_last_replaced' => 'nullable|date',
            'disk_health_status' => 'required|in:ok,warning,failing,unknown',
            'firmware_version' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (!empty($validated['id'])) {
            $item = HardwareLifecycle::findOrFail($validated['id']);
            $item->update($validated);
            $actionDetails = "Ficha de ciclo de vida editada para {$item->entity_type} #{$item->entity_id} (S/N: {$item->serial_number})";
            $statusMsg = "Ficha de ciclo de vida actualizada exitosamente.";
        } else {
            $item = HardwareLifecycle::updateOrCreate(
                [
                    'entity_type' => $validated['entity_type'],
                    'entity_id' => $validated['entity_id'],
                ],
                $validated
            );
            $actionDetails = "Ciclo de vida guardado para {$item->entity_type} #{$item->entity_id} (S/N: {$item->serial_number})";
            $statusMsg = "Ficha de ciclo de vida registrada exitosamente.";
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => !empty($validated['id']) ? 'updated' : 'created',
            'module' => 'lifecycle',
            'auditable_type' => HardwareLifecycle::class,
            'auditable_id' => $item->id,
            'entity_name' => "Activo {$item->entity_type} #{$item->entity_id}",
            'entity_label' => "S/N: " . ($item->serial_number ?: 'N/A'),
            'description' => $actionDetails,
            'new_values' => $item->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.lifecycle.index')
            ->with('status', $statusMsg);
    }

    /**
     * Elimina el registro de ciclo de vida de un activo.
     */
    public function destroy(int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de inventario y garantías deben realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $item = HardwareLifecycle::findOrFail($id);
        $item->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'deleted',
            'module' => 'lifecycle',
            'auditable_type' => HardwareLifecycle::class,
            'auditable_id' => $id,
            'entity_name' => "Activo #{$id}",
            'entity_label' => "ID #{$id}",
            'description' => "Registro de ciclo de vida #{$id} eliminado.",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.lifecycle.index')
            ->with('status', "Registro de ciclo de vida eliminado.");
    }
}
