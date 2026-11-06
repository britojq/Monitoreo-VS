<?php

declare(strict_types=1);

namespace App\Actions\NetRadar;

use App\Models\AuditLog;
use App\Models\NetRadarEvent;
use App\Models\NetRadarHost;
use App\Models\NetRadarSnapshot;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportNetRadarReportAction
{
    public function execute(?User $user, ?string $ip, ?string $userAgent): StreamedResponse
    {
        $dateStr = now()->format('Y-m-d H:i:s');
        $filename = 'reporte_net_radar_' . now()->format('Ymd_His') . '.md';

        if ($user) {
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
                'ip_address' => $ip,
                'user_agent' => $userAgent,
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

            fwrite($out, "# 🛰️ REPORTE DE TELEMETRÍA Y ANÁLISIS DE RED - NET RADAR\n");
            fwrite($out, "**Fecha y Hora de Generación:** {$dateStr}  \n");
            fwrite($out, "**Servidor:** Sede Central Valle Seco  \n");
            fwrite($out, "**Módulo:** NET Radar & Inspección de Tráfico (Inspirado en darkstat)  \n\n");
            fwrite($out, "---\n\n");

            $totalHosts = NetRadarHost::count();
            $activeHosts = NetRadarHost::where('last_seen_at', '>=', now()->subMinutes(15))->count();
            $winHosts = NetRadarHost::where('last_update_type', 'windows_update')->whereIn('update_status', ['checking', 'downloading'])->count();
            $linHosts = NetRadarHost::where('last_update_type', 'linux_repo')->whereIn('update_status', ['checking', 'downloading'])->count();
            $totalRx = NetRadarSnapshot::formatBytes(NetRadarHost::sum('bytes_in') ?? 0);
            $totalTx = NetRadarSnapshot::formatBytes(NetRadarHost::sum('bytes_out') ?? 0);

            fwrite($out, "## 📊 Resumen Consolidado\n\n");
            fwrite($out, "| Métrica | Valor |\n");
            fwrite($out, "| :--- | :--- |\n");
            fwrite($out, "| **Total Hosts Detectados** | `{$totalHosts}` |\n");
            fwrite($out, "| **Hosts Activos (Últimos 15 min)** | `{$activeHosts}` |\n");
            fwrite($out, "| **Equipos con Windows Update** | `{$winHosts}` |\n");
            fwrite($out, "| **Equipos con Repositorios Linux** | `{$linHosts}` |\n");
            fwrite($out, "| **Tráfico Acumulado RX (Bajada)** | `{$totalRx}` |\n");
            fwrite($out, "| **Tráfico Acumulado TX (Subida)** | `{$totalTx}` |\n\n");

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
                    $uTarget = $uh->last_update_target ?: 'Repositorio / Dominio';
                    $uTraffic = $uh->formatted_update_bytes;
                    $uDate = $uh->last_update_at ? $uh->last_update_at->format('Y-m-d H:i:s') : 'N/A';
                    $uVendor = $uh->vendor ?: 'Desconocido';
                    fwrite($out, "| `{$uh->ip}` | `{$uh->mac}` | {$uVendor} | **{$uType}** ({$uh->update_status}) | `{$uTarget}` | {$uTraffic} | {$uDate} |\n");
                }
                fwrite($out, "\n");
            }

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
