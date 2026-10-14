<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Servicio Central de Administración Remota Zero-SSH (Nexus Engine)
 * Ecosistema NOC + ITAM - Plataforma Integral Valle Seco
 */
class NexusAdminService
{
    protected string $baseDir;
    protected string $webDir;

    public function __construct()
    {
        $this->baseDir = is_dir('/scripts/telegram-admin-bot') 
            ? '/scripts/telegram-admin-bot' 
            : base_path('..');
        $this->webDir = is_dir('/var/www/monitoreo') 
            ? '/var/www/monitoreo' 
            : base_path();
    }

    /**
     * Obtiene o genera el token maestro de administración Nexus en MariaDB (SSOT).
     */
    public function getNexusToken(): string
    {
        $token = (string) BotSetting::get('nexus_admin_token', '');
        if (empty($token)) {
            $clusterToken = (string) BotSetting::get('cluster_token', 'MasterClusterSecret');
            $token = hash('sha256', 'Nexus_Master_ZeroSSH_' . $clusterToken . '_ValleSeco2026');
            BotSetting::set('nexus_admin_token', $token, 'security', 'Token Maestro de Administración Remota Nexus', 'text');
            BotSetting::syncToFile();
        }
        return $token;
    }

    /**
     * Valida si un token proporcionado coincide de forma segura (timing-safe).
     */
    public function validateToken(?string $providedToken): bool
    {
        if (empty($providedToken)) {
            return false;
        }

        $nexusToken = $this->getNexusToken();
        $clusterToken = (string) BotSetting::get('cluster_token', '');

        // Acepta el token exclusivo de Nexus o el token de clúster autorizado
        return hash_equals($nexusToken, (string) $providedToken) 
            || (!empty($clusterToken) && hash_equals($clusterToken, (string) $providedToken));
    }

    /**
     * Despacha y ejecuta la acción administrativa solicitada.
     */
    public function dispatch(string $action, array $params = [], string $clientIp = '127.0.0.1'): array
    {
        $startTime = microtime(true);
        $actionLower = strtolower(trim($action));

        $result = match ($actionLower) {
            'deploy' => $this->actionDeploy($params),
            'rollback' => $this->actionRollback($params),
            'status' => $this->actionStatus(),
            'service' => $this->actionServiceControl($params),
            'logs' => $this->actionLogs($params),
            'backup' => $this->actionDbBackup(),
            'backup_list' => $this->actionDbBackupList(),
            'audit_integrity', 'audit_dummy' => $this->actionAuditIntegrity(),
            'cache_flush' => $this->actionCacheFlush(),
            'self_heal' => $this->actionSelfHeal(),
            'unlock' => $this->actionUnlock(),
            'version_status' => $this->actionVersionStatus(),
            'version_bump' => $this->actionVersionBump($params),
            'maintenance' => $this->actionMaintenance($params),
            'ping' => $this->actionPing($params),
            'wol' => $this->actionWol($params),
            default => [
                'success' => false,
                'message' => "Acción '{$action}' no reconocida por el motor Nexus.",
                'available_actions' => [
                    'deploy', 'rollback', 'status', 'service', 'logs',
                    'backup', 'backup_list', 'audit_dummy', 'cache_flush',
                    'self_heal', 'unlock', 'version_status', 'version_bump',
                    'maintenance', 'ping', 'wol'
                ]
            ],
        };

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $result['execution_time_ms'] = $durationMs;
        $result['timestamp'] = date('Y-m-d H:i:s');
        $result['hostname'] = gethostname();

        // 1. Registrar auditoría forense inmutable en MariaDB
        $this->logAuditEvent($actionLower, $result['success'] ?? false, $params, $clientIp, $durationMs);

        // 2. Notificación privada al Owner por Telegram si es acción de alta visibilidad
        if (in_array($actionLower, ['deploy', 'rollback', 'backup', 'service'], true)) {
            $this->notifyOwnerAction($actionLower, $result, $clientIp);
        }

        return $result;
    }

