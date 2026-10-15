<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\NetworkDevice\CreateNetworkDeviceAction;
use App\Actions\NetworkDevice\GetDeviceIndexViewModelAction;
use App\Actions\NetworkDevice\UpdateNetworkDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\NetworkDevice\StoreNetworkDeviceRequest;
use App\Http\Requests\NetworkDevice\UpdateNetworkDeviceRequest;
use App\Models\MonitoredNetworkDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNetworkDeviceController extends Controller
{
    /**
     * Muestra el listado centralizado de dispositivos de red de todas las sedes.
     */
    public function index(Request $request, GetDeviceIndexViewModelAction $action): View
    {
        $viewModel = $action->execute($request);
        return view('admin.devices.index', $viewModel);
    }

    /**
     * Registra un nuevo dispositivo de red y lo asigna a una sede.
     */
    public function store(Request $request, ?CreateNetworkDeviceAction $action = null): RedirectResponse|JsonResponse
    {
        $action = $action ?? app(CreateNetworkDeviceAction::class);
        $formRequest = $request instanceof StoreNetworkDeviceRequest
            ? $request
            : StoreNetworkDeviceRequest::createFrom($request);

        if (!$request instanceof StoreNetworkDeviceRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $device = $action->execute($formRequest->toDto(), $request);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Dispositivo [{$device->name}] creado exitosamente.",
                'device' => $device,
            ], 201);
        }

        $siteName = $device->site?->name ?? 'Sede';
        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$device->name}] registrado exitosamente en la sede [{$siteName}].");
    }

    /**
     * Actualiza los datos técnicos y operativos de un dispositivo.
     */
    public function update(Request $request, MonitoredNetworkDevice $device, ?UpdateNetworkDeviceAction $action = null): RedirectResponse
    {
        $action = $action ?? app(UpdateNetworkDeviceAction::class);
        $formRequest = $request instanceof UpdateNetworkDeviceRequest
            ? $request
            : UpdateNetworkDeviceRequest::createFrom($request);

        if (!$request instanceof UpdateNetworkDeviceRequest) {
            $formRequest->setContainer(app())->validateResolved();
        }

        $updated = $action->execute($device, $formRequest->toDto(), $request);

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$updated->name}] actualizado y sincronizado exitosamente.");
    }

    /**
     * Elimina un dispositivo de red.
     */
    public function destroy(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden eliminar dispositivos.');
        }

        $name = $device->name;
        $device->delete();

        app(SyncController::class)->exportToConfigFiles();

        return redirect()->route('admin.devices.index')
            ->with('success', "Dispositivo [{$name}] eliminado exitosamente.");
    }

    /**
     * Activa o desactiva el monitoreo de un dispositivo.
     */
    public function toggle(Request $request, MonitoredNetworkDevice $device): RedirectResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            abort(403, 'Acceso Denegado: Solo administradores pueden modificar el estado del dispositivo.');
        }

        $device->is_active = !$device->is_active;
        $device->save();

        app(SyncController::class)->exportToConfigFiles();

        $status = $device->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Dispositivo [{$device->name}] {$status} exitosamente.");
    }

    /**
     * Retorna datos históricos para gráficos y telemetría.
     */
    public function history(Request $request, MonitoredNetworkDevice $device): JsonResponse
    {
        $range = $request->query('range', '24h');
        $hours = match ($range) {
            '6h' => 6,
            '7d' => 168,
            '30d' => 720,
            default => 24,
        };

        $history = $device->histories()
            ->reorder('checked_at', 'asc')
            ->where('checked_at', '>=', now()->subHours($hours))
            ->get(['response_time_ms', 'is_up', 'checked_at']);

        return response()->json([
            'device' => $device->name,
            'ip' => $device->ip,
            'range' => $range,
            'data' => $history,
        ]);
    }
}
