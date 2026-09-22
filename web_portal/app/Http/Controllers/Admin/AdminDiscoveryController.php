<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscoveredDevice;
use App\Models\DiscoveredDeviceHistory;
use App\Models\DiscoveryScan;
use App\Models\DiscoverySubnet;
use App\Models\MonitoredSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDiscoveryController extends Controller
{
    /**
     * Muestra el panel de Auto-Discovery y Detección Anti-Rogue.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $siteFilter = $request->query('site_id');
        $search = $request->query('search');

        $query = DiscoveredDevice::with(['site', 'classifiedBy', 'linkedNetworkDevice'])
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
                  ->orWhere('vendor', 'like', "%{$search}%");
            });
        }

        // Métricas HUD
        $totalDevices = DiscoveredDevice::count();
        $pendingDevices = DiscoveredDevice::where('classification_status', 'pendiente')->count();
        $authorizedDevices = DiscoveredDevice::where('is_authorized', true)->count();
        $rogueDevices = DiscoveredDevice::where('classification_status', 'rogue')->count();

        $devices = $query->paginate(15)->withQueryString();
        $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();
        $subnets = DiscoverySubnet::with('site')->orderBy('subnet')->get();
        $recentScans = DiscoveryScan::recent(5)->get();

        return view('admin.discovery.index', compact(
            'devices',
            'sites',
            'subnets',
            'recentScans',
            'totalDevices',
            'pendingDevices',
            'authorizedDevices',
            'rogueDevices',
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
        $device = DiscoveredDevice::findOrFail($id);

        $validated = $request->validate([
            'device_type' => ['required', 'string', 'in:router,switch,firewall,server,workstation,printer,ap,camera,ups,unknown'],
            'classification_status' => ['required', 'string', 'in:pendiente,clasificado,ignorado,rogue,byod'],
            'is_authorized' => ['nullable', 'boolean'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'site_id' => ['nullable', 'exists:monitored_sites,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $prevStatus = $device->classification_status;
        $device->update([
            'device_type' => $validated['device_type'],
            'classification_status' => $validated['classification_status'],
            'is_authorized' => $request->has('is_authorized'),
            'hostname' => $validated['hostname'] ?? $device->hostname,
            'site_id' => $validated['site_id'] ?? $device->site_id,
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
