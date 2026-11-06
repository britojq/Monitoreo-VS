<?php

declare(strict_types=1);

namespace App\Actions\Snmp;

use App\Contracts\Services\ClusterConfigInterface;
use App\Models\AuditLog;
use App\Models\SnmpDevice;
use App\Services\System\SonarPathService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;

readonly class ActivateRemoteSnmpAction
{
    public function __construct(
        private ClusterConfigInterface $cluster
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

        $userId = Auth::id() ?: 1;
        $python = SonarPathService::pythonBinary();
        $script = SonarPathService::monitorScript('snmp_activator.py');

        // SEC-15: Erradicación de contraseñas y secretos en /proc/<pid>/cmdline pasando credenciales por stdin
        $cmd = [
            $python,
            $script,
            '--ip', (string) $validated['ip_address'],
            '--method', (string) $validated['method'],
            '--user', (string) $validated['username'],
            '--user-id', (string) $userId,
            '--stdin-auth',
        ];

        if (!empty($validated['port'])) {
            $cmd[] = '--port';
            $cmd[] = (string) $validated['port'];
        }

        $authPayload = json_encode([
            'password' => (string) ($validated['password'] ?? ''),
            'secret' => (string) ($validated['secret'] ?? ''),
            'community' => (string) ($validated['community'] ?? 'public'),
        ], JSON_THROW_ON_ERROR);

        $processResult = Process::timeout(45)
            ->input($authPayload)
            ->run($cmd);

        $rawOutput = trim($processResult->output());
        $outputStr = $rawOutput !== '' ? $rawOutput : trim($processResult->errorOutput());
        $returnVar = $processResult->exitCode();

        $resultJson = json_decode($rawOutput, true);
        if (!is_array($resultJson) && preg_match('/\{[\s\S]*\}/', $rawOutput, $matches)) {
            $resultJson = json_decode($matches[0], true);
        }

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
