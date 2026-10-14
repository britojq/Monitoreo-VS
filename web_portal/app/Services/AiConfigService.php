<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

class AiConfigService
{
    /**
     * Obtiene la configuración del Asistente Virtual Corporativo de IA en el portal web
     * directamente desde MariaDB (SSOT).
     */
    public function getConfig(): array
    {
        return [
            'enabled' => (bool) BotSetting::get('ai_web_enabled', false),
            'updated_at' => BotSetting::get('ai_web_updated_at', null),
            'updated_by' => BotSetting::get('ai_web_updated_by', null),
        ];
    }

    /**
     * Indica si el asistente virtual corporativo de IA está habilitado en la web.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getConfig()['enabled'];
    }

    /**
     * Actualiza y persiste el estado del asistente corporativo de IA en MariaDB (SSOT).
     */
    public function updateConfig(bool $enabled, ?string $updatedBy = null): bool
    {
        try {
            $nowStr = date('Y-m-d H:i:s');
            $updater = $updatedBy ?: 'Administrador [Web]';

            BotSetting::set('ai_web_enabled', $enabled, 'ai', 'Asistente Corporativo de IA en Portal Web', 'boolean');
            BotSetting::set('ai_web_updated_at', $nowStr, 'ai', 'Fecha de Actualización de IA Web', 'text');
            BotSetting::set('ai_web_updated_by', $updater, 'ai', 'Usuario que Actualizó IA Web', 'text');

            // Exportación unidireccional para demonios en Python
            BotSetting::syncToFile();

            return true;
        } catch (\Throwable $e) {
            Log::error('Error actualizando estado de IA en MariaDB: ' . $e->getMessage());
            return false;
        }
    }
}
