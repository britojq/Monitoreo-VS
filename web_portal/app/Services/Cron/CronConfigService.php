<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Contracts\Services\CronConfigServiceInterface;
use App\Models\BotSetting;

class CronConfigService implements CronConfigServiceInterface
{
    /**
     * Obtiene la configuración de cron de forma estructurada directamente desde MariaDB (SSOT).
     * Desacoplado de la capa HTTP para cumplir con el Principio de Responsabilidad Única (SRP)
     * e Inversión de Dependencias (DIP).
     *
     * @return array<string, mixed>
     */
    public function getCronConfig(): array
    {
        $default = [
            'enabled' => (bool) BotSetting::get('cron_reports_enabled', false),
            'schedules' => BotSetting::get('cron_schedules', ['07:30', '16:00']),
            'updated_at' => (string) BotSetting::get('cron_reports_updated_at', ''),
            'updated_by' => (string) BotSetting::get('cron_reports_updated_by', 'Sistema'),
            'next_schedule' => null,
            'next_day' => 'hoy',
            'web_check_interval' => (int) BotSetting::get('web_check_interval_minutes', 60),
            'web_check_interval_updated_at' => (string) BotSetting::get('web_check_interval_updated_at', ''),
            'web_check_interval_updated_by' => (string) BotSetting::get('web_check_interval_updated_by', 'Sistema'),
        ];

        if (is_array($default['schedules'])) {
            sort($default['schedules']);
            $default['schedules'] = array_values(array_unique($default['schedules']));
        } else {
            $default['schedules'] = ['07:30', '16:00'];
        }

        // Calcular próximo envío
        $nowHm = date('H:i');
        foreach ($default['schedules'] as $sch) {
            if ($sch > $nowHm) {
                $default['next_schedule'] = $sch;
                $default['next_day'] = 'hoy';
                break;
            }
        }
        if (!$default['next_schedule'] && !empty($default['schedules'])) {
            $default['next_schedule'] = $default['schedules'][0];
            $default['next_day'] = 'mañana';
        }

        return $default;
    }
}
