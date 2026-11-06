<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\NetworkDevice\CreateNetworkDeviceAction;
use App\Actions\NetworkDevice\GetDeviceIndexViewModelAction;
use App\Actions\NetworkDevice\UpdateNetworkDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\NetworkDevice\StoreNetworkDeviceRequest;
use App\Http\Requests\NetworkDevice\UpdateNetworkDeviceRequest;
use App\Models\MonitoredNetworkDevice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminNetworkDeviceController extends Controller
{
    /**
     * Muestra el listado centralizado de dispositivos de red de todas las sedes.
     */
    public function index(Request $request, GetDeviceIndexViewModelAction $action): View
    {
        $this->authorize('viewAny', MonitoredNetworkDevice::class);

        $viewModel = $action->execute($request);
        return view('admin.devices.index', $viewModel);
    }

    /**
     * Registra un nuevo dispositivo de red y lo asigna a una sede.
     */
    public function store(Request $request, ?CreateNetworkDeviceAction $action = null): RedirectResponse|JsonResponse
    {
        $this->authorize('create', MonitoredNetworkDevice::class);

        $action = $action ?? app(CreateNetworkDeviceAction::class);
        $formRequest = $request instanceof StoreNetworkDeviceRequest
            ? $request
            : StoreNetworkDeviceRequest::createFrom($request);

        if (!$request instanceof StoreNetworkDeviceRequest) {
            $formRequest->setContainer(app())->setRedirector(app(\Illuminate\Routing\Redirector::class))->validateResolved();
        }

        $device = $action->execute($formRequest->toDto(), $request);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Dispositivo [{$device->name}] creado exitosamente.",
                'device' => $device,
            ], 201);
        }

        $siteName = $device->site?->name ?? 'Sede';
        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$device->name}] registrado exitosamente en la sede [{$siteName}].");
    }

    /**
     * Actualiza los datos técnicos y operativos de un dispositivo.
     */
    public function update(Request $request, MonitoredNetworkDevice $device, ?UpdateNetworkDeviceAction $action = null): RedirectResponse
    {
        $this->authorize('update', $device);

        $action = $action ?? app(UpdateNetworkDeviceAction::class);
        $formRequest = $request instanceof UpdateNetworkDeviceRequest
            ? $request
            : UpdateNetworkDeviceRequest::createFrom($request);

        if (!$request instanceof UpdateNetworkDeviceRequest) {
            $formRequest->setContainer(app())->setRedirector(app(\Illuminate\Routing\Redirector::class))->validateResolved();
        }

        $updated = $action->execute($device, $formRequest->toDto(), $request);

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$updated->name}] actualizado y sincronizado exitosamente.");
    }

    /**
     * Elimina un dispositivo de red.
     */
    public function destroy(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        $this->authorize('delete', $device);

        $name = $device->name;
        $device->delete();

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$name}] eliminado exitosamente.");
    }

    /**
     * Activa o desactiva el monitoreo de un dispositivo.
     */
    public function toggle(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $device->is_active = !$device->is_active;
        $device->save();

        app(SyncController::class)->exportToConfigFiles();

        $status = $device->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Dispositivo [{$device->name}] {$status} exitosamente.");
    }

    /**
     * Retorna datos históricos para gráficos y telemetría de dispositivos de red.
     */
    public function history(Request $request, MonitoredNetworkDevice $device): JsonResponse
    {
        $this->authorize('view', $device);

        $range = (string) $request->query('range', '24h');
        $since = match ($range) {
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $stats = DB::table('network_device_check_histories')
            ->where('monitored_network_device_id', $device->id)
            ->where('checked_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_checks,
                SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_checks,
                AVG(CASE WHEN is_up = 1 THEN latency_ms ELSE NULL END) as avg_latency,
                MAX(CASE WHEN is_up = 1 THEN latency_ms ELSE 0 END) as max_latency,
                MIN(CASE WHEN is_up = 1 THEN latency_ms ELSE NULL END) as min_latency
            ')->first();

        $totalChecks = (int) ($stats->total_checks ?? 0);
        $upChecks = (int) ($stats->up_checks ?? 0);
        $downChecks = $totalChecks - $upChecks;
        $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : 0.0;
        $avgLatency = round((float) ($stats->avg_latency ?? 0), 2);
        $maxLatency = round((float) ($stats->max_latency ?? 0), 2);
        $minLatency = round((float) ($stats->min_latency ?? 0), 2);

        $rawRecords = DB::table('network_device_check_histories')
            ->where('monitored_network_device_id', $device->id)
            ->where('checked_at', '>=', $since)
            ->select(['checked_at', 'is_up', 'latency_ms', 'status_message'])
            ->orderBy('checked_at', 'asc')
            ->get();

        $sampled = [];
        $lastState = null;
        $step = max(1, (int) ceil($rawRecords->count() / 150));

        foreach ($rawRecords as $idx => $r) {
            $currentState = (bool) $r->is_up;
            $isStateChange = ($lastState !== null && $lastState !== $currentState);
            $lastState = $currentState;

            if ($isStateChange || $idx % $step === 0 || $idx === $rawRecords->count() - 1) {
                $sampled[] = $r;
            }
        }

        $labels = [];
        $latencies = [];
        $statuses = [];

        foreach ($sampled as $r) {
            $dt = Carbon::parse($r->checked_at);
            $formattedTime = ($range === '7d' || $range === '30d')
                ? $dt->format('d/m H:i')
                : $dt->format('H:i');

            $labels[] = $formattedTime;
            $latencies[] = $r->is_up ? (float) $r->latency_ms : 0.0;
            $statuses[] = $r->is_up ? 1 : 0;
        }

        $incidents = $rawRecords->where('is_up', 0)->sortByDesc('checked_at')->take(30)->map(function ($r) {
            $dt = Carbon::parse($r->checked_at);
            return [
                'time' => $dt->format('Y-m-d H:i:s'),
                'time_human' => $dt->diffForHumans(),
                'status_message' => $r->status_message ?: 'Dispositivo Caído / Timeout Ping ICMP',
            ];
        })->values();

        $lastRecord = $rawRecords->last();
        $lastDt = $lastRecord ? Carbon::parse($lastRecord->checked_at) : null;

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'ip' => $device->ip ?: '0.0.0.0',
            ],
            'range' => $range,
            'stats' => [
                'total_checks' => $totalChecks,
                'up_checks' => $upChecks,
                'down_checks' => $downChecks,
                'uptime_percentage' => $uptimePct,
                'avg_latency' => $avgLatency,
                'max_latency' => $maxLatency,
                'min_latency' => $minLatency,
                'last_check' => $lastDt ? $lastDt->format('Y-m-d H:i:s') : null,
                'last_check_human' => $lastDt ? $lastDt->diffForHumans() : null,
            ],
            'labels' => $labels,
            'latencies' => $latencies,
            'statuses' => $statuses,
            'incidents' => $incidents,
        ]);
    }
}
