<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\ClusterConfigInterface;
use App\Http\Controllers\Controller;
use App\Jobs\WakeOnLanJob;
use App\Models\AuditLog;
use App\Models\MonitoredSite;
use App\Models\WolDevice;
use App\Services\System\SonarPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminWolController extends Controller
{
    public function __construct(
        protected ClusterConfigInterface $cluster,
    ) {}
    /**
     * Muestra el panel de administración de Wake-on-LAN.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');

        $query = WolDevice::with('site')->orderBy('name', 'asc');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mac_address', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $devices = $query->paginate(20)->withQueryString();
        $sites = MonitoredSite::where('is_active', true)->orderBy('name')->get();

        $totalDevices = WolDevice::count();
        $recentlyWoken = WolDevice::whereNotNull('last_woken_at')
            ->where('last_woken_at', '>=', now()->subHours(24))
            ->count();

        return view('admin.wol.index', compact(
            'devices',
            'sites',
            'totalDevices',
            'recentlyWoken',
            'search'
        ));
    }

    /**
     * Envía un Magic Packet de Wake-on-LAN para encender el dispositivo (Asíncrono vía Queue).
     */
    public function wake(int $id): JsonResponse|RedirectResponse
    {
        $device = WolDevice::findOrFail($id);

        WakeOnLanJob::dispatch(
            $device->id,
            Auth::id() ? (int) Auth::id() : null,
            request()->ip(),
            request()->userAgent()
        );

        $msg = "⚡ Solicitud Wake-on-LAN transmitida al despachador en segundo plano para '{$device->name}' ({$device->mac_address}).";

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'status' => 'queued',
                'message' => $msg,
            ], 202);
        }

        return redirect()->route('admin.wol.index')->with('status', $msg);
    }

    /**
     * Registra un nuevo equipo para encendido remoto.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($this->cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de equipos WoL deben realizarse en el servidor MASTER ({$this->cluster->getMasterApiUrl()}).");
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mac_address' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$|^[0-9A-Fa-f]{12}$/'],
            'ip_address' => 'nullable|ip',
            'broadcast_address' => 'nullable|ip',
            'site_id' => 'nullable|exists:monitored_sites,id',
        ]);

        // Normalizar MAC
        $cleanMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $validated['mac_address']));
        $formattedMac = implode(':', str_split($cleanMac, 2));

        $device = WolDevice::updateOrCreate(
            ['mac_address' => $formattedMac],
            [
                'name' => $validated['name'],
                'ip_address' => $validated['ip_address'] ?? null,
                'broadcast_address' => $validated['broadcast_address'] ?: '255.255.255.255',
                'site_id' => $validated['site_id'] ?? null,
                'is_enabled' => true,
            ]
        );

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'created',
            'module' => 'wol',
            'auditable_type' => WolDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => $device->mac_address,
            'description' => "Dispositivo WoL registrado: {$device->name} ({$device->mac_address})",
            'new_values' => $device->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.wol.index')
            ->with('status', "Equipo '{$device->name}' registrado exitosamente para Wake-on-LAN.");
    }

    /**
     * Elimina un equipo del registro de WoL.
     */
    public function destroy(int $id): RedirectResponse
    {
        if ($this->cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de equipos WoL deben realizarse en el servidor MASTER ({$this->cluster->getMasterApiUrl()}).");
        }

        $device = WolDevice::findOrFail($id);
        $name = $device->name;
        $device->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email,
            'user_role' => Auth::user()?->role,
            'event' => 'deleted',
            'module' => 'wol',
            'auditable_type' => WolDevice::class,
            'auditable_id' => $id,
            'entity_name' => $name,
            'entity_label' => "ID #{$id}",
            'description' => "Dispositivo WoL eliminado: {$name}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.wol.index')
            ->with('status', "Dispositivo '{$name}' eliminado del registro de WoL.");
    }
}
