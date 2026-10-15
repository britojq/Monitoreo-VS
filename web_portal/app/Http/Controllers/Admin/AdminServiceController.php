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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $query = $service->histories()->reorder('checked_at', 'asc');

        match ($range) {
            '6h' => $query->where('checked_at', '>=', now()->subHours(6)),
            '7d' => $query->where('checked_at', '>=', now()->subDays(7)),
            '30d' => $query->where('checked_at', '>=', now()->subDays(30)),
            default => $query->where('checked_at', '>=', now()->subHours(24)),
        };

        $records = $query->get();
        $totalChecks = $records->count();
        $upChecks = $records->where('is_up', true)->count();
        $downChecks = $totalChecks - $upChecks;
        $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : 0.0;

        $upRecords = $records->where('is_up', true);
        $avgLatency = $upRecords->count() > 0 ? round((float) $upRecords->avg('latency_ms'), 2) : 0.0;
        $maxLatency = $upRecords->count() > 0 ? round((float) $upRecords->max('latency_ms'), 2) : 0.0;
        $minLatency = $upRecords->count() > 0 ? round((float) $upRecords->min('latency_ms'), 2) : 0.0;

        $labels = [];
        $latencies = [];
        $statuses = [];
        $points = [];

        foreach ($records as $r) {
            $formattedTime = ($range === '7d' || $range === '30d')
                ? $r->checked_at->format('d/m H:i')
                : $r->checked_at->format('H:i');

            $labels[] = $formattedTime;
            $latencies[] = $r->is_up ? (float) $r->latency_ms : 0.0;
            $statuses[] = $r->is_up ? 1 : 0;
            $points[] = [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'is_up' => (bool) $r->is_up,
                'latency_ms' => (float) $r->latency_ms,
                'http_code' => $r->http_code,
                'status_message' => $r->status_message,
            ];
        }

        $incidents = $records->where('is_up', false)->sortByDesc('checked_at')->values()->map(function ($r) {
            return [
                'time' => $r->checked_at->format('Y-m-d H:i:s'),
                'time_human' => $r->checked_at->diffForHumans(),
                'status_message' => $r->status_message ?: 'Timeout / Sin respuesta',
                'http_code' => $r->http_code ?: 'N/A',
            ];
        });

        $lastRecord = $records->last();

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
                'last_check' => $lastRecord ? $lastRecord->checked_at->format('Y-m-d H:i:s') : null,
                'last_check_human' => $lastRecord ? $lastRecord->checked_at->diffForHumans() : null,
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