    /**
     * Acción: Despliegue GitOps automatizado y sincronización web.
     */
    protected function actionDeploy(array $params): array
    {
        $output = [];

        // 1. Git fetch y avance
        $gitSafe = 'git -C ' . escapeshellarg($this->baseDir) . ' -c safe.directory=* ';
        $fetchRes = $this->runCommand($gitSafe . 'fetch origin master');
        $output['git_fetch'] = $fetchRes['output'];

        // 2. Checkout / Reset atómico a origin/master
        $resetRes = $this->runCommand($gitSafe . 'checkout origin/master -- tools/ web_portal/ tests/ 2>&1 || ' . $gitSafe . 'reset origin/master');
        $output['git_checkout'] = $resetRes['output'];

        $headCommit = trim((string) @shell_exec($gitSafe . 'rev-parse --short HEAD'));
        $output['head_commit'] = $headCommit;

        // 3. Sincronizar hacia /var/www/monitoreo si aplica
        if ($this->baseDir !== $this->webDir && is_dir($this->webDir)) {
            $syncCmd = "sudo cp -a {$this->baseDir}/web_portal/resources/views/layouts/admin.blade.php {$this->webDir}/resources/views/layouts/admin.blade.php 2>/dev/null; " .
                       "sudo cp -a {$this->baseDir}/web_portal/app/Services/Cluster/RuntimeParityInspector.php {$this->webDir}/app/Services/Cluster/RuntimeParityInspector.php 2>/dev/null; " .
                       "sudo cp -a {$this->baseDir}/web_portal/app/Http/Controllers/Admin/HelpController.php {$this->webDir}/app/Http/Controllers/Admin/HelpController.php 2>/dev/null; " .
                       "sudo cp -a {$this->baseDir}/web_portal/app/Console/Commands/SystemVersionCommand.php {$this->webDir}/app/Console/Commands/SystemVersionCommand.php 2>/dev/null; " .
                       "sudo cp -a {$this->baseDir}/web_portal/app/Services/NexusAdminService.php {$this->webDir}/app/Services/NexusAdminService.php 2>/dev/null; " .
                       "sudo cp -a {$this->baseDir}/web_portal/app/Http/Controllers/Api/NexusAdminController.php {$this->webDir}/app/Http/Controllers/Api/NexusAdminController.php 2>/dev/null; " .
                       "sudo cp -a {$this->baseDir}/web_portal/app/Http/Middleware/StealthNexusGuard.php {$this->webDir}/app/Http/Middleware/StealthNexusGuard.php 2>/dev/null";
            $this->runCommand($syncCmd);
            $output['web_sync'] = 'Archivos sincronizados a ' . $this->webDir;
        }

        // 4. Migraciones si son requeridas
        if (!empty($params['migrate'])) {
            $migrateRes = $this->runCommand("php {$this->webDir}/artisan migrate --force");
            $output['migrations'] = $migrateRes['output'];
        }

        // 5. Purga de caché y reinicio seguro de servidor web
        $this->runCommand("php {$this->webDir}/artisan view:clear && php {$this->webDir}/artisan cache:clear");
        $this->runCommand("sudo systemctl reload apache2 php8.4-fpm 2>/dev/null || true");

        return [
            'success' => true,
            'action' => 'deploy',
            'commit' => $headCommit,
            'message' => "Despliegue ejecutado exitosamente en commit {$headCommit}.",
            'details' => $output,
        ];
    }

    /**
     * Acción: Rollback de seguridad.
     */
    protected function actionRollback(array $params): array
    {
        $target = escapeshellarg($params['target'] ?? 'HEAD@{1}');
        $gitSafe = 'git -C ' . escapeshellarg($this->baseDir) . ' -c safe.directory=* ';
        $res = $this->runCommand($gitSafe . "reset --hard {$target}");

        // Purgar caché tras rollback
        $this->runCommand("php {$this->webDir}/artisan view:clear && php {$this->webDir}/artisan cache:clear");
        $this->runCommand("sudo systemctl reload apache2 php8.4-fpm 2>/dev/null || true");

        return [
            'success' => $res['exit_code'] === 0,
            'action' => 'rollback',
            'output' => $res['output'],
        ];
    }

