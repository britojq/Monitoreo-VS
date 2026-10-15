<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

readonly class UpdateWebCheckIntervalAction
{
    public function execute(int $interval, string $adminName): array
    {
        $nowStr = date('Y-m-d H:i:s');
        try {
            BotSetting::set('web_check_interval_minutes', $interval, 'cron', 'Intervalo de Sondeo Web (minutos)', 'integer');
            BotSetting::set('web_check_interval_updated_at', $nowStr, 'cron', 'Última Actualización de Intervalo Web', 'text');
            BotSetting::set('web_check_interval_updated_by', $adminName, 'cron', 'Usuario que Actualizó Intervalo Web', 'text');

            BotSetting::syncToFile();

            return ['success' => true, 'message' => "Frecuencia de monitoreo y actualización web configurada a {$interval} minutos exitosamente."];
        } catch (\Throwable $e) {
            Log::error('Error actualizando intervalo de monitoreo web en MariaDB: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el nuevo intervalo de monitoreo web.'];
        }
    }
}
