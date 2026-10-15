<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

readonly class AddCronScheduleAction
{
    public function execute(string $newTime, string $adminName): array
    {
        $nowStr = date('Y-m-d H:i:s');
        try {
            $schedules = BotSetting::get('cron_schedules', ['07:30', '16:00']);
            if (!is_array($schedules)) {
                $schedules = ['07:30', '16:00'];
            }

            if (!in_array($newTime, $schedules, true)) {
                $schedules[] = $newTime;
                sort($schedules);
                $cleanSchedules = array_values(array_unique($schedules));

                BotSetting::set('cron_schedules', $cleanSchedules, 'cron', 'Horarios de Envío de Reportes', 'json');
                BotSetting::set('cron_reports_updated_at', $nowStr, 'cron', 'Última Actualización de Cron', 'text');
                BotSetting::set('cron_reports_updated_by', $adminName, 'cron', 'Usuario que Actualizó Cron', 'text');

                BotSetting::syncToFile();

                return ['success' => true, 'type' => 'success', 'message' => "Horario {$newTime} agregado exitosamente a la programación."];
            }

            return ['success' => true, 'type' => 'info', 'message' => "El horario {$newTime} ya se encuentra programado."];
        } catch (\Throwable $e) {
            Log::error('Error añadiendo horario cron en MariaDB: ' . $e->getMessage());
            return ['success' => false, 'type' => 'error', 'message' => 'Error al guardar el nuevo horario.'];
        }
    }
}
