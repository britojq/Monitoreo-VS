<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCronController extends Controller
{
    protected string $configPath = '/scripts/telegram-admin-bot/config/config.json';

    /**
     * Obtiene la configuración de cron de forma estructurada.
     */
    public function getCronConfig(): array
    {
        $default = [
            'enabled' => true,
            'schedules' => ['07:30', '16:00'],
            'updated_at' => '',
            'updated_by' => 'Sistema',
            'next_schedule' => null,
            'next_day' => 'hoy',
            'web_check_interval' => 10,
            'web_check_interval_updated_at' => '',
            'web_check_interval_updated_by' => 'Sistema',
        ];

        if (file_exists($this->configPath)) {
            try {
                $content = @file_get_contents($this->configPath);
                $data = json_decode($content, true) ?: [];

                $default['enabled'] = (bool) ($data['cron_reports_enabled'] ?? true);
                $schedules = $data['cron_schedules'] ?? ['07:30', '16:00'];
                if (is_array($schedules)) {
                    sort($schedules);
                    $default['schedules'] = array_values(array_unique($schedules));
                }
                $default['updated_at'] = $data['cron_reports_updated_at'] ?? '';
                $default['updated_by'] = $data['cron_reports_updated_by'] ?? 'Sistema';
                $default['web_check_interval'] = (int) ($data['web_check_interval_minutes'] ?? 10);
                $default['web_check_interval_updated_at'] = $data['web_check_interval_updated_at'] ?? '';
                $default['web_check_interval_updated_by'] = $data['web_check_interval_updated_by'] ?? 'Sistema';

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

            } catch (\Throwable $e) {
                Log::error('Error leyendo config.json para cron: ' . $e->getMessage());
            }
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
            $data = $this->readRawConfig();
            $currentState = (bool) ($data['cron_reports_enabled'] ?? true);
            $newState = !$currentState;

            $data['cron_reports_enabled'] = $newState;
            $data['cron_reports_updated_at'] = $nowStr;
            $data['cron_reports_updated_by'] = $adminName;

            $this->writeRawConfig($data);

            $msg = $newState 
                ? 'Envíos programados por cron ACTIVADOS correctamente.' 
                : 'Envíos programados por cron PAUSADOS correctamente.';

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Error alternando estado de cron: ' . $e->getMessage());
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

        $newTime = trim($validated['time']);
        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : "Administrador [Web]";
        $nowStr = date('Y-m-d H:i:s');

        try {
            $data = $this->readRawConfig();
            $schedules = $data['cron_schedules'] ?? ['07:30', '16:00'];
            if (!is_array($schedules)) {
                $schedules = ['07:30', '16:00'];
            }

            if (!in_array($newTime, $schedules)) {
                $schedules[] = $newTime;
                sort($schedules);
                $data['cron_schedules'] = array_values(array_unique($schedules));
                $data['cron_reports_updated_at'] = $nowStr;
                $data['cron_reports_updated_by'] = $adminName;
                $this->writeRawConfig($data);
                return back()->with('success', "Horario {$newTime} agregado exitosamente a la programación.");
            }

            return back()->with('info', "El horario {$newTime} ya se encuentra programado.");
        } catch (\Throwable $e) {
            Log::error('Error añadiendo horario cron: ' . $e->getMessage());
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

        $timeToRemove = trim($validated['time']);
        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : "Administrador [Web]";
        $nowStr = date('Y-m-d H:i:s');

        try {
            $data = $this->readRawConfig();
            $schedules = $data['cron_schedules'] ?? ['07:30', '16:00'];
            if (!is_array($schedules)) {
                $schedules = ['07:30', '16:00'];
            }

            $key = array_search($timeToRemove, $schedules);
            if ($key !== false) {
                unset($schedules[$key]);
                sort($schedules);
                $data['cron_schedules'] = array_values($schedules);
                $data['cron_reports_updated_at'] = $nowStr;
                $data['cron_reports_updated_by'] = $adminName;
                $this->writeRawConfig($data);
                return back()->with('success', "Horario {$timeToRemove} eliminado de la programación.");
            }

            return back()->with('warning', "El horario {$timeToRemove} no fue encontrado.");
        } catch (\Throwable $e) {
            Log::error('Error eliminando horario cron: ' . $e->getMessage());
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
            $data = $this->readRawConfig();
            $data['web_check_interval_minutes'] = $interval;
            $data['web_check_interval_updated_at'] = $nowStr;
            $data['web_check_interval_updated_by'] = $adminName;
            $this->writeRawConfig($data);

            return back()->with('success', "Frecuencia de monitoreo y actualización web configurada a {$interval} minutos exitosamente.");
        } catch (\Throwable $e) {
            Log::error('Error actualizando intervalo de monitoreo web: ' . $e->getMessage());
            return back()->with('error', 'Error al guardar el nuevo intervalo de monitoreo web.');
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
