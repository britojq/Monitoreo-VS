<?php

declare(strict_types=1);

namespace App\Actions\Snmp;

use App\Models\AuditLog;
use App\Models\SnmpDevice;
use App\Services\ClusterConfigService;
use Illuminate\Support\Facades\Auth;

readonly class ActivateRemoteSnmpAction
{
    public function __construct(
        private ClusterConfigService $cluster
    ) {}

    public function execute(array $validated, ?string $ip, ?string $userAgent): array
    {
        if ($this->cluster->isSlave()) {
            return [
                'status' => 'failed',
                'status_code' => 403,
                'error' => "Acción Bloqueada: Servidor en modo ESCLAVO. La activación remota debe ejecutarse desde el Master ({$this->cluster->getMasterApiUrl()}).",
            ];
        }

        $targetIp = escapeshellarg((string) $validated['ip_address']);
        $method = escapeshellarg((string) $validated['method']);
        $user = escapeshellarg((string) $validated['username']);
        $pass = escapeshellarg((string) $validated['password']);
        $comm = escapeshellarg((string) $validated['community']);
        $userId = Auth::id() ?: 1;

        $cmd = "/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/snmp_activator.py"
             . " --ip {$targetIp} --method {$method} --user {$user} --password {$pass} --community {$comm} --user-id {$userId}";

        if (!empty($validated['secret'])) {
            $cmd .= ' --secret ' . escapeshellarg((string) $validated['secret']);
        }
        if (!empty($validated['port'])) {
            $cmd .= ' --port ' . (int) $validated['port'];
        }

        exec($cmd . ' 2>&1', $outputLines, $returnVar);
        $outputStr = implode("\n", $outputLines);

        $resultJson = json_decode($outputStr, true);
        $status = $resultJson['status'] ?? ($returnVar === 0 ? 'success' : 'failed');
        $error = $resultJson['error'] ?? null;

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'user_email' => Auth::user()?->email ?? 'system@local',
            'user_role' => Auth::user()?->role ?? 'admin',
            'event' => 'remote_activation',
            'module' => 'snmp',
            'auditable_type' => SnmpDevice::class,
            'auditable_id' => null,
            'entity_name' => (string) $validated['ip_address'],
            'entity_label' => "Activación Remota ({$validated['method']})",
            'description' => "Intento de activación remota SNMP ({$validated['method']}) en {$validated['ip_address']}: {$status}",
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        if ($status === 'success') {
            return [
                'status' => 'success',
                'status_code' => 200,
                'message' => "SNMP activado correctamente en {$validated['ip_address']}.",
                'output' => $resultJson['output'] ?? $outputStr,
            ];
        }

        return [
            'status' => $status,
            'status_code' => 422,
            'message' => 'Fallo al activar SNMP: ' . ($error ?: 'Error de comunicación'),
            'error' => $error,
            'output' => $outputStr,
        ];
    }
}