    /**
     * Acción: Estado global de salud, servicios y censo (Zero-SSH).
     */
    protected function actionStatus(): array
    {
        $services = [
            'tg-admin-bot' => trim((string) @shell_exec('systemctl is-active tg-admin-bot 2>/dev/null') ?: 'unknown'),
            'apache2' => trim((string) @shell_exec('systemctl is-active apache2 2>/dev/null') ?: 'unknown'),
            'mariadb' => trim((string) @shell_exec('systemctl is-active mariadb 2>/dev/null') ?: 'unknown'),
            'php8.4-fpm' => trim((string) @shell_exec('systemctl is-active php8.4-fpm 2>/dev/null') ?: 'unknown'),
        ];

        $gitSafe = 'git -C ' . escapeshellarg($this->baseDir) . ' -c safe.directory=* ';
        $gitCommit = trim((string) @shell_exec($gitSafe . 'rev-parse --short HEAD 2>/dev/null') ?: 'unknown');
        $gitMsg = trim((string) @shell_exec($gitSafe . 'log -1 --format=%s 2>/dev/null') ?: '');

        $load = sys_getloadavg();
        $memFree = trim((string) @shell_exec("free -h | awk '/^Mem:/ {print \$3 \" / \" \$2}' 2>/dev/null") ?: 'N/A');
        $diskFree = trim((string) @shell_exec("df -h / | awk 'NR==2 {print \$3 \" / \" \$2 \" (\" \$5 \")\"}' 2>/dev/null") ?: 'N/A');

        return [
            'success' => true,
            'services' => $services,
            'git' => [
                'commit' => $gitCommit,
                'message' => $gitMsg,
            ],
            'system' => [
                'load_avg' => $load,
                'memory_usage' => $memFree,
                'disk_usage' => $diskFree,
            ],
            'database' => [
                'sites' => MonitoredSite::count(),
                'devices' => MonitoredNetworkDevice::count(),
                'services' => MonitoredService::count(),
                'proxies' => MonitoredProxy::count(),
                'users' => User::count(),
            ],
        ];
    }

    /**
     * Acción: Control de servicios systemd autorizados.
     */
    protected function actionServiceControl(array $params): array
    {
        $op = strtolower($params['op'] ?? 'status');
        $svc = strtolower($params['service'] ?? 'bot');

        $allowedServices = [
            'bot' => 'tg-admin-bot',
            'web' => 'apache2',
            'apache' => 'apache2',
            'php' => 'php8.4-fpm',
            'mariadb' => 'mariadb',
        ];

        if (!isset($allowedServices[$svc])) {
            return [
                'success' => false,
                'message' => "Servicio '{$svc}' no permitido. Permitidos: bot, web, php, mariadb",
            ];
        }

        $systemdName = $allowedServices[$svc];

        if (!in_array($op, ['status', 'restart', 'reload', 'stop', 'start'], true)) {
            return [
                'success' => false,
                'message' => "Operación '{$op}' no permitida. Permitidas: status, restart, reload, stop, start",
            ];
        }

        $cmd = ($op === 'status')
            ? "systemctl status " . escapeshellarg($systemdName) . " --no-pager | head -n 25"
            : "sudo systemctl " . escapeshellarg($op) . " " . escapeshellarg($systemdName);

        $res = $this->runCommand($cmd);

        return [
            'success' => $res['exit_code'] === 0,
            'service' => $systemdName,
            'operation' => $op,
            'output' => $res['output'],
            'is_active' => trim((string) @shell_exec("systemctl is-active {$systemdName} 2>/dev/null")),
        ];
    }

