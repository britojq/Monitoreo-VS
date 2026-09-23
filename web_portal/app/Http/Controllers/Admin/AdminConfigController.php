<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigChangeLog;
use App\Models\DeviceConfiguration;
use App\Models\MonitoredNetworkDevice;
use App\Models\SnmpDevice;
use App\Services\ClusterConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

class AdminConfigController extends Controller
{
    /**
     * Muestra el panel de inventario de respaldos de configuraciones,
     * métricas HUD, auditoría diferencial y control de cambios.
     */
    public function index(Request $request): View
    {
        // 1. Métricas HUD para tarjetas superiores
        $totalDevices = DeviceConfiguration::distinct('device_ip')->count('device_ip');
        $totalBackups = DeviceConfiguration::where('status', 'success')->count();
        $backupsToday = DeviceConfiguration::whereDate('captured_at', today())->count();
        $totalChanges = ConfigChangeLog::where('change_type', 'modified')->count();
        $totalBytes = (int) DeviceConfiguration::sum('config_size_bytes');

        $totalSizeFormatted = $totalBytes >= 1048576
            ? number_format($totalBytes / 1048576, 2) . ' MB'
            : number_format($totalBytes / 1024, 1) . ' KB';

        // 2. Filtros y búsqueda
        $search = trim($request->input('search', ''));
        $deviceType = $request->input('device_type', 'all');
        $activeTab = $request->input('tab', 'backups');

        // Query principal de respaldos
        $configsQuery = DeviceConfiguration::with(['networkDevice', 'snmpDevice', 'latestChangeLog'])
            ->orderBy('captured_at', 'desc');

        if ($search !== '') {
            $configsQuery->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                    ->orWhere('device_ip', 'like', "%{$search}%")
                    ->orWhere('config_hash', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($deviceType !== 'all' && in_array($deviceType, ['cisco_router', 'cisco_switch', 'pfsense', 'linux_server', 'other'])) {
            $configsQuery->where('device_type', $deviceType);
        }

        $configurations = $configsQuery->paginate(12)->withQueryString();

        // Query de historial de cambios diferenciales (pestaña 2)
        $changesQuery = ConfigChangeLog::with(['configuration', 'previousConfiguration', 'networkDevice', 'snmpDevice'])
            ->orderBy('detected_at', 'desc');

        if ($search !== '') {
            $changesQuery->where(function ($q) use ($search) {
                $q->whereHas('configuration', function ($cq) use ($search) {
                    $cq->where('device_name', 'like', "%{$search}%")
                        ->orWhere('device_ip', 'like', "%{$search}%");
                })->orWhere('diff_summary', 'like', "%{$search}%");
            });
        }

        $changeLogs = $changesQuery->paginate(12, ['*'], 'changes_page')->withQueryString();

        // Catálogo de dispositivos disponibles para modal de nuevo respaldo
        $networkDevices = MonitoredNetworkDevice::where('is_active', true)->orderBy('name')->get();
        $snmpDevices = SnmpDevice::where('is_active', true)->orderBy('name')->get();

        return view('admin.configs.index', compact(
            'totalDevices',
            'totalBackups',
            'backupsToday',
            'totalChanges',
            'totalSizeFormatted',
            'configurations',
            'changeLogs',
            'networkDevices',
            'snmpDevices',
            'search',
            'deviceType',
            'activeTab'
        ));
    }

    /**
     * Retorna los detalles y el texto completo de una configuración para el visor AJAX.
     */
    public function show(int $id): JsonResponse
    {
        $config = DeviceConfiguration::with(['networkDevice', 'snmpDevice'])->find($id);

        if (!$config) {
            return response()->json(['error' => 'Configuración no encontrada.'], 404);
        }

        return response()->json([
            'id' => $config->id,
            'device_name' => $config->resolved_name,
            'device_ip' => $config->device_ip,
            'device_type' => $config->device_type_badge['label'] ?? $config->device_type,
            'config_hash' => $config->config_hash,
            'short_hash' => $config->short_hash,
            'config_size_formatted' => $config->size_formatted,
            'line_count' => $config->line_count,
            'captured_at' => $config->captured_at ? $config->captured_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A',
            'captured_by' => $config->captured_by,
            'status' => $config->status_label ?? $config->status,
            'notes' => $config->notes,
            'config_text' => $config->config_text,
        ]);
    }

    /**
     * Retorna el diff unificado asociado a una versión específica para el visor de cambios.
     */
    public function diff(int $id): JsonResponse
    {
        $changeLog = ConfigChangeLog::with(['configuration', 'previousConfiguration'])
            ->where('device_configuration_id', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$changeLog || empty($changeLog->diff_unified)) {
            return response()->json([
                'has_diff' => false,
                'message' => 'Esta versión corresponde a la línea base inicial o no presenta diferencias registradas.'
            ]);
        }

        return response()->json([
            'has_diff' => true,
            'id' => $changeLog->id,
            'device_name' => $changeLog->configuration?->resolved_name ?? 'Dispositivo',
            'device_ip' => $changeLog->configuration?->device_ip ?? '',
            'change_type' => $changeLog->change_type,
            'badge' => $changeLog->change_type_badge,
            'diff_summary' => $changeLog->diff_summary,
            'diff_unified' => $changeLog->diff_unified,
            'lines_added' => $changeLog->lines_added,
            'lines_removed' => $changeLog->lines_removed,
            'detected_at' => $changeLog->detected_at ? $changeLog->detected_at->timezone('America/Caracas')->format('d/m/Y h:i:s A') : 'N/A',
            'previous_date' => $changeLog->previousConfiguration?->captured_at ? $changeLog->previousConfiguration->captured_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'Previa',
            'current_date' => $changeLog->configuration?->captured_at ? $changeLog->configuration->captured_at->timezone('America/Caracas')->format('d/m/Y h:i A') : 'Actual',
        ]);
    }

    /**
     * Dispara un respaldo manual para un dispositivo de red específico.
     */
    public function backupDevice(Request $request): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). La ejecución de respaldos WAN debe realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $target = $request->input('device_target');
        if (empty($target)) {
            return back()->with('error', 'Debe seleccionar un dispositivo para respaldar.');
        }

        $pythonScript = base_path('../monitor/config_backup.py');
        $pythonExec = base_path('../venv/bin/python');

        if (str_starts_with($target, 'net_')) {
            $id = (int) str_replace('net_', '', $target);
            $dev = MonitoredNetworkDevice::find($id);
            if (!$dev) {
                return back()->with('error', 'Dispositivo de red no encontrado.');
            }
            if (!$dev->hasSshCredentials()) {
                return back()->with('error', "El equipo '{$dev->name}' ({$dev->ip}) no tiene credenciales SSH configuradas. Por favor regístrelas en el módulo de Dispositivos.");
            }
            $cmd = [$pythonExec, $pythonScript, '--target-net', (string) $id, '--json'];
            $deviceName = $dev->name;
            $deviceIp = $dev->ip;
        } elseif (str_starts_with($target, 'snmp_')) {
            $id = (int) str_replace('snmp_', '', $target);
            $sdev = SnmpDevice::find($id);
            if (!$sdev) {
                return back()->with('error', 'Dispositivo SNMP no encontrado.');
            }
            if (empty($sdev->ssh_username) || empty($sdev->ssh_password_encrypted)) {
                return back()->with('error', "El equipo SNMP '{$sdev->name}' ({$sdev->ip_address}) no tiene credenciales SSH configuradas.");
            }
            $cmd = [$pythonExec, $pythonScript, '--target-snmp', (string) $id, '--json'];
            $deviceName = $sdev->name;
            $deviceIp = $sdev->ip_address;
        } else {
            return back()->with('error', 'Identificador de dispositivo no válido.');
        }

        $process = new Process($cmd);
        $process->setTimeout(60);
        $process->run();

        $output = $process->getOutput();
        $jsonStr = '';
        // Extraer payload JSON de la salida
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                $jsonStr = $line;
                break;
            }
        }

        $res = $jsonStr ? json_decode($jsonStr, true) : null;

        if ($process->isSuccessful() && $res && ($res['action'] ?? '') !== 'failed') {
            $msg = $res['message'] ?? "Respaldo y verificación diferencial ejecutada exitosamente para {$deviceName} ({$deviceIp}).";
            return back()->with('success', $msg);
        } else {
            $err = $res['error'] ?? trim($output) ?: 'Fallo en la conexión SSH con el equipo de red.';
            return back()->with('error', "Error al respaldar {$deviceName} ({$deviceIp}): {$err}");
        }
    }

    /**
     * Ejecuta el respaldo y detección de cambios de todos los equipos del inventario con credenciales SSH.
     */
    public function backupAll(): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). La ejecución masiva de respaldos debe realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $pythonScript = base_path('../monitor/config_backup.py');
        $pythonExec = base_path('../venv/bin/python');

        $process = new Process([$pythonExec, $pythonScript, '--backup-all', '--json']);
        $process->setTimeout(300);
        $process->run();

        return back()->with('success', 'Ciclo de respaldo y auditoría ejecutado para los equipos con credenciales SSH activas.');
    }

    /**
     * Elimina una versión de respaldo específica (Exclusivo Administrador).
     */
    public function destroy(int $id): RedirectResponse
    {
        $cluster = new ClusterConfigService();
        if ($cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Este servidor opera en modo ESCLAVO (Solo Lectura). La eliminación de respaldos debe realizarse en el servidor MASTER ({$cluster->getMasterApiUrl()}).");
        }

        $config = DeviceConfiguration::findOrFail($id);
        $deviceName = $config->resolved_name;
        $config->delete();

        return back()->with('success', "Versión de respaldo #{$id} de {$deviceName} eliminada del registro.");
    }
}
