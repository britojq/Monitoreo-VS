<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscoveredDevice;
use App\Models\DiscoveredDeviceHistory;
use App\Models\DiscoveryScan;
use App\Models\DiscoverySubnet;
use App\Models\MonitoredSite;
use App\Models\SnmpActivationLog;
use App\Models\SnmpDevice;
use App\Models\SnmpInterface;
use App\Models\SnmpOid;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDiscoveryController extends Controller
{
    /**
     * Muestra el panel unificado de Radar, Auto-Discovery y Telemetría SNMP.
     */
     public function index(Request $request): View
     {
         $activeTab = $request->query('tab', 'radar');
         $statusFilter = $request->query('status');
         $siteFilter = $request->query('site_id');
         $typeFilter = $request->query('device_type');
         $search = $request->query('search');

         // Métricas globales para los badges de las pestañas
         $radarPendingDevices = DiscoveredDevice::where('classification_status', 'pendiente')->count();
         $snmpTotalDevices = SnmpDevice::count();

         $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();

         if ($activeTab === 'snmp') {
             $query = SnmpDevice::with(['site', 'networkDevice', 'interfaces'])
                 ->orderByDesc('is_active')
                 ->orderBy('name');

             if (!empty($statusFilter)) {
                 $query->where('last_poll_status', $statusFilter);
             }

             if (!empty($typeFilter)) {
                 $query->where('device_type', $typeFilter);
             }

             if (!empty($siteFilter)) {
                 $query->where('site_id', $siteFilter);
             }

             if (!empty($search)) {
                 $query->where(function ($q) use ($search) {
                     $q->where('name', 'like', "%{$search}%")
                       ->orWhere('ip_address', 'like', "%{$search}%")
                       ->orWhere('vendor', 'like', "%{$search}%")
                       ->orWhere('model', 'like', "%{$search}%")
                       ->orWhere('sys_name', 'like', "%{$search}%");
                 });
             }

             // Métricas HUD SNMP
             $onlineDevices = SnmpDevice::where('last_poll_status', 'success')->count();
             $failingDevices = SnmpDevice::where('consecutive_failures', '>', 0)->count();
             $totalInterfaces = SnmpInterface::count();
             $monitoredInterfaces = SnmpInterface::where('is_monitored', true)->count();
             $oidsCount = SnmpOid::where('is_active', true)->count();
             $recentLogs = SnmpActivationLog::latest('executed_at')->take(5)->get();

             $devices = $query->paginate(15)->withQueryString();

             return view('admin.discovery.index', compact(
                 'activeTab',
                 'devices',
                 'sites',
                 'recentLogs',
                 'snmpTotalDevices',
                 'onlineDevices',
                 'failingDevices',
                 'totalInterfaces',
                 'monitoredInterfaces',
                 'oidsCount',
                 'radarPendingDevices',
                 'statusFilter',
                 'typeFilter',
                 'siteFilter',
                 'search'
             ));
         }

         // Pestaña por Defecto: Auto-Discovery & Anti-Rogue
         $query = DiscoveredDevice::with(['site', 'classifiedBy', 'linkedNetworkDevice', 'inventoryAsset'])
             ->orderByDesc('last_seen');

         if (!empty($statusFilter)) {
             $query->where('classification_status', $statusFilter);
         }

         if (!empty($siteFilter)) {
             $query->where('site_id', $siteFilter);
         }

         if (!empty($search)) {
             $query->where(function ($q) use ($search) {
                 $q->where('ip_address', 'like', "%{$search}%")
                   ->orWhere('mac_address', 'like', "%{$search}%")
                   ->orWhere('hostname', 'like', "%{$search}%")
                   ->orWhere('vendor', 'like', "%{$search}%")
                   ->orWhereHas('inventoryAsset', function ($aq) use ($search) {
                       $aq->where('name', 'like', "%{$search}%")
                          ->orWhere('brand', 'like', "%{$search}%")
                          ->orWhere('model', 'like', "%{$search}%");
                   });
             });
         }

         // Métricas HUD Auto-Discovery
         $totalDevices = DiscoveredDevice::count();
         $pendingDevices = $radarPendingDevices;
         $authorizedDevices = DiscoveredDevice::where('is_authorized', true)->count();
         $rogueDevices = DiscoveredDevice::where('classification_status', 'rogue')->count();

         $devices = $query->paginate(15)->withQueryString();
         $subnets = DiscoverySubnet::with('site')->orderBy('subnet')->get();
         $recentScans = DiscoveryScan::recent(5)->get();

         return view('admin.discovery.index', compact(
             'activeTab',
             'devices',
             'sites',
             'subnets',
             'recentScans',
             'totalDevices',
             'pendingDevices',
             'authorizedDevices',
             'rogueDevices',
             'radarPendingDevices',
             'snmpTotalDevices',
             'statusFilter',
             'siteFilter',
             'search'
         ));
     }

    /**
     * Autoriza y clasifica un dispositivo descubierto como legítimo.
     */
    public function authorizeDevice(Request $request, int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). La autorización de dispositivos debe realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $device = DiscoveredDevice::findOrFail($id);
        $deviceType = $request->input('device_type', 'workstation');
        $notes = $request->input('notes');

        $device->authorizeDevice($request->user()?->id, $deviceType, $notes);

        return back()->with('success', "Dispositivo {$device->ip_address} ({$device->mac_address}) aprobado exitosamente como {$deviceType}.");
    }

    /**
     * Marca un dispositivo como No Autorizado / Intruso (Rogue).
     */
    public function markRogue(Request $request, int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). El reporte de intrusos debe realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $device = DiscoveredDevice::findOrFail($id);
        $reason = $request->input('reason', 'Dispositivo no reconocido en auditoría');

        $device->markAsRogue($request->user()?->id, $reason);

        return back()->with('warning', "Dispositivo {$device->ip_address} ({$device->mac_address}) marcado como INTRUSO (Rogue).");
    }

    /**
     * Clasificación manual y edición de metadatos.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). La clasificación de dispositivos debe realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $device = DiscoveredDevice::findOrFail($id);

        $validated = $request->validate([
            'device_type' => ['required', 'string', 'in:router,switch,firewall,server,workstation,printer,ap,camera,ups,phone,unknown'],
            'classification_status' => ['required', 'string', 'in:pendiente,clasificado,ignorado,rogue,byod'],
            'is_authorized' => ['nullable', 'boolean'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'site_id' => ['nullable', 'exists:monitored_sites,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $prevStatus = $device->classification_status;
        $prevSiteId = $device->site_id;

        $newSiteId = array_key_exists('site_id', $validated) ? $validated['site_id'] : $device->site_id;

        $device->update([
            'device_type' => $validated['device_type'],
            'classification_status' => $validated['classification_status'],
            'is_authorized' => $request->has('is_authorized'),
            'hostname' => $validated['hostname'] ?? $device->hostname,
            'site_id' => $newSiteId,
            'notes' => $validated['notes'] ?? $device->notes,
            'classified_by' => $request->user()?->id,
            'classified_at' => now(),
        ]);

        if ($prevStatus !== $validated['classification_status']) {
            DiscoveredDeviceHistory::create([
                'discovered_device_id' => $device->id,
                'event_type' => 'classified',
                'previous_value' => $prevStatus,
                'new_value' => $validated['classification_status'],
                'occurred_at' => now(),
            ]);
        }

        if ((int)$prevSiteId !== (int)$newSiteId) {
            $prevSiteName = $prevSiteId ? (\App\Models\MonitoredSite::find($prevSiteId)?->name ?? "Sede #{$prevSiteId}") : 'Sin Asignar / Desconocida';
            $newSiteName = $newSiteId ? (\App\Models\MonitoredSite::find($newSiteId)?->name ?? "Sede #{$newSiteId}") : 'Sin Asignar / Desconocida';
            DiscoveredDeviceHistory::create([
                'discovered_device_id' => $device->id,
                'event_type' => 'classified',
                'previous_value' => "Sede previa: {$prevSiteName}",
                'new_value' => "Sede asignada: {$newSiteName}",
                'occurred_at' => now(),
            ]);
        }

        return back()->with('success', "Dispositivo {$device->ip_address} actualizado correctamente.");
    }

    /**
     * Dispara un escaneo de descubrimiento en segundo plano.
     */
    public function scanNow(Request $request): RedirectResponse
    {
        $subnet = $request->input('subnet');
        $pythonBin = '/scripts/telegram-admin-bot/venv/bin/python';
        $scriptPath = '/scripts/telegram-admin-bot/monitor/network_discovery.py';

        $cmd = [$pythonBin, $scriptPath, '--manual'];
        if (!empty($subnet)) {
            $cmd[] = '--subnet';
            $cmd[] = $subnet;
        } else {
            $cmd[] = '--all';
        }

        // Ejecutar en segundo plano desacoplado
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/tmp/monitor/network_discovery.log', 'a'],
            2 => ['file', '/tmp/monitor/network_discovery.log', 'a'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            proc_close($process);
        }

        $targetText = !empty($subnet) ? "subred {$subnet}" : "todas las subredes activas";
        return back()->with('success', "Escaneo de descubrimiento iniciado en segundo plano para {$targetText}. Los resultados se actualizarán en breve.");
    }

    /**
     * Registra una nueva subred para auto-discovery.
     */
    public function storeSubnet(Request $request): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). El registro de subredes debe realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $validated = $request->validate([
            'subnet' => ['required', 'string', 'unique:discovery_subnets,subnet', 'regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/'],
            'site_id' => ['nullable', 'exists:monitored_sites,id'],
            'scan_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'rate_limit_pps' => ['required', 'integer', 'min:10', 'max:500'],
        ], [
            'subnet.regex' => 'La subred debe tener formato CIDR válido (ej: 10.20.23.0/24).',
            'subnet.unique' => 'Esta subred ya está registrada.',
        ]);

        DiscoverySubnet::create([
            'subnet' => $validated['subnet'],
            'site_id' => $validated['site_id'] ?? null,
            'scan_interval_minutes' => $validated['scan_interval_minutes'],
            'rate_limit_pps' => $validated['rate_limit_pps'],
            'is_active' => true,
        ]);

        return back()->with('success', "Subred {$validated['subnet']} registrada para escaneo automático.");
    }

    /**
     * Devuelve el historial de eventos de un dispositivo en formato JSON para modal.
     */
    public function history(int $id): JsonResponse
    {
        $device = DiscoveredDevice::findOrFail($id);
        $history = $device->history()->orderByDesc('occurred_at')->limit(30)->get()->map(function ($h) {
            return [
                'id' => $h->id,
                'event_type' => $h->event_type,
                'event_type_label' => $h->event_type_label,
                'previous_value' => $h->previous_value,
                'new_value' => $h->new_value,
                'occurred_at' => $h->occurred_at ? $h->occurred_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : '-',
                'occurred_at_human' => $h->occurred_at ? $h->occurred_at->diffForHumans() : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'ip' => $device->ip_address,
                'mac' => $device->mac_address,
                'vendor' => $device->vendor,
                'hostname' => $device->hostname,
            ],
            'history' => $history,
        ]);
    }
}