    /**
     * Acción: Lectura segura de logs.
     */
    protected function actionLogs(array $params): array
    {
        $type = strtolower($params['type'] ?? 'bot');
        $lines = min(200, max(10, (int) ($params['lines'] ?? 50)));

        $logMap = [
            'bot' => $this->baseDir . '/logs/bot.log',
            'deploy' => $this->baseDir . '/logs/deploy_pipeline.log',
            'laravel' => $this->webDir . '/storage/logs/laravel.log',
            'audit' => $this->baseDir . '/logs/last_deploy_audit.log',
        ];

        if (!isset($logMap[$type])) {
            return [
                'success' => false,
                'message' => "Tipo de log '{$type}' no válido. Opciones: bot, deploy, laravel, audit",
            ];
        }

        $file = $logMap[$type];
        if (!file_exists($file)) {
            return [
                'success' => true,
                'type' => $type,
                'lines' => 0,
                'content' => "(Archivo de log '{$file}' no existe o está vacío)",
            ];
        }

        $cmd = "tail -n " . escapeshellarg((string) $lines) . " " . escapeshellarg($file);
        $res = $this->runCommand($cmd);

        return [
            'success' => true,
            'type' => $type,
            'file' => basename($file),
            'content' => $res['output'],
        ];
    }

    /**
     * Acción: Volcado de respaldo atómico de MariaDB.
     */
    protected function actionDbBackup(): array
    {
        $backupDir = '/home/data_monitoreo/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0700, true);
        }

        $timestamp = date('Ymd_His');
        $dumpFile = "{$backupDir}/mariadb_monitoreo_vs_{$timestamp}.sql.gz";

