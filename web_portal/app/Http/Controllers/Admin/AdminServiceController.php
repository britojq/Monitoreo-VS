<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Services\CreateServiceAction;
use App\Actions\Services\DeleteServiceAction;
use App\Actions\Services\ToggleServiceAction;
use App\Actions\Services\UpdateServiceAction;
use App\Contracts\Repositories\ServiceRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\StoreServiceRequest;
use App\Http\Requests\Services\UpdateServiceRequest;
use App\Models\MonitoredService;
use App\Models\MonitoringSnapshot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminServiceController extends Controller
{
    private readonly ServiceRepositoryInterface $repository;

    public function __construct(
        ?ServiceRepositoryInterface $repository = null
    ) {
        $this->repository = $repository ?? app(ServiceRepositoryInterface::class);
    }

    public function index(): View
    {
        $services = $this->repository->getAllOrdered();
        $latestSnapshot = MonitoringSnapshot::latest()->first();
        $snapshotServices = collect();

        if ($latestSnapshot && isset($latestSnapshot->payload_json['services'])) {
            foreach ($latestSnapshot->payload_json['services'] as $srv) {
                if (isset($srv['id'])) {
                    $snapshotServices->put((string) $srv['id'], $srv);
                }
                if (isset($srv['letter'])) {
                    $snapshotServices->put((string) $srv['letter'], $srv);
                }
            }
        }

        return view('admin.services.index', compact('services', 'snapshotServices'));
    }

    public function store(Request $request, ?CreateServiceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(CreateServiceAction::class);
        $formRequest = $request instanceof StoreServiceRequest
            ? $request
            : StoreServiceRequest::createFrom($request);

        if (!$request instanceof StoreServiceRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $action->execute($formRequest->toDto());

        return redirect()->route('admin.services.index')->with('success', 'Servicio agregado exitosamente.');
    }

    public function update(Request $request, MonitoredService $service, ?UpdateServiceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(UpdateServiceAction::class);
        $formRequest = $request instanceof UpdateServiceRequest
            ? $request
            : UpdateServiceRequest::createFrom($request);

        if (!$request instanceof UpdateServiceRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $action->execute($service->id, $formRequest->toDto($service));

        return redirect()->route('admin.services.index')->with('success', 'Servicio actualizado exitosamente.');
    }

    public function toggle(MonitoredService $service, ?ToggleServiceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(ToggleServiceAction::class);
        $updated = $action->execute($service->id);
        $status = $updated->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Servicio [{$service->name}] {$status} exitosamente.");
    }

    public function history(Request $request, MonitoredService $service): JsonResponse
    {
        $range = (string) $request->query('range', '24h');
        $since = match ($range) {
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $stats = DB::table('service_check_histories')
            ->where('monitored_service_id', $service->id)
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

        $rawRecords = DB::table('service_check_histories')
            ->where('monitored_service_id', $service->id)
            ->where('checked_at', '>=', $since)
            ->select(['checked_at', 'is_up', 'latency_ms', 'status_message', 'http_code'])
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
        $points = [];

        foreach ($sampled as $r) {
            $dt = Carbon::parse($r->checked_at);
            $formattedTime = ($range === '7d' || $range === '30d')
                ? $dt->format('d/m H:i')
                : $dt->format('H:i');

            $labels[] = $formattedTime;
            $latencies[] = $r->is_up ? (float) $r->latency_ms : 0.0;
            $statuses[] = $r->is_up ? 1 : 0;
            $points[] = [
                'time' => $dt->format('Y-m-d H:i:s'),
                'is_up' => (bool) $r->is_up,
                'latency_ms' => (float) $r->latency_ms,
                'http_code' => $r->http_code,
                'status_message' => $r->status_message,
            ];
        }

        $incidents = $rawRecords->where('is_up', 0)->sortByDesc('checked_at')->take(30)->map(function ($r) {
            $dt = Carbon::parse($r->checked_at);
            return [
                'time' => $dt->format('Y-m-d H:i:s'),
                'time_human' => $dt->diffForHumans(),
                'status_message' => $r->status_message ?: 'Timeout / Sin respuesta',
                'http_code' => $r->http_code ?: 'N/A',
            ];
        })->values();

        $lastRecord = $rawRecords->last();
        $lastDt = $lastRecord ? Carbon::parse($lastRecord->checked_at) : null;

        return response()->json([
            'success' => true,
            'service' => [
                'id' => $service->id,
                'letter' => $service->letter,
                'name' => $service->name,
                'type' => $service->type,
                'target' => $service->host_ip ?: ($service->web_url ?: '127.0.0.1'),
                'is_active' => (bool) $service->is_active,
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
            'points' => $points,
            'incidents' => $incidents,
        ]);
    }

    public function destroy(MonitoredService $service, ?DeleteServiceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(DeleteServiceAction::class);
        $action->execute($service->id);

        return redirect()->route('admin.services.index')->with('success', 'Servicio eliminado exitosamente.');
    }
}
