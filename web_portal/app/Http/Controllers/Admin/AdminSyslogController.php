<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyslogEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSyslogController extends Controller
{
    /**
     * Muestra la consola centralizada de eventos Syslog en vivo.
     */
    public function index(Request $request): View
    {
        $severityFilter = $request->query('severity');
        $sourceFilter = $request->query('source_ip');
        $programFilter = $request->query('program');
        $search = $request->query('search');

        $query = SyslogEvent::orderByDesc('id');

        if ($severityFilter !== null && $severityFilter !== '') {
            $query->where('severity', (int) $severityFilter);
        }

        if (!empty($sourceFilter)) {
            $query->where('source_ip', $sourceFilter);
        }

        if (!empty($programFilter)) {
            $query->where('program', $programFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('program', 'like', "%{$search}%")
                  ->orWhere('hostname', 'like', "%{$search}%")
                  ->orWhere('source_ip', 'like', "%{$search}%");
            });
        }

        // Métricas HUD
        $totalLogs = SyslogEvent::count();
        $criticalLogs = SyslogEvent::whereIn('severity', [0, 1, 2])->count();
        $errorLogs = SyslogEvent::where('severity', 3)->count();
        $sourcesCount = SyslogEvent::distinct('source_ip')->count('source_ip');

        $events = $query->paginate(30)->withQueryString();

        // Listas para filtros
        $programs = SyslogEvent::whereNotNull('program')->distinct()->orderBy('program')->pluck('program');
        $sources = SyslogEvent::distinct()->orderBy('source_ip')->pluck('source_ip');

        return view('admin.syslog.index', compact(
            'events',
            'totalLogs',
            'criticalLogs',
            'errorLogs',
            'sourcesCount',
            'programs',
            'sources',
            'severityFilter',
            'sourceFilter',
            'programFilter',
            'search'
        ));
    }

    /**
     * Endpoint API JSON para Live-Tail (autoscroll en tiempo real).
     */
    public function live(Request $request): JsonResponse
    {
        $lastId = (int) $request->query('last_id', 0);
        $limit = min((int) $request->query('limit', 20), 50);

        $query = SyslogEvent::orderBy('id', 'asc');
        if ($lastId > 0) {
            $query->where('id', '>', $lastId);
        } else {
            $query->latest('id')->take($limit);
        }

        $events = $query->get()->map(function ($ev) {
            return [
                'id' => $ev->id,
                'source_ip' => $ev->source_ip,
                'hostname' => $ev->hostname,
                'severity' => $ev->severity,
                'severity_label' => $ev->severity_label,
                'severity_color' => $ev->severity_color,
                'program' => $ev->program ?: 'syslog',
                'message' => $ev->message,
                'received_at' => $ev->received_at ? $ev->received_at->format('H:i:s d/m') : '',
            ];
        });

        return response()->json([
            'success' => true,
            'events' => $events,
            'latest_id' => $events->isNotEmpty() ? $events->last()['id'] : $lastId,
        ]);
    }
}
