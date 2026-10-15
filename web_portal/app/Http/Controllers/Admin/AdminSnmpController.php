<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Snmp\ActivateRemoteSnmpAction;
use App\Actions\Snmp\CreateSnmpDeviceAction;
use App\Actions\Snmp\DeleteSnmpDeviceAction;
use App\Actions\Snmp\UpdateSnmpDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Snmp\ActivateRemoteSnmpRequest;
use App\Http\Requests\Snmp\StoreSnmpDeviceRequest;
use App\Http\Requests\Snmp\UpdateSnmpDeviceRequest;
use App\Models\SnmpDevice;
use App\Models\SnmpInterface;
use App\Models\SnmpInterfaceMetric;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSnmpController extends Controller
{
    /**
     * Redirige al panel unificado de Radar & Telemetría SNMP (Pestaña SNMP).
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.discovery.index', array_merge(['tab' => 'snmp'], $request->query()));
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
    public function store(Request $request, ?CreateSnmpDeviceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(CreateSnmpDeviceAction::class);
        $formRequest = $request instanceof StoreSnmpDeviceRequest
            ? $request
            : StoreSnmpDeviceRequest::createFrom($request);

        if (!$request instanceof StoreSnmpDeviceRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $result = $action->execute(
            $formRequest->validated(),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('admin.discovery.index', ['tab' => 'snmp'])
            ->with('status', $result['message']);
    }

    /**
     * Actualiza la configuración de un dispositivo SNMP (Exclusivo Administrador).
     */
    public function update(Request $request, int $id, ?UpdateSnmpDeviceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(UpdateSnmpDeviceAction::class);
        $formRequest = $request instanceof UpdateSnmpDeviceRequest
            ? $request
            : UpdateSnmpDeviceRequest::createFrom($request);

        if (!$request instanceof UpdateSnmpDeviceRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $isActive = $request->has('is_active') && ($request->input('is_active') == '1' || $request->input('is_active') === true);

        $result = $action->execute(
            $id,
            $formRequest->validated(),
            $isActive,
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('admin.discovery.index', ['tab' => 'snmp'])
            ->with('status', $result['message']);
    }

    /**
     * Elimina un dispositivo SNMP (Exclusivo Administrador).
     */
    public function destroy(Request $request, int $id, ?DeleteSnmpDeviceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(DeleteSnmpDeviceAction::class);
        $result = $action->execute(
            $id,
            $request->ip(),
            $request->userAgent()
        );

        if (!($result['success'] ?? false)) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('admin.discovery.index', ['tab' => 'snmp'])
            ->with('status', $result['message']);
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
            'message' => "Monitoreo de interfaz {$interface->if_name} " . ($interface->is_monitored ? 'activado' : 'desactivado') . '.',
        ]);
    }

    /**
     * Ejecuta activación remota SNMP (SSH, Telnet o pfSense API) (Exclusivo Administrador).
     */
    public function activateRemote(Request $request, ?ActivateRemoteSnmpAction $action = null): JsonResponse
    {
        $action = $action ?? app(ActivateRemoteSnmpAction::class);
        $formRequest = $request instanceof ActivateRemoteSnmpRequest
            ? $request
            : ActivateRemoteSnmpRequest::createFrom($request);

        if (!$request instanceof ActivateRemoteSnmpRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $result = $action->execute(
            $formRequest->validated(),
            $request->ip(),
            $request->userAgent()
        );

        if (($result['status_code'] ?? 200) !== 200) {
            return response()->json([
                'success' => false,
                'status' => $result['status'] ?? 'failed',
                'message' => $result['message'] ?? 'Error al activar SNMP',
                'error' => $result['error'] ?? null,
                'output' => $result['output'] ?? '',
            ], $result['status_code']);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => $result['message'],
            'output' => $result['output'] ?? '',
        ]);
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
