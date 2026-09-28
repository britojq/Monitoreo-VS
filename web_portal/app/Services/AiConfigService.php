<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AiConfigService
{
    protected string $configPath;

    public function __construct()
    {
        $this->configPath = file_exists('/scripts/telegram-admin-bot/config/config.json')
            ? '/scripts/telegram-admin-bot/config/config.json'
            : base_path('../config/config.json');
    }

    /**
     * Obtiene la configuración del Asistente Virtual Corporativo de IA en el portal web.
     */
    public function getConfig(): array
    {
        $default = [
            'enabled' => true,
            'updated_at' => null,
            'updated_by' => null,
        ];

        if (file_exists($this->configPath)) {
            try {
                $raw = @file_get_contents($this->configPath);
                $data = json_decode($raw, true) ?: [];

                // Si ai_web_enabled está explícitamente definido, se respeta; por defecto es true
                if (array_key_exists('ai_web_enabled', $data)) {
                    $default['enabled'] = (bool) $data['ai_web_enabled'];
                } else {
                    $default['enabled'] = true;
                }

                $default['updated_at'] = $data['ai_web_updated_at'] ?? null;
                $default['updated_by'] = $data['ai_web_updated_by'] ?? null;
            } catch (\Throwable $e) {
                Log::error('Error leyendo config de IA en config.json: ' . $e->getMessage());
            }
        }

        return $default;
    }

    /**
     * Indica si el asistente virtual corporativo de IA está habilitado en la web.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getConfig()['enabled'];
    }

    /**
     * Actualiza y persiste el estado del asistente corporativo de IA en config.json.
     */
    public function updateConfig(bool $enabled, ?string $updatedBy = null): bool
    {
        try {
            $data = $this->readRawConfig();
            $data['ai_web_enabled'] = $enabled;
            $data['ai_web_updated_at'] = date('Y-m-d H:i:s');
            $data['ai_web_updated_by'] = $updatedBy ?: 'Administrador [Web]';

            $this->writeRawConfig($data);
            return true;
        } catch (\Throwable $e) {
            Log::error('Error actualizando estado de IA en config.json: ' . $e->getMessage());
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
