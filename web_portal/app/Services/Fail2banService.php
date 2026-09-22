<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class Fail2banService
{
    protected const BINARY = '/usr/bin/fail2ban-client';

    protected const WHITELIST_IPS = [
        '127.0.0.1',
        '::1',
        '10.20.23.221',
        '10.20.23.252',
        '10.20.23.1',
    ];

    /**
     * Obtiene el estado general del demonio Fail2ban y la lista de jails activas.
     */
    public static function getStatus(): array
    {
        try {
            $result = Process::run(['sudo', self::BINARY, 'status']);
            if (!$result->successful()) {
                return [
                    'is_running' => false,
                    'error' => $result->errorOutput() ?: 'El servicio Fail2ban no responde.',
                    'jail_count' => 0,
                    'jails' => [],
                ];
            }

            $output = $result->output();
            $jails = [];
            $jailCount = 0;

            if (preg_match('/Number of jail:\s+(\d+)/i', $output, $m)) {
                $jailCount = (int) $m[1];
            }

            if (preg_match('/Jail list:\s+(.*)/i', $output, $m)) {
                $rawList = trim($m[1]);
                if (!empty($rawList)) {
                    $jails = array_map('trim', explode(',', $rawList));
                }
            }

            return [
                'is_running' => true,
                'jail_count' => $jailCount,
                'jails' => $jails,
            ];
        } catch (\Throwable $e) {
            Log::error("Fail2banService::getStatus error: {$e->getMessage()}");
            return [
                'is_running' => false,
                'error' => $e->getMessage(),
                'jail_count' => 0,
                'jails' => [],
            ];
        }
    }

    /**
     * Obtiene los detalles de una Jail específica.
     */
    public static function getJailStatus(string $jail): array
    {
        $default = [
            'name' => $jail,
            'label' => self::getJailLabel($jail),
            'icon' => self::getJailIcon($jail),
            'currently_failed' => 0,
            'total_failed' => 0,
            'currently_banned' => 0,
            'total_banned' => 0,
            'banned_ips' => [],
        ];

        try {
            $result = Process::run(['sudo', self::BINARY, 'status', $jail]);
            if (!$result->successful()) {
                return $default;
            }

            $output = $result->output();

            if (preg_match('/Currently failed:\s+(\d+)/i', $output, $m)) {
                $default['currently_failed'] = (int) $m[1];
            }
            if (preg_match('/Total failed:\s+(\d+)/i', $output, $m)) {
                $default['total_failed'] = (int) $m[1];
            }
            if (preg_match('/Currently banned:\s+(\d+)/i', $output, $m)) {
                $default['currently_banned'] = (int) $m[1];
            }
            if (preg_match('/Total banned:\s+(\d+)/i', $output, $m)) {
                $default['total_banned'] = (int) $m[1];
            }
            if (preg_match('/Banned IP list:\s*(.*)/i', $output, $m)) {
                $rawIps = trim($m[1]);
                if (!empty($rawIps)) {
                    $default['banned_ips'] = array_values(array_filter(array_map('trim', preg_split('/\s+/', $rawIps))));
                }
            }

            return $default;
        } catch (\Throwable $e) {
            Log::error("Fail2banService::getJailStatus({$jail}) error: {$e->getMessage()}");
            return $default;
        }
    }

    /**
     * Obtiene el listado consolidado de todas las jails y sus IPs bloqueadas.
     */
    public static function getAllJailsDetails(): array
    {
        $status = self::getStatus();
        if (!$status['is_running'] || empty($status['jails'])) {
            return [];
        }

        $details = [];
        foreach ($status['jails'] as $jail) {
            $details[$jail] = self::getJailStatus($jail);
        }

        return $details;
    }

    /**
     * Desbloquea una IP de una Jail específica o de todas las jails.
     */
    public static function unbanIp(string $ip, string $jail = 'all'): array
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => 'Dirección IP inválida.'];
        }

        try {
            if ($jail === 'all') {
                $status = self::getStatus();
                $unbannedCount = 0;
                foreach ($status['jails'] as $j) {
                    $res = Process::run(['sudo', self::BINARY, 'set', $j, 'unbanip', $ip]);
                    if ($res->successful()) {
                        $unbannedCount++;
                    }
                }
                return [
                    'success' => true,
                    'message' => "IP {$ip} desbloqueada en {$unbannedCount} servicio(s) de Fail2ban.",
                ];
            }

            $result = Process::run(['sudo', self::BINARY, 'set', $jail, 'unbanip', $ip]);
            if ($result->successful()) {
                return [
                    'success' => true,
                    'message' => "IP {$ip} desbloqueada exitosamente en la jail {$jail}.",
                ];
            }

            return [
                'success' => false,
                'message' => "No se pudo desbloquear la IP {$ip}: " . ($result->errorOutput() ?: $result->output()),
            ];
        } catch (\Throwable $e) {
            Log::error("Fail2banService::unbanIp error: {$e->getMessage()}");
            return ['success' => false, 'message' => "Excepción al desbloquear IP: {$e->getMessage()}"];
        }
    }

    /**
     * Bloquea manualmente una IP en una Jail específica.
     */
    public static function banIp(string $ip, string $jail = 'sshd'): array
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => 'Dirección IP inválida.'];
        }

        // Verificación de protección contra bloqueo de IPs vitales
        if (in_array($ip, self::WHITELIST_IPS, true) || str_starts_with($ip, '127.') || str_starts_with($ip, '10.20.23.')) {
            return ['success' => false, 'message' => 'Acción denegada: La IP pertenece a la red corporativa o al cluster de administración.'];
        }

        try {
            if ($jail === 'all') {
                $status = self::getStatus();
                $bannedCount = 0;
                foreach ($status['jails'] as $j) {
                    $res = Process::run(['sudo', self::BINARY, 'set', $j, 'banip', $ip]);
                    if ($res->successful()) {
                        $bannedCount++;
                    }
                }
                return [
                    'success' => true,
                    'message' => "IP {$ip} bloqueada en {$bannedCount} jail(s) de Fail2ban.",
                ];
            }

            $result = Process::run(['sudo', self::BINARY, 'set', $jail, 'banip', $ip]);
            if ($result->successful()) {
                return [
                    'success' => true,
                    'message' => "IP {$ip} bloqueada exitosamente en el firewall bajo la jail {$jail}.",
                ];
            }

            return [
                'success' => false,
                'message' => "No se pudo bloquear la IP {$ip}: " . ($result->errorOutput() ?: $result->output()),
            ];
        } catch (\Throwable $e) {
            Log::error("Fail2banService::banIp error: {$e->getMessage()}");
            return ['success' => false, 'message' => "Excepción al bloquear IP: {$e->getMessage()}"];
        }
    }

    /**
     * Recarga las reglas y configuración de Fail2ban.
     */
    public static function reload(): array
    {
        try {
            $result = Process::run(['sudo', self::BINARY, 'reload']);
            if ($result->successful()) {
                return ['success' => true, 'message' => 'Reglas y configuración de Fail2ban recargadas exitosamente.'];
            }
            return ['success' => false, 'message' => 'Error recargando Fail2ban: ' . $result->errorOutput()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function getJailLabel(string $jail): string
    {
        return match ($jail) {
            'sshd' => 'SSH Server (Puerto 22)',
            'apache-auth' => 'Apache Autenticación',
            'apache-badbots' => 'Rastreadores y Bad Bots',
            'apache-botsearch' => 'Escaneo de Vulnerabilidades',
            'apache-noscript' => 'Filtro No-Script Web',
            'vnc-bruteforce' => 'Servidor VNC (Puerto 5900)',
            default => strtoupper($jail),
        };
    }

    public static function getJailIcon(string $jail): string
    {
        return match ($jail) {
            'sshd' => 'terminal',
            'apache-auth' => 'lock',
            'apache-badbots' => 'bug_report',
            'apache-botsearch' => 'policy',
            'apache-noscript' => 'code_off',
            'vnc-bruteforce' => 'desktop_windows',
            default => 'security',
        };
    }
}
