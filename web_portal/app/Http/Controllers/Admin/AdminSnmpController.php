<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MonitoredSite;
use App\Models\SnmpActivationLog;
use App\Models\SnmpDevice;
use App\Models\SnmpInterface;
use App\Models\SnmpInterfaceMetric;
use App\Models\SnmpOid;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class AdminSnmpController extends Controller
{
    /**
     * Muestra el panel principal de Dispositivos y Monitoreo SNMP.
     * Accesible tanto para Administradores como para Operadores (Modo Consulta).
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $typeFilter = $request->query('device_type');
        $siteFilter = $request->query('site_id');
        $search = $request->query('search');

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

        // Métricas HUD
        $totalDevices = SnmpDevice::count();
        $onlineDevices = SnmpDevice::where('last_poll_status', 'success')->count();
        $failingDevices = SnmpDevice::where('consecutive_failures', '>', 0)->count();
        $totalInterfaces = SnmpInterface::count();
        $monitoredInterfaces = SnmpInterface::where('is_monitored', true)->count();

        $devices = $query->paginate(15)->withQueryString();
        $sites = MonitoredSite::where('is_active', true)->orderBy('sort_order')->get();
        $recentLogs = SnmpActivationLog::latest('executed_at')->take(5)->get();
        $oidsCount = SnmpOid::where('is_active', true)->count();

        return view('admin.snmp.index', compact(
            'devices',
            'sites',
            'recentLogs',
            'totalDevices',
            'onlineDevices',
            'failingDevices',
            'totalInterfaces',
            'monitoredInterfaces',
            'oidsCount'
        ));
    }

    /**
     * Retorna detalle de un equipo SNMP en formato JSON.
     */
    public function show(int $id): JsonResponse
    {
        $device = SnmpDevice::with(['site', 'networkDevice', 'interfaces.latestMetric', 'deviceOids.oid'])
            ->findOrFail($id);

        $payload = $device->toArray();
        $payload['community'] = $device->getCommunity();

        return response()->json([
            'success' => true,
            'device' => $payload,
        ]);
    }

    /**
     * Retorna listado de interfaces de red de un equipo.
     */
    public function interfaces(int $id): JsonResponse
    {
        $device = SnmpDevice::findOrFail($id);
        $interfaces = SnmpInterface::where('snmp_device_id', $id)
            ->with('latestMetric')
            ->orderBy('if_index')
            ->get();

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'sys_name' => $device->sys_name,
            ],
            'interfaces' => $interfaces,
        ]);
    }

    /**
     * Retorna métricas de ancho de banda y paquetes de una interfaz para Chart.js.
     */
    public function interfaceMetrics(int $interfaceId): JsonResponse
    {
        $interface = SnmpInterface::with('device')->findOrFail($interfaceId);

        $metrics = SnmpInterfaceMetric::where('snmp_interface_id', $interfaceId)
            ->where('collected_at', '>=', now()->subHours(24))
            ->orderBy('collected_at')
            ->get();

        $labels = [];
        $inBps = [];
        $outBps = [];
        $inUtil = [];
        $outUtil = [];
        $errors = [];

        foreach ($metrics as $m) {
            $labels[] = $m->collected_at ? $m->collected_at->timezone('America/Caracas')->format('h:i:s A') : '';
            $inBps[] = round((float) $m->in_bps, 2);
            $outBps[] = round((float) $m->out_bps, 2);
            $inUtil[] = round((float) $m->in_utilization_pct, 2);
            $outUtil[] = round((float) $m->out_utilization_pct, 2);
            $errors[] = (int) ($m->in_errors + $m->out_errors);
        }

        return response()->json([
            'success' => true,
            'interface' => [
                'id' => $interface->id,
                'name' => $interface->if_name ?: "Puerto {$interface->if_index}",
                'description' => $interface->if_description,
                'alias' => $interface->if_alias,
                'speed' => $interface->if_speed,
                'oper_status' => $interface->if_oper_status,
                'admin_status' => $interface->if_admin_status,
                'device_name' => $interface->device->name ?? 'Dispositivo',
            ],
            'labels' => $labels,
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'in_util' => $inUtil,
            'out_util' => $outUtil,
            'errors' => $errors,
        ]);
    }

    /**
     * Registra un nuevo dispositivo SNMP (Exclusivo Administrador).
     */
    public function store(Request $request): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de dispositivos SNMP deben realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:snmp_devices,ip_address',
            'snmp_version' => 'required|in:v2c,v3',
            'community' => 'nullable|string|max:255',
            'device_type' => 'required|in:router,switch,firewall,server,ups,ap,unknown',
            'site_id' => 'nullable|exists:monitored_sites,id',
            'poll_interval_seconds' => 'nullable|integer|min:10|max:3600',
            'notes' => 'nullable|string|max:500',
        ]);

        $device = new SnmpDevice();
        $device->name = $validated['name'];
        $device->ip_address = $validated['ip_address'];
        $device->snmp_version = $validated['snmp_version'];
        if (!empty($validated['community'])) {
            $device->setCommunity($validated['community']);
        }
        $device->device_type = $validated['device_type'];
        $device->site_id = $validated['site_id'] ?? null;
        $device->poll_interval_seconds = $validated['poll_interval_seconds'] ?? 60;
        $device->notes = $validated['notes'] ?? null;
        $device->is_active = true;
        $device->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'user_email' => Auth::user()->email,
            'user_role' => Auth::user()->role,
            'event' => 'created',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => "Dispositivo SNMP ({$device->ip_address})",
            'description' => "Dispositivo SNMP registrado manualmente: {$device->name} ({$device->ip_address})",
            'new_values' => $device->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Lanzar descubrimiento de interfaces y sondeo inicial en segundo plano
        $this->launchAsyncCommand("/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/snmp_poller.py --interfaces {$device->id}");

        return redirect()->route('admin.snmp.index')
            ->with('status', "Dispositivo {$device->name} registrado exitosamente. Se inició el sondeo inicial en segundo plano.");
    }

    /**
     * Actualiza la configuración de un dispositivo SNMP (Exclusivo Administrador).
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de dispositivos SNMP deben realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $device = SnmpDevice::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:snmp_devices,ip_address,' . $device->id,
            'snmp_port' => 'nullable|integer|min:1|max:65535',
            'snmp_version' => 'required|in:v2c,v3',
            'community' => 'nullable|string|max:255',
            'device_type' => 'required|in:router,switch,firewall,server,ups,ap,unknown',
            'site_id' => 'nullable|exists:monitored_sites,id',
            'poll_interval_seconds' => 'nullable|integer|min:10|max:3600',
            'is_active' => 'nullable',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldValues = $device->toArray();

        $device->name = $validated['name'];
        $device->ip_address = $validated['ip_address'];
        if (!empty($validated['snmp_port'])) {
            $device->snmp_port = (int)$validated['snmp_port'];
        }
        $device->snmp_version = $validated['snmp_version'];
        if (!empty($validated['community'])) {
            $device->setCommunity($validated['community']);
        }
        $device->device_type = $validated['device_type'];
        $device->site_id = $validated['site_id'] ?? null;
        $device->poll_interval_seconds = $validated['poll_interval_seconds'] ?? 60;
        $device->is_active = $request->has('is_active') && ($request->input('is_active') == '1' || $request->input('is_active') === true);
        $device->notes = $validated['notes'] ?? null;
        $device->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'user_email' => Auth::user()->email,
            'user_role' => Auth::user()->role,
            'event' => 'updated',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $device->id,
            'entity_name' => $device->name,
            'entity_label' => "Dispositivo SNMP ({$device->ip_address})",
            'description' => "Dispositivo SNMP actualizado: {$device->name}",
            'old_values' => $oldValues,
            'new_values' => $device->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.snmp.index')
            ->with('status', "Dispositivo {$device->name} actualizado correctamente.");
    }

    /**
     * Elimina un dispositivo SNMP (Exclusivo Administrador).
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). Las modificaciones de dispositivos SNMP deben realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $device = SnmpDevice::findOrFail($id);
        $name = $device->name;
        $ip = $device->ip_address;
        $oldValues = $device->toArray();

        $device->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'user_email' => Auth::user()->email,
            'user_role' => Auth::user()->role,
            'event' => 'deleted',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => $id,
            'entity_name' => $name,
            'entity_label' => "Dispositivo SNMP ({$ip})",
            'description' => "Dispositivo SNMP eliminado del sistema: {$name} ({$ip})",
            'old_values' => $oldValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.snmp.index')
            ->with('status', "Dispositivo {$name} eliminado.");
    }

    /**
     * Dispara sondeo SNMP inmediato para un dispositivo (Exclusivo Administrador).
     */
    public function triggerPoll(Request $request, int $id): JsonResponse
    {
        $device = SnmpDevice::findOrFail($id);

        $this->launchAsyncCommand("/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/snmp_poller.py --poll --device {$id}");

        return response()->json([
            'success' => true,
            'message' => "Sondeo SNMP iniciado para {$device->name} ({$device->ip_address}).",
        ]);
    }

    /**
     * Dispara descubrimiento de interfaces para un dispositivo (Exclusivo Administrador).
     */
    public function triggerInterfaceDiscovery(Request $request, int $id): JsonResponse
    {
        $device = SnmpDevice::findOrFail($id);

        $this->launchAsyncCommand("/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/snmp_poller.py --interfaces {$id}");

        return response()->json([
            'success' => true,
            'message' => "Descubrimiento de interfaces iniciado para {$device->name} ({$device->ip_address}).",
        ]);
    }

    /**
     * Conmuta el estado de monitoreo de una interfaz (Exclusivo Administrador).
     */
    public function toggleInterfaceMonitoring(Request $request, int $id): JsonResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return response()->json([
                'success' => false,
                'message' => "Acción Bloqueada: Servidor en modo ESCLAVO. Gestione interfaces en el Master ({$cluster->getMasterApiUrl()}).",
            ], 403);
        }

        $interface = SnmpInterface::findOrFail($id);
        $interface->is_monitored = !$interface->is_monitored;
        $interface->save();

        return response()->json([
            'success' => true,
            'is_monitored' => $interface->is_monitored,
            'message' => "Monitoreo de interfaz {$interface->if_name} " . ($interface->is_monitored ? 'activado' : 'desactivado') . ".",
        ]);
    }

    /**
     * Ejecuta activación remota SNMP (SSH, Telnet o pfSense API) (Exclusivo Administrador).
     */
    public function activateRemote(Request $request): JsonResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return response()->json([
                'status' => 'failed',
                'error' => "Acción Bloqueada: Servidor en modo ESCLAVO. La activación remota debe ejecutarse desde el Master ({$cluster->getMasterApiUrl()}).",
            ], 403);
        }

        $validated = $request->validate([
            'ip_address' => 'required|ip',
            'method' => 'required|in:ssh,telnet,pfsense',
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:100',
            'secret' => 'nullable|string|max:100',
            'community' => 'required|string|max:100',
            'port' => 'nullable|integer|min:1|max:65535',
        ]);

        $ip = escapeshellarg($validated['ip_address']);
        $method = escapeshellarg($validated['method']);
        $user = escapeshellarg($validated['username']);
        $pass = escapeshellarg($validated['password']);
        $comm = escapeshellarg($validated['community']);
        $userId = Auth::id() ?: 1;

        $cmd = "/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/snmp_activator.py"
             . " --ip {$ip} --method {$method} --user {$user} --password {$pass} --community {$comm} --user-id {$userId}";

        if (!empty($validated['secret'])) {
            $cmd .= " --secret " . escapeshellarg($validated['secret']);
        }
        if (!empty($validated['port'])) {
            $cmd .= " --port " . (int) $validated['port'];
        }

        // Ejecutar de forma síncrona con timeout de 20s
        exec($cmd . " 2>&1", $outputLines, $returnVar);
        $outputStr = implode("\n", $outputLines);

        $resultJson = json_decode($outputStr, true);
        $status = $resultJson['status'] ?? ($returnVar === 0 ? 'success' : 'failed');
        $error = $resultJson['error'] ?? null;

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'user_email' => Auth::user()->email,
            'user_role' => Auth::user()->role,
            'event' => 'remote_activation',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => null,
            'entity_name' => $validated['ip_address'],
            'entity_label' => "Activación Remota ({$validated['method']})",
            'description' => "Intento de activación remota SNMP ({$validated['method']}) en {$validated['ip_address']}: {$status}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($status === 'success') {
            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => "SNMP activado correctamente en {$validated['ip_address']}.",
                'output' => $resultJson['output'] ?? $outputStr,
            ]);
        }

        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => "Fallo al activar SNMP: " . ($error ?: "Error de comunicación"),
            'error' => $error,
            'output' => $outputStr,
        ], 422);
    }

    /**
     * Lanza comando en segundo plano sin bloquear la solicitud HTTP.
     */
    protected function launchAsyncCommand(string $cmd): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B {$cmd}", 'r'));
        } else {
            exec("{$cmd} > /dev/null 2>&1 &");
        }
    }
}
