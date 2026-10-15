<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

readonly class ToggleCronAction
{
    public function execute(string $adminName): array
    {
        try {
            $currentState = (bool) BotSetting::get('cron_reports_enabled', false);
            $newState = !$currentState;
            $nowStr = date('Y-m-d H:i:s');

            BotSetting::set('cron_reports_enabled', $newState, 'cron', 'Envíos Automáticos de Reportes Programados', 'boolean');
            BotSetting::set('cron_reports_updated_at', $nowStr, 'cron', 'Última Actualización de Cron', 'text');
            BotSetting::set('cron_reports_updated_by', $adminName, 'cron', 'Usuario que Actualizó Cron', 'text');

            BotSetting::syncToFile();

            $msg = $newState
                ? 'Envíos programados por cron ACTIVADOS correctamente en la base de datos central.'
                : 'Envíos programados por cron PAUSADOS correctamente en la base de datos central.';

            return ['success' => true, 'message' => $msg];
        } catch (\Throwable $e) {
            Log::error('Error alternando estado de cron en MariaDB: ' . $e->getMessage());
            return ['success' => false, 'message' => 'No fue posible actualizar el estado de los envíos programados.'];
        }
    }
}
