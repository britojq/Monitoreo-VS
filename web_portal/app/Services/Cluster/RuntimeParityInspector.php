<?php

declare(strict_types=1);

namespace App\Services\Cluster;

use App\Services\System\SonarPathService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

class RuntimeParityInspector
{
    /**
     * Inspecciona todos los parámetros de entorno y paridad del nodo.
     *
     * @return array
     */
    public function inspect(): array
    {
        return [
            'platform' => $this->getPlatformInfo(),
            'git' => $this->getGitState(),
            'database_census' => $this->getDatabaseCensus(),
            'migrations' => $this->getMigrationsState(),
            'system_services' => $this->getSystemServices(),
            'port_listeners' => $this->getPortListeners(),
            'resources' => $this->getSystemResources(),
        ];
    }

    /**
     * Retorna información de la plataforma y versiones de software.
     */
    protected function getPlatformInfo(): array
    {
        $pythonVersion = 'unknown';
        $pyVenv = SonarPathService::pythonBinary();
        if (file_exists($pyVenv) && is_executable($pyVenv)) {
            $pyProc = Process::run([$pyVenv, '--version']);
            $pythonVersion = $pyProc->successful() ? trim($pyProc->output()) : 'unknown';
        }

        $mariadbVersion = 'unknown';
        try {
            $rawVer = DB::selectOne('SELECT VERSION() as v');
            $mariadbVersion = $rawVer ? $rawVer->v : 'unknown';
        } catch (\Throwable) {
            // Ignorar si falla la consulta
        }

        return [
            'system_version' => 'v5.52.80',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'python_version' => $pythonVersion,
            'mariadb_version' => $mariadbVersion,
            'hostname' => gethostname(),
            'server_ip' => request()->server('SERVER_ADDR') ?: gethostbyname(gethostname()),
            'timezone' => config('app.timezone'),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Inspecciona el estado de Git en el repositorio.
     */
    protected function getGitState(): array
    {
        $baseDir = SonarPathService::baseDir();
        $gitSafe = 'git -C ' . escapeshellarg($baseDir) . ' -c safe.directory=* ';

        $commitShort = trim(@shell_exec($gitSafe . 'rev-parse --short HEAD 2>/dev/null') ?: 'unknown');
        $commitFull = trim(@shell_exec($gitSafe . 'rev-parse HEAD 2>/dev/null') ?: 'unknown');
        $branch = trim(@shell_exec($gitSafe . 'rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown');
        $author = trim(@shell_exec($gitSafe . 'log -1 --format=%an 2>/dev/null') ?: 'unknown');
        $date = trim(@shell_exec($gitSafe . 'log -1 --format=%cd --date=iso 2>/dev/null') ?: 'unknown');
        $message = trim(@shell_exec($gitSafe . 'log -1 --format=%s 2>/dev/null') ?: '');

        $porcelain = trim(@shell_exec($gitSafe . 'status --porcelain 2>/dev/null') ?: '');
        $isClean = empty($porcelain);
        $modifiedFiles = [];
        if (!empty($porcelain)) {
            $lines = explode("\n", $porcelain);
            $modifiedFiles = array_slice(array_map('trim', $lines), 0, 30);
        }

        return [
            'commit_short' => $commitShort,
            'commit_full' => $commitFull,
            'branch' => $branch,
            'author' => $author,
            'commit_date' => $date,
            'commit_message' => $message,
            'is_clean' => $isClean,
            'uncommitted_files_count' => count($modifiedFiles),
            'uncommitted_files_preview' => $modifiedFiles,
        ];
    }

    /**
     * Censo exhaustivo de registros por tabla para cotejo de paridad.
     */
    protected function getDatabaseCensus(): array
    {
        $tables = [
            'inventory_assets',
            'inventory_peripherals',
            'inventory_assignments',
            'inventory_components',
            'monitored_services',
            'monitored_sites',
            'monitored_network_devices',
            'monitored_proxies',
            'snmp_devices',
            'ups_devices',
            'discovery_subnets',
            'discovered_devices',
            'alert_rules',
            'alerts',
            'users',
            'audit_logs',
            'network_topology_links',
            'wol_devices',
        ];

        $census = [];
        foreach ($tables as $tbl) {
            try {
                if (Schema::hasTable($tbl)) {
                    $census[$tbl] = DB::table($tbl)->count();
                } else {
                    $census[$tbl] = null;
                }
            } catch (\Throwable) {
                $census[$tbl] = null;
            }
        }

        return $census;
    }

    /**
     * Inspecciona las migraciones aplicadas.
     */
    protected function getMigrationsState(): array
    {
        $appliedCount = 0;
        $lastBatch = 0;
        $lastMigration = 'none';

        try {
            if (Schema::hasTable('migrations')) {
                $appliedCount = DB::table('migrations')->count();
                $last = DB::table('migrations')->orderBy('id', 'desc')->first();
                if ($last) {
                    $lastBatch = $last->batch;
                    $lastMigration = $last->migration;
                }
            }
        } catch (\Throwable) {
            // Ignorar
        }

        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
        $filesCount = count($migrationFiles);

        return [
            'applied_count' => $appliedCount,
            'files_count' => $filesCount,
            'last_batch' => $lastBatch,
            'last_migration' => $lastMigration,
            'is_synchronized' => ($appliedCount >= $filesCount),
        ];
    }

    /**
     * Inspecciona el estado de los servicios del sistema (systemd).
     */
    protected function getSystemServices(): array
    {
        $services = [
            'tg-admin-bot' => $this->checkSystemdService('tg-admin-bot'),
            'apache2' => $this->checkSystemdService('apache2'),
            'mariadb' => $this->checkSystemdService('mariadb'),
            'php-fpm' => $this->checkPhpFpmService(),
            'glpi-agent' => $this->checkSystemdService('glpi-agent'),
        ];

        $allCoreActive = ($services['apache2'] === 'active' && $services['mariadb'] === 'active');

        return [
            'services' => $services,
            'core_active' => $allCoreActive,
        ];
    }

    /**
     * Verifica el estado de un servicio systemd individual.
     */
    protected function checkSystemdService(string $serviceName): string
    {
        $out = trim(@shell_exec('systemctl is-active ' . escapeshellarg($serviceName) . ' 2>/dev/null') ?: '');
        return !empty($out) ? $out : 'unknown';
    }

    /**
     * Detecta la versión activa de PHP-FPM y verifica su estado.
     */
    protected function checkPhpFpmService(): string
    {
        $candidates = ['php8.4-fpm', 'php8.3-fpm', 'php8.2-fpm', 'php-fpm'];
        foreach ($candidates as $cand) {
            $status = $this->checkSystemdService($cand);
            if ($status === 'active') {
                return "$cand (active)";
            }
        }
        return 'inactive';
    }

    /**
     * Inspecciona puertos de red críticos en escucha local.
     */
    protected function getPortListeners(): array
    {
        $ports = [
            80 => 'HTTP / Web Portal',
            443 => 'HTTPS / SSL Web Portal',
            3306 => 'MariaDB Database',
            162 => 'SNMP Trap Listener (UDP)',
            514 => 'Syslog Receiver (UDP)',
            2055 => 'NetFlow Collector (UDP)',
        ];

        $results = [];
        foreach ($ports as $port => $label) {
            $isListening = $this->isLocalPortListening($port);
            $results[$port] = [
                'port' => $port,
                'label' => $label,
                'is_listening' => $isListening,
            ];
        }

        return $results;
    }

    /**
     * Comprueba si un puerto está en escucha localmente mediante socket TCP o ss estructurado (F-03).
     */
    protected function isLocalPortListening(int $port): bool
    {
        // Validación estricta de rango de puerto (1-65535) contra inyección
        if ($port < 1 || $port > 65535) {
            return false;
        }

        // Para puertos TCP rápidos:
        if (in_array($port, [80, 443, 3306], true)) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($fp) {
                fclose($fp);
                return true;
            }
        }

        // Inspección segura sin shell mediante Symfony Process (F-03 / CWE-78)
        try {
            $process = Process::run(['ss', '-tuln']);
            if ($process->successful()) {
                return str_contains($process->output(), ":{$port} ");
            }
        } catch (\Throwable) {
            // Degradación silenciosa en entornos con permisos restringidos
        }

        return false;
    }

    /**
     * Obtiene estadísticas de recursos del sistema.
     */
    protected function getSystemResources(): array
    {
        $rootTotal = @disk_total_space('/') ?: 0;
        $rootFree = @disk_free_space('/') ?: 0;
        $rootUsed = max(0, $rootTotal - $rootFree);

        $load = sys_getloadavg() ?: [0, 0, 0];

        // Memoria desde /proc/meminfo
        $memTotal = 0;
        $memFree = 0;
        $memAvail = 0;
        if (is_readable('/proc/meminfo')) {
            $lines = @file('/proc/meminfo');
            foreach ($lines as $line) {
                if (preg_match('/^MemTotal:\s+(\d+)\s+kB/', $line, $m)) $memTotal = (int)$m[1] * 1024;
                if (preg_match('/^MemFree:\s+(\d+)\s+kB/', $line, $m)) $memFree = (int)$m[1] * 1024;
                if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/', $line, $m)) $memAvail = (int)$m[1] * 1024;
            }
        }

        return [
            'disk_root' => [
                'total_bytes' => $rootTotal,
                'free_bytes' => $rootFree,
                'used_bytes' => $rootUsed,
                'used_percent' => $rootTotal > 0 ? round(($rootUsed / $rootTotal) * 100, 1) : 0,
            ],
            'memory' => [
                'total_bytes' => $memTotal,
                'available_bytes' => $memAvail,
                'used_bytes' => max(0, $memTotal - $memAvail),
                'used_percent' => $memTotal > 0 ? round((($memTotal - $memAvail) / $memTotal) * 100, 1) : 0,
            ],
            'load_averages' => [
                '1min' => $load[0] ?? 0,
                '5min' => $load[1] ?? 0,
                '15min' => $load[2] ?? 0,
            ],
        ];
    }
}
