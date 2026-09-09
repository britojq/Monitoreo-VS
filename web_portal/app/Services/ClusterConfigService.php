<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ClusterConfigService
{
    protected string $configPath = '/scripts/telegram-admin-bot/config/config.json';

    public function getConfig(): array
    {
        $default = [
            'node_role' => 'master',
            'cluster_token' => '',
            'master_api_url' => 'http://10.20.23.252',
            'cluster_last_sync_at' => null,
            'cluster_last_sync_status' => 'standalone',
            'slave_sync_interval_minutes' => 2,
        ];

        if (file_exists($this->configPath)) {
            try {
                $raw = @file_get_contents($this->configPath);
                $data = json_decode($raw, true) ?: [];

                $default['node_role'] = strtolower($data['node_role'] ?? 'master') === 'slave' ? 'slave' : 'master';
                $default['cluster_token'] = $data['cluster_token'] ?? '';
                $default['master_api_url'] = rtrim($data['master_api_url'] ?? 'http://10.20.23.252', '/');
                $default['cluster_last_sync_at'] = $data['cluster_last_sync_at'] ?? null;
                $default['cluster_last_sync_status'] = $data['cluster_last_sync_status'] ?? ($default['node_role'] === 'master' ? 'master_active' : 'pending');
                $default['slave_sync_interval_minutes'] = max(1, min(1440, (int)($data['slave_sync_interval_minutes'] ?? 2)));

                // Generar token por defecto si está vacío
                if (empty($default['cluster_token'])) {
                    $default['cluster_token'] = hash('sha256', 'ValleSeco_Cluster_' . ($data['bot_token'] ?? 'SecretKey2026'));
                    $data['cluster_token'] = $default['cluster_token'];
                    $data['node_role'] = $default['node_role'];
                    $this->writeRawConfig($data);
                }
            } catch (\Throwable $e) {
                Log::error('Error leyendo config de cluster: ' . $e->getMessage());
            }
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

    public function getClusterToken(): string
    {
        return $this->getConfig()['cluster_token'];
    }

    public function getMasterApiUrl(): string
    {
        return $this->getConfig()['master_api_url'];
    }

    public function updateConfig(array $attributes): bool
    {
        try {
            $data = $this->readRawConfig();

            if (isset($attributes['node_role'])) {
                $role = strtolower(trim($attributes['node_role']));
                $data['node_role'] = ($role === 'slave') ? 'slave' : 'master';
            }

            if (isset($attributes['cluster_token']) && !empty(trim($attributes['cluster_token']))) {
                $data['cluster_token'] = trim($attributes['cluster_token']);
            }

            if (isset($attributes['master_api_url']) && !empty(trim($attributes['master_api_url']))) {
                $data['master_api_url'] = rtrim(trim($attributes['master_api_url']), '/');
            }

            if (isset($attributes['cluster_last_sync_at'])) {
                $data['cluster_last_sync_at'] = $attributes['cluster_last_sync_at'];
            }

            if (isset($attributes['cluster_last_sync_status'])) {
                $data['cluster_last_sync_status'] = $attributes['cluster_last_sync_status'];
            }

            if (isset($attributes['slave_sync_interval_minutes'])) {
                $data['slave_sync_interval_minutes'] = max(1, min(1440, (int)$attributes['slave_sync_interval_minutes']));
            }

            $data['cluster_updated_at'] = date('Y-m-d H:i:s');

            $this->writeRawConfig($data);
            return true;
        } catch (\Throwable $e) {
            Log::error('Error actualizando config de cluster: ' . $e->getMessage());
            return false;
        }
    }

    protected function readRawConfig(): array
    {
        if (!file_exists($this->configPath)) {
            return [];
        }
        $raw = @file_get_contents($this->configPath);
        return json_decode($raw, true) ?: [];
    }

    protected function writeRawConfig(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($this->configPath, $json . "\n");
    }
}
