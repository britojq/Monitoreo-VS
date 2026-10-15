<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Proxies\CreateProxyAction;
use App\Actions\Proxies\DeleteProxyAction;
use App\Actions\Proxies\ToggleProxyAction;
use App\Actions\Proxies\UpdateProxyAction;
use App\Contracts\Repositories\ProxyRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proxies\StoreProxyRequest;
use App\Http\Requests\Proxies\UpdateProxyRequest;
use App\Models\MonitoredProxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProxyController extends Controller
{
    private readonly ProxyRepositoryInterface $repository;

    public function __construct(
        ?ProxyRepositoryInterface $repository = null
    ) {
        $this->repository = $repository ?? app(ProxyRepositoryInterface::class);
    }

    public function index(): View
    {
        $proxies = $this->repository->getAllOrdered();

        return view('admin.proxies.index', compact('proxies'));
    }

    public function store(Request $request, ?CreateProxyAction $action = null): RedirectResponse
    {
        $action = $action ?? app(CreateProxyAction::class);
        $formRequest = $request instanceof StoreProxyRequest
            ? $request
            : StoreProxyRequest::createFrom($request);

        if (!$request instanceof StoreProxyRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $action->execute($formRequest->toDto());

        return redirect()->route('admin.proxies.index')->with('success', 'Proxy registrado exitosamente.');
    }

    public function update(Request $request, MonitoredProxy $proxy, ?UpdateProxyAction $action = null): RedirectResponse
    {
        $action = $action ?? app(UpdateProxyAction::class);
        $formRequest = $request instanceof UpdateProxyRequest
            ? $request
            : UpdateProxyRequest::createFrom($request);

        if (!$request instanceof UpdateProxyRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $action->execute($proxy->id, $formRequest->toDto($proxy));

        return redirect()->route('admin.proxies.index')->with('success', 'Proxy actualizado exitosamente.');
    }

    public function toggle(MonitoredProxy $proxy, ?ToggleProxyAction $action = null): RedirectResponse
    {
        $action = $action ?? app(ToggleProxyAction::class);
        $updated = $action->execute($proxy->id);
        $status = $updated->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Proxy [{$proxy->name}] {$status} exitosamente.");
    }

    public function history(Request $request, MonitoredProxy $proxy): JsonResponse
    {
        $range = (string) $request->query('range', '24h');
        $query = $proxy->histories()->reorder('checked_at', 'asc');

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
                'status_message' => $r->status_message ?: 'Fallo de conexión / Proxy caído',
                'http_code' => $r->http_code ?: 'N/A',
            ];
        });

        $lastRecord = $records->last();

        return response()->json([
            'success' => true,
            'proxy' => [
                'id' => $proxy->id,
                'letter' => $proxy->letter,
                'name' => $proxy->name,
                'ip_port' => $proxy->ip_port,
                'is_active' => (bool) $proxy->is_active,
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

    public function destroy(MonitoredProxy $proxy, ?DeleteProxyAction $action = null): RedirectResponse
    {
        $action = $action ?? app(DeleteProxyAction::class);
        $action->execute($proxy->id);

        return redirect()->route('admin.proxies.index')->with('success', 'Proxy eliminado exitosamente.');
    }
}
