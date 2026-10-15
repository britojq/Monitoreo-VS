<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Cron\AddCronScheduleAction;
use App\Actions\Cron\RemoveCronScheduleAction;
use App\Actions\Cron\ToggleCronAction;
use App\Actions\Cron\UpdateWebCheckIntervalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cron\AddCronScheduleRequest;
use App\Http\Requests\Cron\RemoveCronScheduleRequest;
use App\Http\Requests\Cron\UpdateWebCheckIntervalRequest;
use App\Models\BotSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
    public function toggle(Request $request, ?ToggleCronAction $action = null): RedirectResponse
    {
        $action = $action ?? app(ToggleCronAction::class);
        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : 'Administrador [Web]';

        $result = $action->execute($adminName);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Añade un nuevo horario de envío.
     */
    public function addSchedule(Request $request, ?AddCronScheduleAction $action = null): RedirectResponse
    {
        $action = $action ?? app(AddCronScheduleAction::class);
        $formRequest = $request instanceof AddCronScheduleRequest
            ? $request
            : AddCronScheduleRequest::createFrom($request);

        if (!$request instanceof AddCronScheduleRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : 'Administrador [Web]';

        $result = $action->execute(trim((string) $formRequest->validated('time')), $adminName);

        return back()->with($result['type'] ?? 'info', $result['message']);
    }

    /**
     * Elimina un horario de envío.
     */
    public function removeSchedule(Request $request, ?RemoveCronScheduleAction $action = null): RedirectResponse
    {
        $action = $action ?? app(RemoveCronScheduleAction::class);
        $formRequest = $request instanceof RemoveCronScheduleRequest
            ? $request
            : RemoveCronScheduleRequest::createFrom($request);

        if (!$request instanceof RemoveCronScheduleRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : 'Administrador [Web]';

        $result = $action->execute(trim((string) $formRequest->validated('time')), $adminName);

        return back()->with($result['type'] ?? 'info', $result['message']);
    }

    /**
     * Actualiza el intervalo de chequeo y actualización de servicios para el portal web.
     */
    public function updateWebCheckInterval(Request $request, ?UpdateWebCheckIntervalAction $action = null): RedirectResponse
    {
        $action = $action ?? app(UpdateWebCheckIntervalAction::class);
        $formRequest = $request instanceof UpdateWebCheckIntervalRequest
            ? $request
            : UpdateWebCheckIntervalRequest::createFrom($request);

        if (!$request instanceof UpdateWebCheckIntervalRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $user = $request->user();
        $adminName = $user ? "{$user->name} [Web]" : 'Administrador [Web]';

        $result = $action->execute((int) $formRequest->validated('interval_minutes'), $adminName);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }
}
