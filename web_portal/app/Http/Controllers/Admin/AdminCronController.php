<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCronController extends Controller
{
    /**
     * Obtiene la configuración de cron de forma estructurada directamente desde MariaDB (SSOT).
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

    /**
     * Alterna la activación/pausa de los envíos automáticos.
     */
    public function toggle(Request $request): RedirectResponse
    {
        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : "Administrador [Web]";
        $nowStr = date('Y-m-d H:i:s');

        try {
            $currentState = (bool) BotSetting::get('cron_reports_enabled', false);
            $newState = !$currentState;

            BotSetting::set('cron_reports_enabled', $newState, 'cron', 'Envíos Automáticos de Reportes Programados', 'boolean');
            BotSetting::set('cron_reports_updated_at', $nowStr, 'cron', 'Última Actualización de Cron', 'text');
            BotSetting::set('cron_reports_updated_by', $adminName, 'cron', 'Usuario que Actualizó Cron', 'text');

            // Exportación unidireccional para demonios en Python
            BotSetting::syncToFile();

            $msg = $newState 
                ? 'Envíos programados por cron ACTIVADOS correctamente en la base de datos central.' 
                : 'Envíos programados por cron PAUSADOS correctamente en la base de datos central.';

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Error alternando estado de cron en MariaDB: ' . $e->getMessage());
            return back()->with('error', 'No fue posible actualizar el estado de los envíos programados.');
        }
    }

    /**
     * Añade un nuevo horario de envío.
     */
    public function addSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ], [
            'time.regex' => 'El horario debe tener formato válido de 24 horas (HH:MM).',
        ]);

        $newTime = trim((string) $validated['time']);
        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : "Administrador [Web]";
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

                return back()->with('success', "Horario {$newTime} agregado exitosamente a la programación.");
            }

            return back()->with('info', "El horario {$newTime} ya se encuentra programado.");
        } catch (\Throwable $e) {
            Log::error('Error añadiendo horario cron en MariaDB: ' . $e->getMessage());
            return back()->with('error', 'Error al guardar el nuevo horario.');
        }
    }

    /**
     * Elimina un horario de envío.
     */
    public function removeSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'time' => ['required', 'string'],
        ]);

        $timeToRemove = trim((string) $validated['time']);
        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : "Administrador [Web]";
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

                return back()->with('success', "Horario {$timeToRemove} eliminado de la programación.");
            }

            return back()->with('warning', "El horario {$timeToRemove} no fue encontrado.");
        } catch (\Throwable $e) {
            Log::error('Error eliminando horario cron en MariaDB: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar el horario.');
        }
    }

    /**
     * Actualiza el intervalo de chequeo y actualización de servicios para el portal web.
     */
    public function updateWebCheckInterval(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ], [
            'interval_minutes.required' => 'Debe indicar el intervalo en minutos.',
            'interval_minutes.integer' => 'El intervalo debe ser un valor entero.',
            'interval_minutes.min' => 'El intervalo mínimo es de 1 minuto.',
            'interval_minutes.max' => 'El intervalo máximo es de 1440 minutos (24 horas).',
        ]);

        $interval = (int) $validated['interval_minutes'];
        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : "Administrador [Web]";
        $nowStr = date('Y-m-d H:i:s');

        try {
            BotSetting::set('web_check_interval_minutes', $interval, 'cron', 'Intervalo de Sondeo Web (minutos)', 'integer');
            BotSetting::set('web_check_interval_updated_at', $nowStr, 'cron', 'Última Actualización de Intervalo Web', 'text');
            BotSetting::set('web_check_interval_updated_by', $adminName, 'cron', 'Usuario que Actualizó Intervalo Web', 'text');

            BotSetting::syncToFile();

            return back()->with('success', "Frecuencia de monitoreo y actualización web configurada a {$interval} minutos exitosamente.");
        } catch (\Throwable $e) {
            Log::error('Error actualizando intervalo de monitoreo web en MariaDB: ' . $e->getMessage());
            return back()->with('error', 'Error al guardar el nuevo intervalo de monitoreo web.');
        }
    }
}
