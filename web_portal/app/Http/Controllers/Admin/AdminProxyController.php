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
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $since = match ($range) {
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $stats = DB::table('proxy_check_histories')
            ->where('monitored_proxy_id', $proxy->id)
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

        $rawRecords = DB::table('proxy_check_histories')
            ->where('monitored_proxy_id', $proxy->id)
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
                'status_message' => $r->status_message ?: 'Fallo de conexión / Proxy caído',
                'http_code' => $r->http_code ?: 'N/A',
            ];
        })->values();

        $lastRecord = $rawRecords->last();
        $lastDt = $lastRecord ? Carbon::parse($lastRecord->checked_at) : null;

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

    public function destroy(MonitoredProxy $proxy, ?DeleteProxyAction $action = null): RedirectResponse
    {
        $action = $action ?? app(DeleteProxyAction::class);
        $action->execute($proxy->id);

        return redirect()->route('admin.proxies.index')->with('success', 'Proxy eliminado exitosamente.');
    }
}