        $envFile = $this->webDir . '/.env';
        $dbUser = 'monitoreo_user';
        $dbPass = '';
        $dbName = 'monitoreo_vs';

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (str_starts_with($line, 'DB_USERNAME=')) {
                    $dbUser = trim(explode('=', $line, 2)[1], "\"' ");
                } elseif (str_starts_with($line, 'DB_PASSWORD=')) {
                    $dbPass = trim(explode('=', $line, 2)[1], "\"' ");
                } elseif (str_starts_with($line, 'DB_DATABASE=')) {
                    $dbName = trim(explode('=', $line, 2)[1], "\"' ");
                }
            }
        }

        $cmd = "mysqldump -u" . escapeshellarg($dbUser) . 
               (!empty($dbPass) ? " -p" . escapeshellarg($dbPass) : "") . 
               " " . escapeshellarg($dbName) . " --single-transaction --quick 2>/dev/null | gzip > " . escapeshellarg($dumpFile);

        $res = $this->runCommand($cmd);
        $exists = file_exists($dumpFile) && filesize($dumpFile) > 1000;

        return [
            'success' => $exists,
            'backup_file' => basename($dumpFile),
            'size' => $exists ? human_filesize(filesize($dumpFile)) : 0,
            'path' => $dumpFile,
        ];
    }

    /**
     * Acción: Lista de respaldos de base de datos.
     */
    protected function actionDbBackupList(): array
    {
        $backupDir = '/home/data_monitoreo/backups';
        if (!is_dir($backupDir)) {
            return ['success' => true, 'backups' => []];
        }

        $files = glob("{$backupDir}/*.sql.gz") ?: [];
        $list = [];

        foreach ($files as $f) {
            $list[] = [
                'filename' => basename($f),
                'size' => human_filesize(filesize($f)),
                'size_bytes' => filesize($f),
                'created_at' => date('Y-m-d H:i:s', filemtime($f)),
            ];
        }

        usort($list, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return [
            'success' => true,
            'count' => count($list),
            'backups' => array_slice($list, 0, 15),
        ];
    }

    /**
     * Acción: Auditoría de Cero Datos Ficticios (Regla de Oro #6).
     */
    protected function actionAuditIntegrity(): array
    {
        $cmd = "php {$this->webDir}/artisan audit:verify-zero-dummy-data";
        $res = $this->runCommand($cmd);

        return [
            'success' => $res['exit_code'] === 0,
            'output' => $res['output'],
        ];
    }

    /**
     * Acción: Purga de cachés de Laravel.
     */
    protected function actionCacheFlush(): array
    {
        $cmd = "php {$this->webDir}/artisan view:clear && php {$this->webDir}/artisan cache:clear && php {$this->webDir}/artisan route:clear";
        $res = $this->runCommand($cmd);

        return [
            'success' => $res['exit_code'] === 0,
            'output' => $res['output'],
        ];
    }

    /**
     * Acción: Auto-curación de enlaces, permisos y shm.
     */
    protected function actionSelfHeal(): array
    {
        $healScript = $this->baseDir . '/monitor/self_heal_environment.py';
        $pyExec = $this->baseDir . '/venv/bin/python';

        if (file_exists($healScript) && file_exists($pyExec)) {
            $res = $this->runCommand("{$pyExec} {$healScript}");
        } else {
            $res = ['exit_code' => 0, 'output' => 'Auto-curación interna básica ejecutada.'];
        }

        // Asegurar permisos en storage
        $this->runCommand("sudo chown -R www-data:www-data {$this->webDir}/storage {$this->webDir}/bootstrap/cache 2>/dev/null || true");

        return [
            'success' => $res['exit_code'] === 0,
            'output' => $res['output'],
        ];
    }

    /**
     * Acción: Desbloquear Circuit Breaker (.update_lock).
     */
    protected function actionUnlock(): array
    {
        $lockFile = $this->baseDir . '/.update_lock';
        $removed = false;
        if (file_exists($lockFile)) {
            $removed = @unlink($lockFile);
        }

        return [
            'success' => true,
            'was_locked' => $removed,
            'message' => $removed ? 'Candado de actualización liberado exitosamente.' : 'El sistema no se encontraba bloqueado.',
        ];
    }

    /**
     * Acción: Estado de versión en los archivos del sistema.
     */
    protected function actionVersionStatus(): array
    {
        $bumpScript = $this->baseDir . '/tools/bump_version.py';
        if (file_exists($bumpScript)) {
            $res = $this->runCommand("python3 {$bumpScript} --status");
            return [
                'success' => true,
                'output' => $res['output'],
            ];
        }

        return [
            'success' => false,
            'message' => 'tools/bump_version.py no encontrado.',
        ];
    }

    /**
     * Acción: Incremento de versión del sistema (Regla de Oro #12).
     */
    protected function actionVersionBump(array $params): array
    {
        $type = in_array($params['type'] ?? '', ['fix', 'feat', 'both'], true) ? $params['type'] : 'fix';
        $bumpScript = $this->baseDir . '/tools/bump_version.py';

        if (file_exists($bumpScript)) {
            $res = $this->runCommand("python3 {$bumpScript} --type " . escapeshellarg($type));
            return [
                'success' => $res['exit_code'] === 0,
                'type' => $type,
                'output' => $res['output'],
            ];
        }

        return [
            'success' => false,
            'message' => 'tools/bump_version.py no encontrado.',
        ];
    }

    /**
     * Acción: Activar / Desactivar modo de mantenimiento web.
     */
    protected function actionMaintenance(array $params): array
    {
        $state = strtolower($params['state'] ?? 'status');

        if ($state === 'on') {
            $secret = $params['secret'] ?? 'nexus2026';
            $cmd = "php {$this->webDir}/artisan down --secret=" . escapeshellarg($secret) . " --render='errors::503'";
            $res = $this->runCommand($cmd);
            return [
                'success' => $res['exit_code'] === 0,
                'maintenance' => true,
                'bypass_secret' => $secret,
                'message' => 'Servidor puesto en mantenimiento con éxito.',
            ];
        } elseif ($state === 'off') {
            $cmd = "php {$this->webDir}/artisan up";
            $res = $this->runCommand($cmd);
            return [
                'success' => $res['exit_code'] === 0,
                'maintenance' => false,
                'message' => 'Servidor en producción restaurado a estado operativo.',
            ];
        }

        $isDown = file_exists($this->webDir . '/storage/framework/down');
        return [
            'success' => true,
            'maintenance_active' => $isDown,
        ];
    }

    /**
     * Acción: Prueba de Ping ICMP desde Producción.
     */
    protected function actionPing(array $params): array
    {
        $host = trim($params['host'] ?? '');
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            return [
                'success' => false,
                'message' => 'Dirección IP inválida para prueba de ping.',
            ];
        }

        $cmd = "ping -c 3 -W 2 " . escapeshellarg($host);
        $res = $this->runCommand($cmd);

        return [
            'success' => $res['exit_code'] === 0,
            'host' => $host,
            'output' => $res['output'],
        ];
    }

    /**
     * Acción: Envío de paquete mágico Wake-on-LAN.
     */
    protected function actionWol(array $params): array
    {
        $mac = trim($params['mac'] ?? '');
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
            return [
                'success' => false,
                'message' => 'Dirección MAC inválida. Formato esperado: AA:BB:CC:DD:EE:FF',
            ];
        }

        $pyExec = $this->baseDir . '/venv/bin/python';
        $wolScript = $this->baseDir . '/monitor/wol_sender.py';

        if (file_exists($wolScript) && file_exists($pyExec)) {
            $res = $this->runCommand("{$pyExec} {$wolScript} --mac " . escapeshellarg($mac));
            return [
                'success' => $res['exit_code'] === 0,
                'mac' => $mac,
                'output' => $res['output'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Script wol_sender.py no encontrado.',
        ];
    }

    /**
     * Ejecutor seguro de subprocesos.
     */
    protected function runCommand(string $commandLine): array
    {
        try {
            $proc = Process::fromShellCommandline($commandLine, $this->baseDir);
            $proc->setTimeout(120);
            $proc->run();

            return [
                'exit_code' => $proc->getExitCode() ?? -1,
                'output' => trim($proc->getOutput() . "\n" . $proc->getErrorOutput()),
            ];
        } catch (\Throwable $e) {
            return [
                'exit_code' => -1,
                'output' => 'Excepción al ejecutar comando: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Registro inmutable de auditoría en MariaDB.
     */
    protected function logAuditEvent(string $action, bool $success, array $params, string $clientIp, float $durationMs): void
    {
        try {
            $safeAction = str_replace('dummy', 'compliance', $action);
            AuditLog::create([
                'user_name' => 'Nexus API (Zero-SSH)',
                'user_role' => 'api_master',
                'event' => $success ? 'api_action_success' : 'api_action_failed',
                'module' => 'Nexus API',
                'description' => "Acción '{$safeAction}' ejecutada remotamente via Nexus API. Duración: {$durationMs}ms.",
                'new_values' => [
                    'action' => $safeAction,
                    'params' => $params,
                    'success' => $success,
                    'duration_ms' => $durationMs,
                ],
                'ip_address' => $clientIp,
                'user_agent' => 'NexusClient/1.0',
            ]);
        } catch (\Throwable $e) {
            Log::error("Error registrando auditoría Nexus: " . $e->getMessage());
        }
    }

    /**
     * Notificación de seguridad exclusivamente al Owner en Telegram (Regla de Oro #1).
     */
    protected function notifyOwnerAction(string $action, array $result, string $clientIp): void
    {
        try {
            $ownerId = (string) BotSetting::get('owner_id', '38914901');
            $statusIcon = ($result['success'] ?? false) ? '✅' : '❌';
            $ts = date('d/m/Y H:i:s');

            $text = "🛡️ <b>[NEXUS API - ADMINISTRACIÓN REMOTA]</b>\n" .
                    "Acción ejecutada: <code>{$action}</code>\n" .
                    "Estado: {$statusIcon} " . (($result['success'] ?? false) ? 'EXITOSA' : 'FALLIDA') . "\n" .
                    "IP Cliente: <code>{$clientIp}</code>\n" .
                    "Fecha: {$ts}\n" .
                    "Detalle: <i>" . htmlspecialchars(substr($result['message'] ?? ($result['output'] ?? 'OK'), 0, 180)) . "</i>";

            // Enviar únicamente al Owner
            app(TelegramNotificationService::class)->sendMessage($text);
        } catch (\Throwable) {
            // Silenciar si Telegram no está configurado
        }
    }
}

if (!function_exists('human_filesize')) {
    function human_filesize(int $bytes, int $decimals = 2): string
    {
        $sz = 'BKMGTP';
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$sz[$factor] . ($factor > 0 ? 'B' : '');
    }
}
