<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NetRadarEvent;
use App\Models\NetRadarHost;
use App\Models\NetRadarSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminNetRadarController extends Controller
{
    /**
     * Panel principal de NET Radar
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status', 'all');
        $search = $request->query('search');
        $sort = $request->query('sort', 'total_bytes');
        $direction = $request->query('direction', 'desc');

        $query = NetRadarHost::query();

        // Filtro de búsqueda
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

        // Filtro por estado
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

        // Ordenamiento seguro
        $allowedSorts = ['total_bytes', 'bytes_in', 'bytes_out', 'last_seen_at', 'ip', 'update_bytes', 'packet_count'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, strtolower($direction) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('total_bytes');
        }

        $hosts = $query->paginate(20)->withQueryString();

        // Métricas HUD en tiempo real
        $totalHosts = NetRadarHost::count();
        $activeHosts = NetRadarHost::where('last_seen_at', '>=', now()->subMinutes(15))->count();
        $windowsUpdating = NetRadarHost::where('last_update_type', 'windows_update')
            ->whereIn('update_status', ['checking', 'downloading'])
            ->where('last_update_at', '>=', now()->subHours(2))
            ->count();
        $linuxUpdating = NetRadarHost::where('last_update_type', 'linux_repo')
            ->whereIn('update_status', ['checking', 'downloading'])
            ->where('last_update_at', '>=', now()->subHours(2))
            ->count();

        $totalBytesIn = NetRadarHost::sum('bytes_in') ?? 0;
        $totalBytesOut = NetRadarHost::sum('bytes_out') ?? 0;
        $totalTraffic = $totalBytesIn + $totalBytesOut;

        // Formateo de bytes
        $fmtTotalTraffic = NetRadarSnapshot::formatBytes($totalTraffic);
        $fmtBytesIn = NetRadarSnapshot::formatBytes($totalBytesIn);
        $fmtBytesOut = NetRadarSnapshot::formatBytes($totalBytesOut);

        // Eventos recientes forenses de actualizaciones
        $recentEvents = NetRadarEvent::latest('created_at')->take(8)->get();

        // Último Snapshot consolidado
        $latestSnapshot = NetRadarSnapshot::latest('created_at')->first();

        return view('admin.netradar.index', compact(
            'hosts',
            'totalHosts',
            'activeHosts',
            'windowsUpdating',
            'linuxUpdating',
            'fmtTotalTraffic',
            'fmtBytesIn',
            'fmtBytesOut',
            'recentEvents',
            'latestSnapshot',
            'statusFilter',
            'search',
            'sort',
            'direction'
        ));
    }

    /**
     * Endpoint API JSON para auto-refresco y gráficos
     */
    public function data(Request $request): JsonResponse
    {
        $totalHosts = NetRadarHost::count();
        $activeHosts = NetRadarHost::where('last_seen_at', '>=', now()->subMinutes(15))->count();
        $windowsUpdating = NetRadarHost::where('last_update_type', 'windows_update')
            ->whereIn('update_status', ['checking', 'downloading'])
            ->where('last_update_at', '>=', now()->subHours(2))
            ->count();
        $linuxUpdating = NetRadarHost::where('last_update_type', 'linux_repo')
            ->whereIn('update_status', ['checking', 'downloading'])
            ->where('last_update_at', '>=', now()->subHours(2))
            ->count();

        $totalBytesIn = NetRadarHost::sum('bytes_in') ?? 0;
        $totalBytesOut = NetRadarHost::sum('bytes_out') ?? 0;

        $topTalkers = NetRadarHost::orderByDesc('total_bytes')->take(5)->get([
            'ip', 'mac', 'hostname', 'vendor', 'os_detected', 'total_bytes', 'update_status', 'last_update_type'
        ]);

        $recentEvents = NetRadarEvent::latest('created_at')->take(5)->get();

        return response()->json([
            'success' => true,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'kpis' => [
                'total_hosts' => $totalHosts,
                'active_hosts' => $activeHosts,
                'windows_updating' => $windowsUpdating,
                'linux_updating' => $linuxUpdating,
                'total_bytes_in' => $totalBytesIn,
                'total_bytes_out' => $totalBytesOut,
                'fmt_total' => NetRadarSnapshot::formatBytes($totalBytesIn + $totalBytesOut),
                'fmt_in' => NetRadarSnapshot::formatBytes($totalBytesIn),
                'fmt_out' => NetRadarSnapshot::formatBytes($totalBytesOut),
            ],
            'top_talkers' => $topTalkers,
            'recent_events' => $recentEvents,
        ]);
    }

    /**
     * Endpoint JSON para obtener eventos forenses de un host específico
     */
    public function events(Request $request): JsonResponse
    {
        $ip = $request->query('ip');
        $query = NetRadarEvent::query()->latest('created_at');

        if (!empty($ip)) {
            $query->where('host_ip', $ip);
        }

        $events = $query->take(50)->get();

        return response()->json([
            'success' => true,
            'ip' => $ip,
            'events' => $events,
        ]);
    }

    /**
     * Dispara un escaneo manual en caliente de NET Radar
     */
    public function scanNow(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission('netradar.scan') && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para ejecutar capturas en vivo de red.'
            ], 403);
        }

        // Ejecutar net_radar_engine en segundo plano con modo --once
        $pythonBin = '/scripts/telegram-admin-bot/venv/bin/python';
        $scriptPath = '/scripts/telegram-admin-bot/monitor/net_radar_engine.py';

        if (!file_exists($pythonBin)) {
            $pythonBin = base_path('../venv/bin/python');
        }
        if (!file_exists($scriptPath)) {
            $scriptPath = base_path('../monitor/net_radar_engine.py');
        }

        if (file_exists($pythonBin) && file_exists($scriptPath)) {
            $cmd = sprintf(
                'sudo %s %s --once --cycle 10 > /tmp/monitor/net_radar_scan.log 2>&1 &',
                escapeshellarg($pythonBin),
                escapeshellarg($scriptPath)
            );
            exec($cmd);

            // Registrar en auditoría
            if (class_exists(AuditLog::class) && $user) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'event' => 'scanned',
                    'module' => 'netradar',
                    'auditable_type' => 'NetRadar',
                    'auditable_id' => 0,
                    'entity_name' => 'NET Radar',
                    'entity_label' => 'Barrido en Vivo',
                    'description' => "El usuario {$user->name} disparó una captura manual de tráfico en NET Radar.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Captura y análisis de NET Radar iniciado en segundo plano. Los resultados se actualizarán automáticamente.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'El motor NET Radar no está disponible en la ruta especificada.'
        ], 500);
    }

    /**
     * Exporta reporte estructurado de NET Radar en formato Markdown (.md)
     */
    public function exportReport(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $dateStr = now()->format('Y-m-d H:i:s');
        $filename = 'reporte_net_radar_' . now()->format('Ymd_His') . '.md';

        // Auditoría forense del evento de exportación
        if (class_exists(AuditLog::class) && $user) {
            AuditLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'event' => 'exported',
                'module' => 'netradar',
                'auditable_type' => 'NetRadar',
                'auditable_id' => 0,
                'entity_name' => 'NET Radar',
                'entity_label' => 'Reporte Markdown',
                'description' => "El usuario {$user->name} ({$user->email}) exportó el reporte estructurado de NET Radar en formato Markdown.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $headers = [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($dateStr) {
            $out = fopen('php://output', 'w');

            // Header del reporte Markdown
            fwrite($out, "# 🛰️ REPORTE DE TELEMETRÍA Y ANÁLISIS DE RED - NET RADAR\n");
            fwrite($out, "**Fecha y Hora de Generación:** {$dateStr}  \n");
            fwrite($out, "**Servidor:** Sede Central Valle Seco  \n");
            fwrite($out, "**Módulo:** NET Radar & Inspección de Tráfico (Inspirado en darkstat)  \n\n");
            fwrite($out, "---\n\n");

            // Resumen Ejecutivo
            $totalHosts = NetRadarHost::count();
            $activeHosts = NetRadarHost::where('last_seen_at', '>=', now()->subMinutes(15))->count();
            $winHosts = NetRadarHost::where('last_update_type', 'windows_update')->whereIn('update_status', ['checking', 'downloading'])->count();
            $linHosts = NetRadarHost::where('last_update_type', 'linux_repo')->whereIn('update_status', ['checking', 'downloading'])->count();
            $totalRx = NetRadarSnapshot::formatBytes(NetRadarHost::sum('bytes_in') ?? 0);
            $totalTx = NetRadarSnapshot::formatBytes(NetRadarHost::sum('bytes_out') ?? 0);

            fwrite($out, "## 📊 Resumen Ejecutivo\n\n");
            fwrite($out, "| Métrica | Valor |\n");
            fwrite($out, "| :--- | :--- |\n");
            fwrite($out, "| **Total Hosts Detectados** | `{$totalHosts}` |\n");
            fwrite($out, "| **Hosts Activos (Últimos 15 min)** | `{$activeHosts}` |\n");
            fwrite($out, "| **Equipos con Windows Update** | `{$winHosts}` |\n");
            fwrite($out, "| **Equipos con Repositorios Linux** | `{$linHosts}` |\n");
            fwrite($out, "| **Tráfico Acumulado RX (Bajada)** | `{$totalRx}` |\n");
            fwrite($out, "| **Tráfico Acumulado TX (Subida)** | `{$totalTx}` |\n\n");

            // Sección Especial: Detección de Actualizaciones en Curso
            $updatingHosts = NetRadarHost::whereIn('update_status', ['checking', 'downloading'])
                ->orderByDesc('last_update_at')
                ->get();

            fwrite($out, "## 🚨 Detección de Equipos Buscando o Descargando Actualizaciones\n\n");
            if ($updatingHosts->isEmpty()) {
                fwrite($out, "_No se registran equipos en la red realizando peticiones o descargas de actualizaciones en este momento._\n\n");
            } else {
                fwrite($out, "| IP Host | MAC | Fabricante | Tipo Actualización | Objetivo / Dominio | Tráfico Actualización | Última Detección |\n");
                fwrite($out, "| :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n");
                foreach ($updatingHosts as $uh) {
                    $uType = $uh->last_update_type === 'windows_update' ? '🪟 Windows Update' : '🐧 Linux Repo';
                    $uTarget = $uh->last_update_target ?: 'Dominio Oficial';
                    $uTraffic = $uh->formatted_update_bytes;
                    $uDate = $uh->last_update_at ? $uh->last_update_at->format('Y-m-d H:i:s') : 'N/A';
                    $uVendor = $uh->vendor ?: 'Desconocido';
                    fwrite($out, "| `{$uh->ip}` | `{$uh->mac}` | {$uVendor} | **{$uType}** ({$uh->update_status}) | `{$uTarget}` | {$uTraffic} | {$uDate} |\n");
                }
                fwrite($out, "\n");
            }

            // Top Talkers
            $topTalkers = NetRadarHost::orderByDesc('total_bytes')->take(15)->get();
            fwrite($out, "## 🏆 Top 15 Hosts por Volumen de Tráfico (Top Talkers)\n\n");
            fwrite($out, "| # | IP Host | MAC | Hostname / Fabricante | SO Estimado | RX (In) | TX (Out) | Total Tráfico |\n");
            fwrite($out, "| :-: | :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n");
            $pos = 1;
            foreach ($topTalkers as $tt) {
                $name = $tt->hostname ?: ($tt->vendor ?: 'Dispositivo');
                $os = $tt->os_detected ?: 'Desconocido';
                $rx = $tt->formatted_bytes_in;
                $tx = $tt->formatted_bytes_out;
                $tot = $tt->formatted_total_bytes;
                fwrite($out, "| {$pos} | `{$tt->ip}` | `{$tt->mac}` | {$name} | {$os} | {$rx} | {$tx} | **{$tot}** |\n");
                $pos++;
            }
            fwrite($out, "\n");

            // Eventos Forenses Recientes
            $recentEvts = NetRadarEvent::latest('created_at')->take(20)->get();
            fwrite($out, "## 🛡️ Bitácora Forense de Eventos de Red Recientes\n\n");
            if ($recentEvts->isEmpty()) {
                fwrite($out, "_No hay eventos forenses registrados recientemente._\n\n");
            } else {
                fwrite($out, "| Fecha/Hora | IP Origen | Tipo de Evento | Severidad | Objetivo | Detalle |\n");
                fwrite($out, "| :--- | :--- | :--- | :--- | :--- | :--- |\n");
                foreach ($recentEvts as $ev) {
                    $evDate = $ev->created_at ? $ev->created_at->format('Y-m-d H:i:s') : 'N/A';
                    $evSev = strtoupper($ev->severity);
                    $evTarget = $ev->target_domain ?: '-';
                    fwrite($out, "| {$evDate} | `{$ev->host_ip}` | `{$ev->event_type}` | {$evSev} | `{$evTarget}` | {$ev->description} |\n");
                }
                fwrite($out, "\n");
            }

            fwrite($out, "---\n");
            fwrite($out, "*Reporte generado automáticamente por NET Radar - Sistema de Monitoreo Valle Seco.*\n");
            fclose($out);
        }, 200, $headers);
    }
}
