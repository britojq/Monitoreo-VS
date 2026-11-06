<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

class AiConfigService
{
    /**
     * Obtiene la configuración del Asistente Virtual de IA en el Grupo de Telegram.
     */
    public function getGroupAiConfig(): array
    {
        return [
            'enabled' => (bool) BotSetting::get('telegram_group_ai_enabled', false),
            'updated_at' => BotSetting::get('telegram_group_ai_updated_at', null),
            'updated_by' => BotSetting::get('telegram_group_ai_updated_by', null),
        ];
    }

    /**
     * Indica si el asistente de IA está habilitado en el grupo de Telegram.
     */
    public function isGroupAiEnabled(): bool
    {
        return (bool) BotSetting::get('telegram_group_ai_enabled', false);
    }

    /**
     * Actualiza y persiste el estado de activación de la IA en el Grupo de Telegram.
     */
    public function updateGroupAiConfig(bool $enabled, ?string $updatedBy = null): bool
    {
        try {
            $nowStr = date('Y-m-d H:i:s');
            $updater = $updatedBy ?: 'Administrador [Web]';

            BotSetting::set('telegram_group_ai_enabled', $enabled ? '1' : '0', 'ai', 'Asistente IA en Grupo Corporativo de Telegram', 'boolean');
            BotSetting::set('telegram_group_ai_updated_at', $nowStr, 'ai', 'Fecha de Actualización de IA en Grupo', 'text');
            BotSetting::set('telegram_group_ai_updated_by', $updater, 'ai', 'Usuario que Actualizó IA en Grupo', 'text');

            return true;
        } catch (\Throwable $e) {
            Log::error('Error actualizando estado de IA en Grupo de Telegram en MariaDB: ' . $e->getMessage());
            return false;
        }
    }
}
