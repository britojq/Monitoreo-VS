<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Cron\AddCronScheduleAction;
use App\Actions\Cron\RemoveCronScheduleAction;
use App\Actions\Cron\ToggleCronAction;
use App\Actions\Cron\UpdateWebCheckIntervalAction;
use App\Contracts\Services\CronConfigServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cron\AddCronScheduleRequest;
use App\Http\Requests\Cron\RemoveCronScheduleRequest;
use App\Http\Requests\Cron\UpdateWebCheckIntervalRequest;
use App\Models\BotSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCronController extends Controller
{
    private readonly CronConfigServiceInterface $cronConfigService;

    public function __construct(
        ?CronConfigServiceInterface $cronConfigService = null,
    ) {
        $this->cronConfigService = $cronConfigService ?? app(CronConfigServiceInterface::class);
    }

    /**
     * Obtiene la configuración de cron delegando al servicio desacoplado (SSOT).
     */
    public function getCronConfig(): array
    {
        return $this->cronConfigService->getCronConfig();
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
