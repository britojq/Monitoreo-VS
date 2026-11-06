<?php

declare(strict_types=1);

namespace App\Actions\NetRadar;

use App\Models\NetRadarEvent;
use App\Models\NetRadarHost;
use App\Models\NetRadarSnapshot;
use Illuminate\Http\Request;

class FetchNetRadarDashboardAction
{
    public function execute(Request $request): array
    {
        $statusFilter = $request->query('status', 'all');
        $search = $request->query('search');
        $sort = $request->query('sort', 'total_bytes');
        $direction = $request->query('direction', 'desc');

        $query = NetRadarHost::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                  ->orWhere('mac', 'like', "%{$search}%")
                  ->orWhere('hostname', 'like', "%{$search}%")
                  ->orWhere('vendor', 'like', "%{$search}%")
                  ->orWhere('os_detected', 'like', "%{$search}%")
                  ->orWhere('last_update_target', 'like', "%{$search}%");
            });
        }

        if ($statusFilter === 'updating') {
            $query->whereIn('update_status', ['checking', 'downloading']);
        } elseif ($statusFilter === 'windows_update') {
            $query->where('last_update_type', 'windows_update')
                  ->whereIn('update_status', ['checking', 'downloading']);
        } elseif ($statusFilter === 'linux_repo') {
            $query->where('last_update_type', 'linux_repo')
                  ->whereIn('update_status', ['checking', 'downloading']);
        } elseif ($statusFilter === 'active') {
            $query->where('last_seen_at', '>=', now()->subMinutes(15));
        } elseif ($statusFilter === 'inactive') {
            $query->where(function ($q) {
                $q->whereNull('last_seen_at')
                  ->orWhere('last_seen_at', '<', now()->subMinutes(15));
            });
        }

        $allowedSorts = ['total_bytes', 'bytes_in', 'bytes_out', 'last_seen_at', 'ip', 'update_bytes', 'packet_count'];
        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, strtolower($direction) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('total_bytes');
        }

        $hosts = $query->paginate(20)->withQueryString();

        $totalBytesIn = (int) (NetRadarHost::sum('bytes_in') ?? 0);
        $totalBytesOut = (int) (NetRadarHost::sum('bytes_out') ?? 0);
        $totalTraffic = (int) ($totalBytesIn + $totalBytesOut);

        return [
            'hosts' => $hosts,
            'totalHosts' => NetRadarHost::count(),
            'activeHosts' => NetRadarHost::where('last_seen_at', '>=', now()->subMinutes(15))->count(),
            'windowsUpdating' => NetRadarHost::where('last_update_type', 'windows_update')
                ->whereIn('update_status', ['checking', 'downloading'])
                ->where('last_update_at', '>=', now()->subHours(2))
                ->count(),
            'linuxUpdating' => NetRadarHost::where('last_update_type', 'linux_repo')
                ->whereIn('update_status', ['checking', 'downloading'])
                ->where('last_update_at', '>=', now()->subHours(2))
                ->count(),
            'fmtTotalTraffic' => NetRadarSnapshot::formatBytes((int) $totalTraffic),
            'fmtBytesIn' => NetRadarSnapshot::formatBytes((int) $totalBytesIn),
            'fmtBytesOut' => NetRadarSnapshot::formatBytes((int) $totalBytesOut),
            'recentEvents' => NetRadarEvent::latest('created_at')->take(8)->get(),
            'latestSnapshot' => NetRadarSnapshot::latest('created_at')->first(),
            'statusFilter' => $statusFilter,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    public function getDataJson(): array
    {
        $totalBytesIn = NetRadarHost::sum('bytes_in') ?? 0;
        $totalBytesOut = NetRadarHost::sum('bytes_out') ?? 0;

        return [
            'success' => true,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'kpis' => [
                'total_hosts' => NetRadarHost::count(),
                'active_hosts' => NetRadarHost::where('last_seen_at', '>=', now()->subMinutes(15))->count(),
                'windows_updating' => NetRadarHost::where('last_update_type', 'windows_update')
                    ->whereIn('update_status', ['checking', 'downloading'])
                    ->where('last_update_at', '>=', now()->subHours(2))->count(),
                'linux_updating' => NetRadarHost::where('last_update_type', 'linux_repo')
                    ->whereIn('update_status', ['checking', 'downloading'])
                    ->where('last_update_at', '>=', now()->subHours(2))->count(),
                'total_bytes_in' => $totalBytesIn,
                'total_bytes_out' => $totalBytesOut,
                'fmt_total' => NetRadarSnapshot::formatBytes($totalBytesIn + $totalBytesOut),
                'fmt_in' => NetRadarSnapshot::formatBytes($totalBytesIn),
                'fmt_out' => NetRadarSnapshot::formatBytes($totalBytesOut),
            ],
            'top_talkers' => NetRadarHost::orderByDesc('total_bytes')->take(5)->get([
                'ip', 'mac', 'hostname', 'vendor', 'os_detected', 'total_bytes', 'update_status', 'last_update_type'
            ]),
            'recent_events' => NetRadarEvent::latest('created_at')->take(5)->get(),
        ];
    }

    public function getEventsJson(?string $ip = null): array
    {
        $query = NetRadarEvent::query()->latest('created_at');
        if (!empty($ip)) {
            $query->where('host_ip', $ip);
        }

        return [
            'success' => true,
            'ip' => $ip,
            'events' => $query->take(50)->get(),
        ];
    }
}
