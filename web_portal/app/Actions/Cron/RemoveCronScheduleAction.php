<?php

declare(strict_types=1);

namespace App\Actions\Cron;

use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

readonly class RemoveCronScheduleAction
{
    public function execute(string $timeToRemove, string $adminName): array
    {
        $nowStr = date('Y-m-d H:i:s');
        try {
            $schedules = BotSetting::get('cron_schedules', ['07:30', '16:00']);
            if (!is_array($schedules)) {
                $schedules = ['07:30', '16:00'];
            }

            $key = array_search($timeToRemove, $schedules, true);
            if ($key !== false) {
                unset($schedules[$key]);
                sort($schedules);
                $cleanSchedules = array_values($schedules);

                BotSetting::set('cron_schedules', $cleanSchedules, 'cron', 'Horarios de Envío de Reportes', 'json');
                BotSetting::set('cron_reports_updated_at', $nowStr, 'cron', 'Última Actualización de Cron', 'text');
                BotSetting::set('cron_reports_updated_by', $adminName, 'cron', 'Usuario que Actualizó Cron', 'text');

                BotSetting::syncToFile();

                return ['success' => true, 'type' => 'success', 'message' => "Horario {$timeToRemove} eliminado de la programación."];
            }

            return ['success' => true, 'type' => 'warning', 'message' => "El horario {$timeToRemove} no fue encontrado."];
        } catch (\Throwable $e) {
            Log::error('Error eliminando horario cron en MariaDB: ' . $e->getMessage());
            return ['success' => false, 'type' => 'error', 'message' => 'Error al eliminar el horario.'];
        }
    }
}
