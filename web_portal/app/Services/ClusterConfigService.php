<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

class ClusterConfigService
{
    /**
     * Obtiene la configuración de cluster directamente desde MariaDB (SSOT).
     */
    public function getConfig(): array
    {
        $role = strtolower(trim((string) BotSetting::get('cluster_node_role', 'master')));
        $validRole = in_array($role, ['master', 'slave', 'dev'], true) ? $role : 'master';
        $default = [
            'node_role' => $validRole,
            'cluster_token' => (string) BotSetting::get('cluster_token', ''),
            'master_api_url' => rtrim((string) BotSetting::get('cluster_master_api_url', 'http://10.20.23.252'), '/'),
            'cluster_last_sync_at' => BotSetting::get('cluster_last_sync_at', null),
            'cluster_last_sync_status' => (string) BotSetting::get('cluster_last_sync_status', 'master_active'),
            'slave_sync_interval_minutes' => max(1, min(1440, (int) BotSetting::get('cluster_slave_sync_interval_minutes', 2))),
        ];

        // Generar token por defecto si está vacío
        if (empty($default['cluster_token'])) {
            $botToken = (string) BotSetting::get('bot_token', 'SecretKey2026');
            $default['cluster_token'] = hash('sha256', 'ValleSeco_Cluster_' . $botToken);
            BotSetting::set('cluster_token', $default['cluster_token'], 'cluster', 'Token de Autenticación de Cluster', 'text');
            BotSetting::set('cluster_node_role', $default['node_role'], 'cluster', 'Rol del Nodo en Cluster', 'text');
            BotSetting::syncToFile();
        }

        return $default;
    }

    public function getNodeRole(): string
    {
        return $this->getConfig()['node_role'];
    }

    public function isMaster(): bool
    {
        return $this->getNodeRole() === 'master';
    }

    public function isSlave(): bool
    {
        return $this->getNodeRole() === 'slave';
    }

    public function isDev(): bool
    {
        return $this->getNodeRole() === 'dev';
    }

    public function getClusterToken(): string
    {
        return $this->getConfig()['cluster_token'];
    }

    public function getMasterApiUrl(): string
    {
        return $this->getConfig()['master_api_url'];
    }

    /**
     * Actualiza y persiste los parámetros del cluster en MariaDB (SSOT).
     */
    public function updateConfig(array $attributes): bool
    {
        try {
            if (isset($attributes['node_role'])) {
                $role = strtolower(trim((string) $attributes['node_role']));
                $validRole = in_array($role, ['master', 'slave', 'dev'], true) ? $role : 'master';
                BotSetting::set('cluster_node_role', $validRole, 'cluster', 'Rol del Nodo en Cluster', 'text');
            }

            if (isset($attributes['cluster_token']) && !empty(trim((string) $attributes['cluster_token']))) {
                BotSetting::set('cluster_token', trim((string) $attributes['cluster_token']), 'cluster', 'Token de Autenticación de Cluster', 'text');
            }

            if (isset($attributes['master_api_url']) && !empty(trim((string) $attributes['master_api_url']))) {
                BotSetting::set('cluster_master_api_url', rtrim(trim((string) $attributes['master_api_url']), '/'), 'cluster', 'URL API del Nodo Maestro', 'text');
            }

            if (isset($attributes['cluster_last_sync_at'])) {
                BotSetting::set('cluster_last_sync_at', (string) $attributes['cluster_last_sync_at'], 'cluster', 'Última Sincronización de Cluster', 'text');
            }

            if (isset($attributes['cluster_last_sync_status'])) {
                BotSetting::set('cluster_last_sync_status', (string) $attributes['cluster_last_sync_status'], 'cluster', 'Estado de Sincronización del Cluster', 'text');
            }

            if (isset($attributes['slave_sync_interval_minutes'])) {
                $interval = max(1, min(1440, (int) $attributes['slave_sync_interval_minutes']));
                BotSetting::set('cluster_slave_sync_interval_minutes', $interval, 'cluster', 'Intervalo de Sincronización de Esclavo (minutos)', 'integer');
            }

            BotSetting::set('cluster_updated_at', date('Y-m-d H:i:s'), 'cluster', 'Fecha de Actualización del Cluster', 'text');

            // Exportación unidireccional automática a config.json para demonios en Python
            BotSetting::syncToFile();

            return true;
        } catch (\Throwable $e) {
            Log::error('Error actualizando config de cluster en MariaDB: ' . $e->getMessage());
            return false;
        }
    }
}
